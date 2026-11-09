<?php

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
    $this->a = User::factory()->create();
    $this->b = User::factory()->create();
    $this->c = User::factory()->create();
});

function chatPng(): UploadedFile
{
    $im = imagecreatetruecolor(20, 20); $f = tempnam(sys_get_temp_dir(), 'p') ?: '/tmp/x'; imagepng($im, $f);

    return new UploadedFile($f, 'foto.png', 'image/png', null, true);
}

it('sends a photo, a file and a voice note, and hides the disk path', function () {
    Sanctum::actingAs($this->a);

    $r = $this->postJson("/api/v1/chat/{$this->b->uuid}/messages", ['file' => chatPng(), 'content' => 'olha isso'])->assertCreated();
    expect($r->json('data.type'))->toBe('image')->and($r->json('data.content'))->toBe('olha isso')->and($r->json('data.metadata'))->not->toHaveKey('path');

    $pdf = UploadedFile::fake()->createWithContent('contrato.pdf', '%PDF-1.4 x');
    $this->postJson("/api/v1/chat/{$this->b->uuid}/messages", ['file' => $pdf])->assertCreated()->assertJsonPath('data.type', 'file')->assertJsonPath('data.content', '📎 contrato.pdf');

    $audio = UploadedFile::fake()->createWithContent('voz.webm', str_repeat('a', 500));
    $this->postJson("/api/v1/chat/{$this->b->uuid}/messages", ['file' => $audio, 'voice' => true, 'duration' => 7])
        ->assertCreated()->assertJsonPath('data.type', 'audio')->assertJsonPath('data.metadata.duration', 7);

    expect(Message::count())->toBe(3);
});

it('rejects dangerous files', function () {
    Sanctum::actingAs($this->a);
    foreach (['x.html', 'x.php', 'x.svg', 'x.exe'] as $name) {
        $this->postJson("/api/v1/chat/{$this->b->uuid}/messages", ['file' => UploadedFile::fake()->createWithContent($name, '<script>1</script>')])->assertUnprocessable();
    }
});

it('delivers the attachment only to the two participants', function () {
    Sanctum::actingAs($this->a);
    $uuid = $this->postJson("/api/v1/chat/{$this->b->uuid}/messages", ['file' => chatPng()])->json('data.metadata.uuid');

    $this->get("/api/v1/chat/attachments/{$uuid}")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    Sanctum::actingAs($this->b);
    $this->get("/api/v1/chat/attachments/{$uuid}")->assertOk();
    Sanctum::actingAs($this->c);
    $this->get("/api/v1/chat/attachments/{$uuid}")->assertNotFound();
});

it('sends stickers and GIFs, and rejects forged ones', function () {
    Sanctum::actingAs($this->a);
    $u = "/api/v1/chat/{$this->b->uuid}/messages";

    $this->postJson($u, ['type' => 'sticker', 'metadata' => ['id' => 'parabens']])->assertCreated()->assertJsonPath('data.type', 'sticker')->assertJsonPath('data.metadata.id', 'parabens');
    $this->postJson($u, ['type' => 'sticker', 'metadata' => ['id' => '../x']])->assertUnprocessable();

    $gif = 'https://media2.giphy.com/media/abc123/200.gif';
    $this->postJson($u, ['type' => 'gif', 'metadata' => ['url' => $gif, 'title' => 'oi']])->assertCreated()->assertJsonPath('data.metadata.url', $gif);
    $this->postJson($u, ['type' => 'gif', 'metadata' => ['url' => 'https://evil.example/x.gif']])->assertUnprocessable();
});

it('keeps GIF search off without an API key', function () {
    config(['services.giphy.key' => null]);
    Sanctum::actingAs($this->a);
    $this->getJson('/api/v1/chat/gifs?q=oi')->assertOk()->assertJsonPath('data.enabled', false);
});
