<?php

use App\Models\ChatGroup;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->ana = User::factory()->create();
    $this->bruno = User::factory()->create();
    $this->carla = User::factory()->create();
    $this->sent = fn (User $from, User $to) => (function () use ($from, $to) {
        Sanctum::actingAs($from);

        return $this->postJson("/api/v1/chat/{$to->uuid}/messages", ['content' => 'oi'])->assertCreated()->json('data.uuid');
    })->call($this);
    $this->statusSeenByAna = fn () => (function () {
        Sanctum::actingAs($this->ana);

        return $this->getJson("/api/v1/chat/{$this->bruno->uuid}/messages")->json('data.0.status');
    })->call($this);
});

it('goes from sent to delivered to read, WhatsApp style', function () {
    ($this->sent)($this->ana, $this->bruno);
    expect(($this->statusSeenByAna)())->toBe('sent');

    Sanctum::actingAs($this->bruno);
    $this->getJson('/api/v1/chat/unread-count')->assertOk();            // o app do Bruno buscou as mensagens
    expect(($this->statusSeenByAna)())->toBe('delivered');

    Sanctum::actingAs($this->bruno);
    $this->patchJson("/api/v1/chat/{$this->ana->uuid}/read")->assertOk(); // Bruno abriu a conversa
    expect(($this->statusSeenByAna)())->toBe('read');
});

it('hides the read mark when either side turns read receipts off, but keeps unread counters working', function () {
    ($this->sent)($this->ana, $this->bruno);
    Sanctum::actingAs($this->bruno);
    $this->putJson('/api/v1/me/chat-settings', ['read_receipts' => false])->assertOk()->assertJsonPath('data.read_receipts', false);
    $this->patchJson("/api/v1/chat/{$this->ana->uuid}/read")->assertOk();
    expect($this->getJson('/api/v1/chat/unread-count')->json('data.count'))->toBe(0);   // contador segue certo
    expect(($this->statusSeenByAna)())->toBe('delivered');                              // mas a Ana não vê "lida"

    // agora é a Ana quem desliga: também deixa de ver a leitura dos outros
    Sanctum::actingAs($this->bruno);
    $this->putJson('/api/v1/me/chat-settings', ['read_receipts' => true]);
    Sanctum::actingAs($this->ana);
    $this->putJson('/api/v1/me/chat-settings', ['read_receipts' => false]);
    expect(($this->statusSeenByAna)())->toBe('delivered');
    expect($this->getJson('/api/v1/me/chat-settings')->json('data.read_receipts'))->toBeFalse();
});

it('never exposes the read time of my own messages and only shows status on mine', function () {
    ($this->sent)($this->ana, $this->bruno);
    Sanctum::actingAs($this->bruno);
    $m = $this->getJson("/api/v1/chat/{$this->ana->uuid}/messages")->json('data.0');
    expect($m['status'])->toBeNull();                                    // mensagem recebida não tem status
});

it('marks a group message as read only when every other member who keeps receipts has opened it', function () {
    $hotel = Hotel::factory()->create();
    foreach ([$this->ana, $this->bruno, $this->carla] as $u) {
        HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $u->id, 'role' => 'employee', 'status' => 'approved']);
    }
    $g = ChatGroup::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'created_by' => $this->ana->id, 'name' => 'Equipe']);
    foreach ([$this->ana, $this->bruno, $this->carla] as $u) {
        $g->members()->create(['user_id' => $u->id, 'role' => 'member']);
    }
    Sanctum::actingAs($this->ana);
    $this->postJson("/api/v1/chat-groups/{$g->uuid}/messages", ['content' => 'Bom dia, time'])->assertCreated();
    $status = fn () => (function () use ($g) { Sanctum::actingAs($this->ana); return $this->getJson("/api/v1/chat-groups/{$g->uuid}/messages")->json('data.0.status'); })->call($this);
    expect($status())->toBe('sent');

    foreach ([$this->bruno, $this->carla] as $i => $u) {
        $this->travel(2)->seconds();
        Sanctum::actingAs($u);
        $this->patchJson("/api/v1/chat-groups/{$g->uuid}/read")->assertOk();
        expect($status())->toBe($i === 0 ? 'sent' : 'read');         // só vira "lida" quando o último abriu
    }
});

