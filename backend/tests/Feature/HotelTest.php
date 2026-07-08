<?php

/**
 * Hotel endpoint tests.
 * Validates: Requirements 3.1, 3.2, 3.3, 3.6, 3.7, 3.8
 */

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────────────
// POST /api/v1/hotels
// ──────────────────────────────────────────────────────────────

it('creates a hotel and returns 201 with standard envelope', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/hotels', [
        'name'        => 'Hotel do Sol',
        'description' => 'Um hotel incrível para hospedagem.',
        'city'        => 'Rio de Janeiro',
        'state'       => 'RJ',
        'country'     => 'Brasil',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['uuid', 'name', 'slug'],
        ])
        ->assertJson(['success' => true]);
});

it('returns 401 when creating hotel without authentication', function () {
    $response = $this->postJson('/api/v1/hotels', [
        'name' => 'Hotel Sem Auth',
    ]);

    $response->assertStatus(401)->assertJson(['success' => false]);
});

it('returns 422 when creating hotel with missing name', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/hotels', []);

    $response->assertStatus(422)->assertJson(['success' => false]);
});

// ──────────────────────────────────────────────────────────────
// GET /api/v1/hotels/{slug}  (public)
// ──────────────────────────────────────────────────────────────

it('returns hotel profile by slug with standard envelope', function () {
    $hotel = Hotel::factory()->create();

    $response = $this->getJson("/api/v1/hotels/{$hotel->slug}");

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonPath('data.slug', $hotel->slug);
});

it('returns 404 for non-existent hotel slug', function () {
    $response = $this->getJson('/api/v1/hotels/nao-existe-mesmo');

    $response->assertStatus(404)
        ->assertJson(['success' => false])
        ->assertJsonStructure(['success', 'message', 'errors']);
});

// ──────────────────────────────────────────────────────────────
// PUT /api/v1/hotels/{slug}
// ──────────────────────────────────────────────────────────────

it('hotel admin can update hotel details', function () {
    $user  = User::factory()->create();
    $hotel = Hotel::factory()->create();
    $hotel->hotelUsers()->create(['user_id' => $user->id, 'role' => 'admin']);

    Sanctum::actingAs($user);

    $response = $this->putJson("/api/v1/hotels/{$hotel->slug}", [
        'name' => 'Novo Nome do Hotel',
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true, 'data' => ['name' => 'Novo Nome do Hotel']]);
});

it('non-admin user gets 403 when trying to update hotel', function () {
    $user  = User::factory()->create();
    $hotel = Hotel::factory()->create();
    // user is NOT linked as admin

    Sanctum::actingAs($user);

    $response = $this->putJson("/api/v1/hotels/{$hotel->slug}", [
        'name' => 'Tentativa de mudança',
    ]);

    $response->assertStatus(403)
        ->assertJson(['success' => false])
        ->assertJsonStructure(['success', 'message', 'errors']);
});

// ──────────────────────────────────────────────────────────────
// GET /api/v1/me/hotels
// ──────────────────────────────────────────────────────────────

it('returns paginated list of hotels managed by the authenticated user', function () {
    $user  = User::factory()->create();
    $hotel = Hotel::factory()->create();
    $hotel->hotelUsers()->create(['user_id' => $user->id, 'role' => 'admin']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/me/hotels');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            'links',
        ])
        ->assertJson(['success' => true]);
});

it('returns 401 on GET /me/hotels without token', function () {
    $response = $this->getJson('/api/v1/me/hotels');

    $response->assertStatus(401)->assertJson(['success' => false]);
});

// ──────────────────────────────────────────────────────────────
// Property 7: Criação de hotel vincula HotelAdmin
// Validates: Requirements 3.1, 3.8
// ──────────────────────────────────────────────────────────────

it('satisfies Property 7: hotel creator is linked as admin', function () {
    // Feature: hotel-connect, Property 7: Criação de hotel vincula HotelAdmin
    // Validates: Requirements 3.1, 3.8
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $name = 'Hotel Propriedade ' . rand(1, 999);

    $create = $this->postJson('/api/v1/hotels', [
        'name'        => $name,
        'description' => 'Descrição do hotel de propriedade.',
        'city'        => 'São Paulo',
        'state'       => 'SP',
        'country'     => 'Brasil',
    ]);

    $create->assertStatus(201)->assertJson(['success' => true]);

    $slug = $create->json('data.slug');

    // Fetch the hotel — admins should be loaded
    $show = $this->getJson("/api/v1/hotels/{$slug}");
    $show->assertStatus(200);

    // Creator should be in admins list
    $admins = $show->json('data.admins') ?? [];
    $adminUuids = collect($admins)->pluck('uuid');

    expect($adminUuids)->toContain($user->uuid);
})->repeat(3);

// ──────────────────────────────────────────────────────────────
// Property 8: Unicidade de slug de hotel
// Validates: Requirements 3.6
// ──────────────────────────────────────────────────────────────

it('satisfies Property 8: two hotels with the same name get distinct slugs', function () {
    // Feature: hotel-connect, Property 8: Unicidade de slug de hotel
    // Validates: Requirements 3.6
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $name = 'Hotel Duplicado';

    $first = $this->postJson('/api/v1/hotels', [
        'name'        => $name,
        'description' => 'Primeiro hotel com este nome.',
    ]);
    $first->assertStatus(201);

    $second = $this->postJson('/api/v1/hotels', [
        'name'        => $name,
        'description' => 'Segundo hotel com este nome.',
    ]);
    $second->assertStatus(201);

    $slug1 = $first->json('data.slug');
    $slug2 = $second->json('data.slug');

    expect($slug1)->not->toBe($slug2);
});

// ──────────────────────────────────────────────────────────────
// Property 9: Não-autorizado recebe 403
// Validates: Requirements 3.7
// ──────────────────────────────────────────────────────────────

it('satisfies Property 9: unauthenticated non-admin gets 403 on hotel update', function () {
    // Feature: hotel-connect, Property 9: Autorização — não-autorizado recebe 403
    // Validates: Requirements 3.7
    $owner  = User::factory()->create();
    $other  = User::factory()->create();
    $hotel  = Hotel::factory()->create();
    $hotel->hotelUsers()->create(['user_id' => $owner->id, 'role' => 'admin']);

    Sanctum::actingAs($other);

    $response = $this->putJson("/api/v1/hotels/{$hotel->slug}", [
        'name' => 'Alteração Não Autorizada',
    ]);

    $response->assertStatus(403)->assertJson(['success' => false]);
})->repeat(3);
