<?php

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\MarketplaceListing;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
    $this->hotel = Hotel::factory()->create();
    $mk = fn (string $role, array $extra = []) => tap(User::factory()->create(), fn ($u) => HotelUser::create(
        ['hotel_id' => $this->hotel->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'approved'] + $extra));
    $this->rh     = $mk('admin', ['access_role' => 'hr']);
    $this->ana    = $mk('employee');
    $this->bruno  = $mk('employee');
    $this->base   = "/api/v1/workspaces/{$this->hotel->slug}/marketplace";
    $this->img    = fn () => UploadedFile::fake()->image('foto.png', 300, 300);
});

it('publishes a listing with photos and lists it for colleagues', function () {
    Sanctum::actingAs($this->ana);
    $r = $this->post($this->base, ['title' => 'Bicicleta aro 29', 'description' => 'Pouco usada, revisada.', 'price' => 850.5, 'category' => 'esportes', 'condition' => 'used', 'images' => [($this->img)(), ($this->img)()]], ['Accept' => 'application/json'])->assertCreated();
    expect($r->json('data.price_cents'))->toBe(85050)->and($r->json('data.images'))->toHaveCount(2)->and($r->json('data.images.0.url'))->toContain('signature=')->and($r->json('data.can_edit'))->toBeTrue();

    Sanctum::actingAs($this->bruno);
    $list = $this->getJson($this->base)->assertOk();
    expect($list->json('data.items'))->toHaveCount(1)->and($list->json('data.items.0.seller.uuid'))->toBe($this->ana->uuid)
        ->and($list->json('data.items.0.is_mine'))->toBeFalse()->and($list->json('data.categories'))->toHaveKey('esportes');
});

it('validates the listing and requires a photo', function () {
    Sanctum::actingAs($this->ana);
    $h = ['Accept' => 'application/json'];
    $this->post($this->base, ['title' => 'Oi', 'description' => 'x', 'category' => 'xx', 'condition' => 'used'], $h)->assertUnprocessable();
    $this->post($this->base, ['title' => 'Mesa de escritório', 'description' => 'Boa mesa de madeira', 'category' => 'casa', 'condition' => 'used'], $h)->assertUnprocessable()->assertJsonValidationErrors('images');
    $this->post($this->base, ['title' => 'Mesa de escritório', 'description' => 'Boa mesa de madeira', 'category' => 'casa', 'condition' => 'used', 'price' => -1, 'images' => [($this->img)()]], $h)->assertUnprocessable();
});

it('filters, searches without accents and sorts', function () {
    $mk = fn ($u, $title, $cat, $price) => MarketplaceListing::create(['uuid' => (string) Illuminate\Support\Str::uuid(), 'hotel_id' => $this->hotel->id, 'seller_id' => $u->id,
        'title' => $title, 'description' => 'desc', 'category' => $cat, 'condition' => 'used', 'price_cents' => $price, 'status' => 'active']);
    $mk($this->ana, 'Café expresso', 'comidas', 3000);
    $mk($this->ana, 'Violão', 'esportes', 50000);
    $mk($this->bruno, 'Cadeira gamer', 'casa', null);
    $mk($this->bruno, 'Outra empresa?', 'casa', 100)->update(['status' => 'sold']);

    Sanctum::actingAs($this->rh);
    $titles = fn (string $qs) => collect($this->getJson("{$this->base}?{$qs}")->assertOk()->json('data.items'))->pluck('title')->all();
    expect($titles('q=cafe'))->toBe(['Café expresso'])
        ->and($titles('category=casa'))->toBe(['Cadeira gamer'])
        ->and($titles('min=100&max=600'))->toBe(['Violão'])
        ->and($titles('sort=price_desc'))->toBe(['Violão', 'Café expresso', 'Cadeira gamer'])
        ->and($titles('status=sold'))->toBe(['Outra empresa?']);
});

it('keeps listings inside the company', function () {
    Sanctum::actingAs($this->ana);
    $uuid = $this->post($this->base, ['title' => 'Bicicleta aro 29', 'description' => 'Pouco usada', 'category' => 'esportes', 'condition' => 'used', 'images' => [($this->img)()]], ['Accept' => 'application/json'])->json('data.uuid');

    $otherHotel = Hotel::factory()->create();
    $outsider = tap(User::factory()->create(), fn ($u) => HotelUser::create(['hotel_id' => $otherHotel->id, 'user_id' => $u->id, 'role' => 'employee', 'status' => 'approved']));
    Sanctum::actingAs($outsider);
    $this->getJson("/api/v1/workspaces/{$otherHotel->slug}/marketplace/{$uuid}")->assertNotFound();
    $this->getJson("/api/v1/workspaces/{$this->hotel->slug}/marketplace")->assertForbidden();
});

