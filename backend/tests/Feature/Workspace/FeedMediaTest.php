<?php

/** Mural: fotos privadas com URL assinada, reações e menções. */

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function feedMember(Hotel $hotel, string $role = 'employee'): User
{
    $user = User::factory()->create();
    HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'approved']);

    return $user;
}

/** JPEG com EXIF contendo um marcador de GPS. */
function feedJpegWithGps(): string
{
    $tiff = "II*\x00\x08\x00\x00\x00\x00\x00\x00\x00\x00\x00GPS-LAT-23.55,-46.63";
    $exif = "Exif\0\0" . $tiff;

    return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
        . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif
        . "\xFF\xDA\x00\x08\x01\x01\x00\x00?\x00PIXELS\xFF\xD9";
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->hotel  = Hotel::factory()->create();
    $this->base   = "/api/v1/workspaces/{$this->hotel->slug}";
    $this->rh     = feedMember($this->hotel, 'admin');
    $this->worker = feedMember($this->hotel);
    $this->other  = feedMember($this->hotel);
});

function feedPost($test, array $fields): Illuminate\Testing\TestResponse
{
    return $test->post("{$test->base}/feed", $fields, ['Accept' => 'application/json']);
}

it('publishes photos on private disk and serves them only through a signed URL', function () {
    Sanctum::actingAs($this->rh);
    $res = feedPost($this, [
        'body' => 'Festa de fim de ano',
        'photos' => [UploadedFile::fake()->image('a.png', 40, 40), UploadedFile::fake()->image('b.png', 40, 40)],
    ])->assertCreated();

    expect($res->json('data.media'))->toHaveCount(2);
    $url = $res->json('data.media.0.url');
    expect($url)->toContain('/api/v1/workspace-media/')->toContain('signature=');
    expect(Storage::disk('local')->allFiles('workspace-posts'))->toHaveCount(2);
    expect(Storage::disk('public')->allFiles())->toBeEmpty(); // nunca no disco público

    // sem login: a URL assinada basta; URL adulterada ou sem assinatura é recusada
    $this->app['auth']->forgetGuards();
    $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(preg_replace('/signature=[^&]+/', 'signature=falsa', $url))->assertForbidden();
    $this->get(strtok($url, '?'))->assertForbidden();
});

it('removes the files from disk when the post is deleted and the old link stops working', function () {
    Sanctum::actingAs($this->rh);
    $res = feedPost($this, ['body' => 'Aviso', 'photos' => [UploadedFile::fake()->image('a.png', 20, 20)]])->assertCreated();
    $url = $res->json('data.media.0.url');
    $this->deleteJson("{$this->base}/feed/" . $res->json('data.uuid'))->assertOk();

    expect(Storage::disk('local')->allFiles('workspace-posts'))->toBeEmpty();
    $this->app['auth']->forgetGuards();
    $this->get($url)->assertNotFound();
});

it('strips EXIF and GPS from the stored photo', function () {
    Sanctum::actingAs($this->rh);
    feedPost($this, ['body' => 'Foto', 'photos' => [UploadedFile::fake()->createWithContent('x.jpg', feedJpegWithGps())]])->assertCreated();

    $path = Storage::disk('local')->allFiles('workspace-posts')[0];
    $stored = Storage::disk('local')->get($path);
    expect($stored)->not->toContain('GPS-LAT')->toContain('PIXELS');
});

it('validates photos and keeps publishing to RH and managers', function () {
    Sanctum::actingAs($this->rh);
    feedPost($this, ['body' => ''])->assertStatus(422);
    feedPost($this, ['body' => 'x', 'photos' => array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.png", 10, 10), range(1, 7))])->assertStatus(422);
    feedPost($this, ['body' => 'x', 'photos' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')]])->assertStatus(422);
    feedPost($this, ['photos' => [UploadedFile::fake()->image('so-foto.png', 10, 10)]])->assertCreated(); // só foto, sem texto

    Sanctum::actingAs($this->worker);
    feedPost($this, ['body' => 'oi'])->assertForbidden();
});

it('supports one reaction per person, counts them and notifies the author once', function () {
    Sanctum::actingAs($this->rh);
    $uuid = feedPost($this, ['body' => 'Meta batida!'])->assertCreated()->json('data.uuid');

    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/feed/{$uuid}/react", ['reaction' => 'love'])->assertOk()
        ->assertJsonPath('data.reactions.love', 1)->assertJsonPath('data.my_reaction', 'love');
    $this->postJson("{$this->base}/feed/{$uuid}/react", ['reaction' => 'clap'])->assertOk()
        ->assertJsonPath('data.reactions.love', 0)->assertJsonPath('data.reactions.clap', 1)->assertJsonPath('data.likes_count', 1);
    $this->postJson("{$this->base}/feed/{$uuid}/react", ['reaction' => 'bogus'])->assertStatus(422);
    expect(Notification::where('user_id', $this->rh->id)->where('type', 'workspace_reaction')->count())->toBe(1);

    Sanctum::actingAs($this->other);
    $this->postJson("{$this->base}/feed/{$uuid}/react", ['reaction' => 'celebrate'])->assertOk()->assertJsonPath('data.likes_count', 2);

    $list = $this->getJson("{$this->base}/feed")->assertOk();
    expect($list->json('data.items.0.my_reaction'))->toBe('celebrate')->and($list->json('data.items.0.reactions.clap'))->toBe(1);

    $this->postJson("{$this->base}/feed/{$uuid}/react", ['reaction' => null])->assertOk()->assertJsonPath('data.my_reaction', null)->assertJsonPath('data.likes_count', 1);
});

it('notifies mentioned people, ignores strangers and self, and returns names from the server', function () {
    $outsider = User::factory()->create();
    Sanctum::actingAs($this->rh);
    $body = "Parabéns @[Nome Falso]({$this->worker->uuid}) e @[Eu]({$this->rh->uuid}) e @[Intruso]({$outsider->uuid})!";
    $res = feedPost($this, ['body' => $body])->assertCreated();

    expect(Notification::where('user_id', $this->worker->id)->where('type', 'workspace_mention')->count())->toBe(1);
    expect(Notification::where('user_id', $this->rh->id)->where('type', 'workspace_mention')->count())->toBe(0);
    expect(Notification::where('user_id', $outsider->id)->count())->toBe(0);
    // o nome vem do cadastro, não do texto digitado; quem não é da empresa não aparece
    expect($res->json('data.mentions'))->toHaveKey($this->worker->uuid)->not->toHaveKey($outsider->uuid);
    expect($res->json('data.mentions')[$this->worker->uuid])->toBe($this->worker->name);

    // menção em comentário
    $uuid = $res->json('data.uuid');
    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/feed/{$uuid}/comments", ['body' => "Valeu @[x]({$this->other->uuid})"])->assertCreated();
    expect(Notification::where('user_id', $this->other->id)->where('type', 'workspace_mention')->count())->toBe(1);
});
