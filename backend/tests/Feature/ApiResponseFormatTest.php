<?php

/**
 * API response format consistency tests.
 * Validates: Requirements 8.1, 8.2, 8.3
 * Property 16: Formato de resposta da API é consistente
 */

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Assert the standard API envelope is present in the response.
 */
function assertApiEnvelope(\Illuminate\Testing\TestResponse $response, bool $success): void
{
    $json = $response->json();

    expect($json)->toHaveKey('success');
    expect($json)->toHaveKey('message');
    expect($json['success'])->toBeBool();
    expect($json['message'])->toBeString();
    expect($json['success'])->toBe($success);

    if ($success) {
        expect($json)->toHaveKey('data');
    } else {
        expect($json)->toHaveKey('errors');
    }
}

// ──────────────────────────────────────────────────────────────
// Success responses
// ──────────────────────────────────────────────────────────────

it('GET /me returns success envelope', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/me');

    assertApiEnvelope($response, true);
});

it('POST /auth/login returns success envelope', function () {
    User::factory()->create(['email' => 'fmt@example.com', 'password' => 'password']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email'    => 'fmt@example.com',
        'password' => 'password',
    ]);

    assertApiEnvelope($response, true);
});

it('POST /hotels returns success envelope', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/hotels', [
        'name'        => 'Hotel Formato',
        'description' => 'Descrição do hotel para teste de formato.',
    ]);

    assertApiEnvelope($response, true);
});

it('GET /hotels/{slug} returns success envelope', function () {
    $hotel = Hotel::factory()->create();

    $response = $this->getJson("/api/v1/hotels/{$hotel->slug}");

    assertApiEnvelope($response, true);
});

it('GET /me/hotels returns paginated success envelope', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/me/hotels');

    $json = $response->json();
    expect($json['success'])->toBeTrue();
    expect($json)->toHaveKeys(['success', 'message', 'data', 'meta', 'links']);
});

// ──────────────────────────────────────────────────────────────
// Error responses
// ──────────────────────────────────────────────────────────────

it('unauthenticated request returns standard 401 error envelope', function () {
    $response = $this->getJson('/api/v1/me');

    assertApiEnvelope($response, false);
    $response->assertStatus(401);
});

it('model not found returns standard 404 error envelope', function () {
    $response = $this->getJson('/api/v1/users/00000000-0000-0000-0000-999999999999');

    assertApiEnvelope($response, false);
    $response->assertStatus(404);
});

it('validation error returns standard 422 error envelope', function () {
    $response = $this->postJson('/api/v1/auth/register', []);

    assertApiEnvelope($response, false);
    $response->assertStatus(422);
});

it('authorization error returns standard 403 error envelope', function () {
    $user  = User::factory()->create();
    $hotel = Hotel::factory()->create();
    // user is NOT admin of this hotel
    Sanctum::actingAs($user);

    $response = $this->putJson("/api/v1/hotels/{$hotel->slug}", [
        'name' => 'Sem Autorização',
    ]);

    assertApiEnvelope($response, false);
    $response->assertStatus(403);
});

// ──────────────────────────────────────────────────────────────
// Health check
// ──────────────────────────────────────────────────────────────

it('health endpoint returns success envelope', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertStatus(200)->assertJson([
        'success' => true,
        'message' => 'OK',
        'data'    => [],
    ]);
});
