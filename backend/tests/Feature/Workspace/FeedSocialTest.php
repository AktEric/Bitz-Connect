<?php

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use App\Models\WorkspacePost;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
    Carbon::setTestNow('2026-11-10 12:00:00');
    $this->hotel = Hotel::factory()->create();
    $mk = fn (string $role, array $extra = []) => tap(User::factory()->create(), fn ($u) => HotelUser::create(
        ['hotel_id' => $this->hotel->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'approved'] + $extra));
    $this->rh     = $mk('admin', ['access_role' => 'hr']);
    $this->person = $mk('employee', ['birth_date' => '1990-11-10']);
    $this->shy    = $mk('employee', ['birth_date' => '1992-11-10', 'birthday_visible' => false]);
    $this->base   = "/api/v1/workspaces/{$this->hotel->slug}";
});
afterEach(fn () => Carbon::setTestNow());

it('posts the birthday greeting once a day, only for people who allow it', function () {
    $this->artisan('workspace:birthday-posts')->assertSuccessful();
    $this->artisan('workspace:birthday-posts')->assertSuccessful();

    $posts = WorkspacePost::where('kind', 'birthday')->get();
    expect($posts)->toHaveCount(1)->and($posts[0]->subject_id)->toBe($this->person->id);

    Sanctum::actingAs($this->rh);
    $item = $this->getJson("{$this->base}/feed")->assertOk()->json('data.items.0');
    expect($item['kind'])->toBe('birthday')->and($item['subject']['name'])->toBe($this->person->name)->and($item['body'])->toContain('aniversário');

    // o aniversariante não apaga a própria homenagem; o RH apaga
    Sanctum::actingAs($this->person);
    expect($this->getJson("{$this->base}/feed")->json('data.items.0.can_delete'))->toBeFalse();
    Sanctum::actingAs($this->rh);
    expect($this->getJson("{$this->base}/feed")->json('data.items.0.can_delete'))->toBeTrue();
});

it('shows the latest comments right in the feed', function () {
    $this->artisan('workspace:birthday-posts');
    $uuid = WorkspacePost::value('uuid');
    foreach (['Parabéns!', 'Felicidades', 'Muitos anos de vida'] as $text) {
        Sanctum::actingAs($this->rh);
        $this->postJson("{$this->base}/feed/{$uuid}/comments", ['body' => $text])->assertCreated();
    }
    $item = $this->getJson("{$this->base}/feed")->json('data.items.0');
    expect($item['comments_count'])->toBe(3)->and(collect($item['comments_preview'])->pluck('body')->all())->toBe(['Felicidades', 'Muitos anos de vida']);
});

it('publishes a video with a private signed URL and rejects other file types', function () {
    Sanctum::actingAs($this->rh);
    $mp4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom" . str_repeat("\0", 64);
    $path = sys_get_temp_dir() . '/clip.mp4';
    file_put_contents($path, $mp4);

    $r = $this->post("{$this->base}/feed", ['body' => 'Vídeo da convenção', 'video' => new UploadedFile($path, 'clip.mp4', 'video/mp4', null, true)], ['Accept' => 'application/json'])->assertCreated();
    $media = $r->json('data.media.0');
    expect($media['kind'])->toBe('video')->and($media['url'])->toContain('signature=');

    $bad = sys_get_temp_dir() . '/x.exe';
    file_put_contents($bad, 'MZ not a video');
    $this->post("{$this->base}/feed", ['body' => 'x', 'video' => new UploadedFile($bad, 'x.mp4', 'video/mp4', null, true)], ['Accept' => 'application/json'])->assertUnprocessable();

    // a URL assinada entrega o vídeo
    $this->get($media['url'])->assertOk();
});