it('lets only the seller edit and mark as sold; moderators hide and remove with audit', function () {
    Sanctum::actingAs($this->ana);
    $uuid = $this->post($this->base, ['title' => 'Bicicleta aro 29', 'description' => 'Pouco usada', 'category' => 'esportes', 'condition' => 'used', 'images' => [($this->img)()]], ['Accept' => 'application/json'])->json('data.uuid');

    $this->patchJson("{$this->base}/{$uuid}", ['price' => 700, 'status' => 'sold'])->assertOk()->assertJsonPath('data.price_cents', 70000)->assertJsonPath('data.status', 'sold');

    Sanctum::actingAs($this->bruno);
    $this->patchJson("{$this->base}/{$uuid}", ['title' => 'Roubei'])->assertForbidden();
    $this->deleteJson("{$this->base}/{$uuid}")->assertForbidden();

    Sanctum::actingAs($this->rh);
    $this->patchJson("{$this->base}/{$uuid}", ['status' => 'hidden', 'title' => 'Reescrito'])->assertOk()->assertJsonPath('data.status', 'hidden')->assertJsonPath('data.title', 'Bicicleta aro 29');
    Sanctum::actingAs($this->bruno);
    $this->getJson("{$this->base}/{$uuid}")->assertNotFound();
    Sanctum::actingAs($this->rh);
    $this->deleteJson("{$this->base}/{$uuid}")->assertOk();
    expect(AuditLog::whereIn('action', ['marketplace.moderated', 'marketplace.removed'])->count())->toBe(2);
});

it('likes (toggle with counter), reposts once to the feed and notifies the seller', function () {
    Sanctum::actingAs($this->ana);
    $uuid = $this->post($this->base, ['title' => 'Violão', 'description' => 'Violão de nylon', 'category' => 'esportes', 'condition' => 'used', 'images' => [($this->img)()]], ['Accept' => 'application/json'])->json('data.uuid');

    Sanctum::actingAs($this->bruno);
    $this->postJson("{$this->base}/{$uuid}/like")->assertOk()->assertJsonPath('data.liked', true)->assertJsonPath('data.likes_count', 1);
    expect($this->getJson("{$this->base}?scope=liked")->json('data.items'))->toHaveCount(1);
    $this->postJson("{$this->base}/{$uuid}/like")->assertOk()->assertJsonPath('data.liked', false)->assertJsonPath('data.likes_count', 0);

    $this->postJson("{$this->base}/{$uuid}/repost", ['comment' => 'Olha que legal!'])->assertCreated()->assertJsonPath('data.reposts_count', 1);
    $this->postJson("{$this->base}/{$uuid}/repost")->assertUnprocessable();

    $feed = $this->getJson("/api/v1/workspaces/{$this->hotel->slug}/feed")->assertOk()->json('data.items');
    $post = collect($feed)->firstWhere('kind', 'listing');
    expect($post['body'])->toBe('Olha que legal!')->and($post['listing']['title'])->toBe('Violão')->and($post['can_delete'])->toBeTrue();
});

it('exposes marketplace capabilities', function () {
    Sanctum::actingAs($this->ana);
    $caps = $this->getJson("/api/v1/workspaces/{$this->hotel->slug}")->json('data.me.permissions');
    expect($caps)->toContain('marketplace.view', 'marketplace.sell')->not->toContain('marketplace.moderate');
    Sanctum::actingAs($this->rh);
    expect($this->getJson("/api/v1/workspaces/{$this->hotel->slug}")->json('data.me.permissions'))->toContain('marketplace.moderate');
});

it('sends a marketplace card in the private chat only between members of the company', function () {
    Sanctum::actingAs($this->ana);
    $uuid = $this->post($this->base, ['title' => 'Violão', 'description' => 'Violão de nylon', 'category' => 'esportes', 'condition' => 'used', 'price' => 200, 'images' => [($this->img)()]], ['Accept' => 'application/json'])->json('data.uuid');

    Sanctum::actingAs($this->bruno);
    $r = $this->postJson("/api/v1/chat/{$this->ana->uuid}/messages", ['type' => 'listing', 'metadata' => ['listing_uuid' => $uuid], 'content' => 'Tenho interesse!'])->assertCreated();
    expect($r->json('data.type'))->toBe('listing')->and($r->json('data.metadata.title'))->toBe('Violão')->and($r->json('data.metadata.price_cents'))->toBe(20000)->and($r->json('data.content'))->toBe('Tenho interesse!');

    $stranger = User::factory()->create();
    $this->postJson("/api/v1/chat/{$stranger->uuid}/messages", ['type' => 'listing', 'metadata' => ['listing_uuid' => $uuid]])->assertUnprocessable();
});