it('lets participants react to a direct message, switch or remove the reaction, and shows it to both', function () {
    $uuid = ($this->sent)($this->ana, $this->bruno);
    Sanctum::actingAs($this->bruno);
    $r = $this->postJson("/api/v1/chat/messages/{$uuid}/react", ['emoji' => '❤️'])->assertOk();
    expect($r->json('data.reactions.0'))->toMatchArray(['emoji' => '❤️', 'count' => 1, 'mine' => true]);
    $this->postJson("/api/v1/chat/messages/{$uuid}/react", ['emoji' => '😂'])->assertOk();            // trocar
    Sanctum::actingAs($this->ana);
    $this->postJson("/api/v1/chat/messages/{$uuid}/react", ['emoji' => '😂'])->assertOk();            // a outra pessoa também

    $msg = $this->getJson("/api/v1/chat/{$this->bruno->uuid}/messages")->json('data.0');
    expect($msg['reactions'])->toHaveCount(1)->and($msg['reactions'][0]['emoji'])->toBe('😂')->and($msg['reactions'][0]['count'])->toBe(2)->and($msg['reactions'][0]['mine'])->toBeTrue();

    $this->postJson("/api/v1/chat/messages/{$uuid}/react", ['emoji' => null])->assertOk();            // remover a minha
    expect($this->getJson("/api/v1/chat/{$this->bruno->uuid}/messages")->json('data.0.reactions.0.count'))->toBe(1);
});

it('rejects unknown emojis and strangers reacting to a conversation they are not in', function () {
    $uuid = ($this->sent)($this->ana, $this->bruno);
    Sanctum::actingAs($this->bruno);
    $this->postJson("/api/v1/chat/messages/{$uuid}/react", ['emoji' => '💣'])->assertUnprocessable();
    Sanctum::actingAs($this->carla);
    $this->postJson("/api/v1/chat/messages/{$uuid}/react", ['emoji' => '👍'])->assertNotFound();
});

it('reacts to group messages only as a member', function () {
    $hotel = Hotel::factory()->create();
    foreach ([$this->ana, $this->bruno] as $u) {
        HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $u->id, 'role' => 'employee', 'status' => 'approved']);
    }
    $g = ChatGroup::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'created_by' => $this->ana->id, 'name' => 'Equipe']);
    foreach ([$this->ana, $this->bruno] as $u) {
        $g->members()->create(['user_id' => $u->id, 'role' => 'member']);
    }
    Sanctum::actingAs($this->ana);
    $uuid = $this->postJson("/api/v1/chat-groups/{$g->uuid}/messages", ['content' => 'Reunião às 10h'])->json('data.uuid');

    Sanctum::actingAs($this->bruno);
    $this->postJson("/api/v1/chat/messages/{$uuid}/react", ['emoji' => '👍'])->assertOk();
    expect($this->getJson("/api/v1/chat-groups/{$g->uuid}/messages")->json('data.0.reactions.0.emoji'))->toBe('👍');

    Sanctum::actingAs($this->carla);
    $this->postJson("/api/v1/chat/messages/{$uuid}/react", ['emoji' => '👍'])->assertNotFound();
});

it('lets group managers set, show and remove the group photo; members cannot', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    $hotel = Hotel::factory()->create();
    HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $this->ana->id, 'role' => 'admin', 'access_role' => 'owner', 'status' => 'approved']);
    HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $this->bruno->id, 'role' => 'employee', 'status' => 'approved']);
    $g = ChatGroup::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'created_by' => $this->ana->id, 'name' => 'Equipe']);
    $g->members()->create(['user_id' => $this->ana->id, 'role' => 'admin']);
    $g->members()->create(['user_id' => $this->bruno->id, 'role' => 'member']);
    $png = fn () => Illuminate\Http\UploadedFile::fake()->image('capa.png', 200, 200);

    Sanctum::actingAs($this->bruno);
    $this->post("/api/v1/chat-groups/{$g->uuid}/avatar", ['photo' => $png()], ['Accept' => 'application/json'])->assertForbidden();

    Sanctum::actingAs($this->ana);
    $url = $this->post("/api/v1/chat-groups/{$g->uuid}/avatar", ['photo' => $png()], ['Accept' => 'application/json'])->assertOk()->json('data.avatar_url');
    expect($url)->toContain('/storage/chat-groups/');
    expect($this->getJson("/api/v1/chat-groups/{$g->uuid}")->json('data.avatar_url'))->toBe($url);
    $this->post("/api/v1/chat-groups/{$g->uuid}/avatar", ['photo' => Illuminate\Http\UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable();

    $this->deleteJson("/api/v1/chat-groups/{$g->uuid}/avatar")->assertOk();
    expect($this->getJson("/api/v1/chat-groups/{$g->uuid}")->json('data.avatar_url'))->toBeNull();
});

