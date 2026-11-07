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