it('lets anyone recognize a colleague, publishes it on the feed, notifies them and limits spam', function () {
    Sanctum::actingAs($this->person);
    $this->postJson("{$this->base}/kudos", ['to_uuid' => $this->person->uuid, 'badge' => 'thanks', 'message' => 'Obrigado por tudo'])->assertUnprocessable(); // não vale a si mesmo
    $this->postJson("{$this->base}/kudos", ['to_uuid' => $this->rh->uuid, 'badge' => 'nope', 'message' => 'Obrigado por tudo'])->assertUnprocessable();

    $item = $this->postJson("{$this->base}/kudos", ['to_uuid' => $this->rh->uuid, 'badge' => 'teamwork', 'message' => 'Ajudou muito no fechamento'])->assertCreated()->json('data');
    expect($item['kind'])->toBe('kudos')->and($item['badge'])->toBe('teamwork')->and($item['subject']['name'])->toBe($this->rh->name)->and($item['author']['name'])->toBe($this->person->name);
    expect(\App\Models\Notification::where('user_id', $this->rh->id)->where('type', 'workspace_kudos')->count())->toBe(1);

    Sanctum::actingAs($this->shy);
    expect($this->getJson("{$this->base}/feed")->json('data.items.0.kind'))->toBe('kudos');

    // o autor e o RH apagam; limite de 5 por dia
    Sanctum::actingAs($this->person);
    foreach (range(1, 4) as $i) {
        $this->postJson("{$this->base}/kudos", ['to_uuid' => $this->rh->uuid, 'badge' => 'service', 'message' => "Mensagem número {$i}"])->assertCreated();
    }
    $this->postJson("{$this->base}/kudos", ['to_uuid' => $this->rh->uuid, 'badge' => 'service', 'message' => 'Mais um elogio'])->assertStatus(429);
});

it('lets RH run an anonymous poll: one vote per person, changeable until it closes', function () {
    Sanctum::actingAs($this->person);
    $this->postJson("{$this->base}/polls", ['question' => 'Almoço de sexta?', 'options' => ['Sim', 'Não']])->assertForbidden();

    Sanctum::actingAs($this->rh);
    $poll = $this->postJson("{$this->base}/polls", ['question' => 'Onde será a festa de fim de ano?', 'options' => ['Salão', 'Chácara', 'Salão'], 'closes_on' => '2026-11-12'])->assertUnprocessable();
    $poll = $this->postJson("{$this->base}/polls", ['question' => 'Onde será a festa de fim de ano?', 'options' => ['Salão', 'Chácara'], 'closes_on' => '2026-11-12'])->assertCreated()->json('data');
    expect($poll['kind'])->toBe('poll')->and($poll['poll']['total'])->toBe(0)->and($poll['poll']['options'])->toHaveCount(2);
    [$a, $b] = array_column($poll['poll']['options'], 'uuid');

    Sanctum::actingAs($this->person);
    $this->postJson("{$this->base}/feed/{$poll['uuid']}/vote", ['option_uuid' => $a])->assertOk()->assertJsonPath('data.my_vote', $a);
    $this->postJson("{$this->base}/feed/{$poll['uuid']}/vote", ['option_uuid' => $b])->assertOk();           // troca de voto
    Sanctum::actingAs($this->shy);
    $this->postJson("{$this->base}/feed/{$poll['uuid']}/vote", ['option_uuid' => $b])->assertOk();
    $this->postJson("{$this->base}/feed/{$poll['uuid']}/vote", ['option_uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'])->assertUnprocessable();

    $feed = $this->getJson("{$this->base}/feed")->json('data.items.0.poll');
    expect($feed['total'])->toBe(2)->and(collect($feed['options'])->pluck('votes', 'label')->all())->toBe(['Salão' => 0, 'Chácara' => 2]);
    expect(json_encode($feed))->not->toContain('user');

    // depois do prazo, não vota mais
    Carbon::setTestNow('2026-11-13 12:00:00');
    $this->postJson("{$this->base}/feed/{$poll['uuid']}/vote", ['option_uuid' => $a])->assertStatus(409);
    expect($this->getJson("{$this->base}/feed")->json('data.items.0.poll.closed'))->toBeTrue();
});
