<?php

/**
 * User profile endpoint tests.
 * Validates: Requirements 2.1, 2.2, 2.5, 2.6
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────────────
// GET /api/v1/me
// ──────────────────────────────────────────────────────────────

it('returns authenticated user profile with standard envelope', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/me');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['uuid', 'name', 'email'],
        ])
        ->assertJson(['success' => true]);
});

it('returns 401 on GET /me without authentication', function () {
    $response = $this->getJson('/api/v1/me');

    $response->assertStatus(401)
        ->assertJson(['success' => false]);
});

// ──────────────────────────────────────────────────────────────
// PUT /api/v1/me
// ──────────────────────────────────────────────────────────────

it('updates user profile and returns updated data', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->putJson('/api/v1/me', [
        'name' => 'Novo Nome',
        'bio'  => 'Sou hoteleiro profissional.',
        'city' => 'São Paulo',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data'    => ['name' => 'Novo Nome'],
        ]);
});

// ──────────────────────────────────────────────────────────────
// GET /api/v1/users/{uuid}  (public)
// ──────────────────────────────────────────────────────────────

it('returns public user profile by uuid', function () {
    $user = User::factory()->create();

    $response = $this->getJson("/api/v1/users/{$user->uuid}");

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonPath('data.uuid', $user->uuid);
});

it('returns 404 for non-existent user uuid', function () {
    $response = $this->getJson('/api/v1/users/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404)
        ->assertJson(['success' => false])
        ->assertJsonStructure(['success', 'message', 'errors']);
});

// ──────────────────────────────────────────────────────────────
// POST /api/v1/me/experiences
// ──────────────────────────────────────────────────────────────

it('adds an experience to the authenticated user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/me/experiences', [
        'title'      => 'Gerente de Recepção',
        'company'    => 'Hotel Paraíso',
        'started_at' => '2020-01-01',
    ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true])
        ->assertJsonStructure([
            'data' => ['title', 'company', 'started_at'],
        ]);
});

it('returns 422 when adding experience with missing required fields', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/me/experiences', []);

    $response->assertStatus(422)->assertJson(['success' => false]);
});

// ──────────────────────────────────────────────────────────────
// PUT /api/v1/me/experiences/{id}
// ──────────────────────────────────────────────────────────────

it('updates an existing experience', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Add first
    $create = $this->postJson('/api/v1/me/experiences', [
        'title'      => 'Recepcionista',
        'company'    => 'Hotel ABC',
        'started_at' => '2019-06-01',
    ]);
    $experienceId = $user->experiences()->first()->id;

    $response = $this->putJson("/api/v1/me/experiences/{$experienceId}", [
        'title' => 'Gerente',
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true, 'data' => ['title' => 'Gerente']]);
});

// ──────────────────────────────────────────────────────────────
// DELETE /api/v1/me/experiences/{id}
// ──────────────────────────────────────────────────────────────

it('deletes an experience and returns success', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/me/experiences', [
        'title'      => 'Cargo X',
        'company'    => 'Empresa Y',
        'started_at' => '2021-01-01',
    ]);
    $experience = $user->experiences()->first();

    $response = $this->deleteJson("/api/v1/me/experiences/{$experience->id}");

    $response->assertStatus(200)->assertJson(['success' => true]);
    $this->assertDatabaseMissing('experiences', ['id' => $experience->id]);
});

// ──────────────────────────────────────────────────────────────
// Property 4: Atualização de perfil é consistente
// Validates: Requirements 2.2
// ──────────────────────────────────────────────────────────────

it('satisfies Property 4: profile update is consistent — data sent equals data read back', function () {
    // Feature: hotel-connect, Property 4: Atualização de perfil é consistente
    // Validates: Requirements 2.2
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $payload = [
        'name' => 'Round Trip ' . rand(1, 999),
        'bio'  => 'Bio gerada aleatoriamente ' . rand(1, 999),
        'city' => 'Campinas',
    ];

    $this->putJson('/api/v1/me', $payload)->assertStatus(200);

    $read = $this->getJson('/api/v1/me');
    $read->assertStatus(200)
        ->assertJsonPath('data.name', $payload['name'])
        ->assertJsonPath('data.bio', $payload['bio'])
        ->assertJsonPath('data.city', $payload['city']);
})->repeat(5);

// ──────────────────────────────────────────────────────────────
// Property 6: Experiências como conjunto
// Validates: Requirements 2.5, 2.6
// ──────────────────────────────────────────────────────────────

it('satisfies Property 6: experiences behave as a set — add then remove reflects correctly', function () {
    // Feature: hotel-connect, Property 6: Experiências profissionais são mantidas como conjunto
    // Validates: Requirements 2.5, 2.6
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Add two experiences
    $this->postJson('/api/v1/me/experiences', [
        'title'      => 'Cargo A',
        'company'    => 'Empresa A',
        'started_at' => '2018-01-01',
    ])->assertStatus(201);

    $this->postJson('/api/v1/me/experiences', [
        'title'      => 'Cargo B',
        'company'    => 'Empresa B',
        'started_at' => '2020-01-01',
    ])->assertStatus(201);

    // Both should be present
    $profile = $this->getJson('/api/v1/me');
    $experiences = $profile->json('data.experiences');
    expect(count($experiences))->toBe(2);

    // Remove one
    $idToRemove = $user->experiences()->first()->id;
    $this->deleteJson("/api/v1/me/experiences/{$idToRemove}")->assertStatus(200);

    // Now only one should remain
    $profile2 = $this->getJson('/api/v1/me');
    $remaining = $profile2->json('data.experiences');
    expect(count($remaining))->toBe(1);
    expect(collect($remaining)->pluck('title')->contains('Cargo A')
        || collect($remaining)->pluck('title')->contains('Cargo B'))->toBeTrue();
});