function stickerImage(): Illuminate\Http\UploadedFile
{
    return Illuminate\Http\UploadedFile::fake()->image('figurinha.png', 200, 200);
}

it('creates a custom sticker, sends it, and the receiver can see and save it', function () {
    Illuminate\Support\Facades\Storage::fake('local');
    Sanctum::actingAs($this->ana);
    $uuid = $this->post('/api/v1/stickers', ['image' => stickerImage()], ['Accept' => 'application/json'])->assertCreated()->json('data.uuid');
    expect($this->getJson('/api/v1/stickers')->json('data.items.0.uuid'))->toBe($uuid);

    // enviar na conversa
    $this->postJson("/api/v1/chat/{$this->bruno->uuid}/messages", ['type' => 'sticker', 'metadata' => ['sticker_uuid' => $uuid]])
        ->assertCreated()->assertJsonPath('data.type', 'sticker')->assertJsonPath('data.metadata.sticker_uuid', $uuid);

    // Bruno vê a imagem (recebeu) mas ainda não tem na coleção; Carla (fora da conversa) não vê nem salva
    Sanctum::actingAs($this->bruno);
    $this->get("/api/v1/stickers/{$uuid}/image")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($this->getJson('/api/v1/stickers')->json('data.items'))->toBe([]);
    Sanctum::actingAs($this->carla);
    $this->get("/api/v1/stickers/{$uuid}/image")->assertNotFound();
    $this->postJson("/api/v1/stickers/{$uuid}/save")->assertNotFound();

    // Bruno salva e passa a poder usar; remover da coleção dele não afeta a Ana
    Sanctum::actingAs($this->bruno);
    $this->postJson("/api/v1/stickers/{$uuid}/save")->assertOk();
    expect($this->getJson('/api/v1/stickers')->json('data.items.0.uuid'))->toBe($uuid);
    $this->postJson("/api/v1/chat/{$this->ana->uuid}/messages", ['type' => 'sticker', 'metadata' => ['sticker_uuid' => $uuid]])->assertCreated();
    $this->deleteJson("/api/v1/stickers/{$uuid}")->assertOk();
    expect($this->getJson('/api/v1/stickers')->json('data.items'))->toBe([]);
    Sanctum::actingAs($this->ana);
    expect($this->getJson('/api/v1/stickers')->json('data.items'))->toHaveCount(1);
});

it('does not let anyone send a custom sticker they do not hold, and rejects bad images', function () {
    Illuminate\Support\Facades\Storage::fake('local');
    Sanctum::actingAs($this->ana);
    $uuid = $this->post('/api/v1/stickers', ['image' => stickerImage()], ['Accept' => 'application/json'])->json('data.uuid');
    Sanctum::actingAs($this->carla);
    $this->postJson("/api/v1/chat/{$this->bruno->uuid}/messages", ['type' => 'sticker', 'metadata' => ['sticker_uuid' => $uuid]])->assertUnprocessable();
    $this->post('/api/v1/stickers', ['image' => Illuminate\Http\UploadedFile::fake()->createWithContent('x.html', '<script>1</script>')], ['Accept' => 'application/json'])->assertUnprocessable();
    $this->post('/api/v1/stickers', ['image' => Illuminate\Http\UploadedFile::fake()->image('mini.png', 10, 10)], ['Accept' => 'application/json'])->assertUnprocessable();
    $this->post('/api/v1/stickers', ['image' => Illuminate\Http\UploadedFile::fake()->image('grande.png', 2000, 2000)], ['Accept' => 'application/json'])->assertUnprocessable();
});
