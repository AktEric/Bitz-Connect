<?php

/**
 * Checkpoint: Property 15 and Property 16 tests.
 *
 * Property 15: Endpoint protegido rejeita não autenticado
 * Property 16: Formato de resposta da API é consistente
 *
 * Validates: Requirements 7.2, 8.1, 8.2, 8.3, 8.4
 */

use App\Models\Hotel;
use App\Models\Job;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────────────
// Property 15: Endpoint protegido rejeita não autenticado
// Validates: Requirements 7.2
//
// For any protected endpoint, a request without a token must
// return HTTP 401, regardless of the method or resource.
// ──────────────────────────────────────────────────────────────

/**
 * Returns a representative sample of protected (auth:sanctum) endpoints
 * covering all domains: Auth, User, Hotel, Feed, Job, Search.
 * Each entry is [method, uri] with placeholder UUIDs where needed.
 */
function protectedEndpoints(): array
{
    return [
        // Auth domain
        ['POST',   '/api/v1/auth/logout'],
        ['PUT',    '/api/v1/auth/password'],

        // User domain
        ['GET',    '/api/v1/me'],
        ['PUT',    '/api/v1/me'],
        ['POST',   '/api/v1/me/avatar'],
        ['POST',   '/api/v1/me/banner'],
        ['POST',   '/api/v1/me/experiences'],
        ['PUT',    '/api/v1/me/experiences/1'],
        ['DELETE', '/api/v1/me/experiences/1'],

        // Hotel domain
        ['POST',   '/api/v1/hotels'],
        ['PUT',    '/api/v1/hotels/some-hotel-slug'],
        ['POST',   '/api/v1/hotels/some-hotel-slug/logo'],
        ['POST',   '/api/v1/hotels/some-hotel-slug/cover'],
        ['GET',    '/api/v1/me/hotels'],

        // Feed domain
        ['GET',    '/api/v1/feed'],
        ['POST',   '/api/v1/posts'],
        ['GET',    '/api/v1/posts/00000000-0000-0000-0000-000000000001'],
        ['PUT',    '/api/v1/posts/00000000-0000-0000-0000-000000000001'],
        ['DELETE', '/api/v1/posts/00000000-0000-0000-0000-000000000001'],
        ['POST',   '/api/v1/posts/00000000-0000-0000-0000-000000000001/like'],
        ['DELETE', '/api/v1/posts/00000000-0000-0000-0000-000000000001/like'],
        ['POST',   '/api/v1/posts/00000000-0000-0000-0000-000000000001/comments'],

        // Job domain (authenticated actions)
        ['POST',   '/api/v1/jobs'],
        ['PUT',    '/api/v1/jobs/00000000-0000-0000-0000-000000000002'],
        ['PATCH',  '/api/v1/jobs/00000000-0000-0000-0000-000000000002/status'],
        ['POST',   '/api/v1/jobs/00000000-0000-0000-0000-000000000002/apply'],
        ['GET',    '/api/v1/jobs/00000000-0000-0000-0000-000000000002/applications'],
        ['GET',    '/api/v1/me/applications'],

        // Search domain
        ['GET',    '/api/v1/search/hotels?q=test'],
        ['GET',    '/api/v1/search/jobs?q=test'],
        ['GET',    '/api/v1/search/users?q=test'],
    ];
}

it('satisfies Property 15: every protected endpoint returns 401 when no token is sent', function () {
    // Feature: hotel-connect, Property 15: Endpoint protegido rejeita não autenticado
    // Validates: Requirements 7.2

    foreach (protectedEndpoints() as [$method, $uri]) {
        $response = match ($method) {
            'GET'    => $this->getJson($uri),
            'POST'   => $this->postJson($uri),
            'PUT'    => $this->putJson($uri),
            'PATCH'  => $this->patchJson($uri),
            'DELETE' => $this->deleteJson($uri),
        };

        $status = $response->getStatusCode();

        expect($status)->toBe(
            401,
            "Expected 401 for {$method} {$uri}, got {$status}"
        );

        $json = $response->json();
        expect($json)->toHaveKey('success', false);
        expect($json)->toHaveKey('message');
        expect($json)->toHaveKey('errors');
    }
})->repeat(3);

it('satisfies Property 15: an invalid/expired token also yields 401 on protected endpoints', function () {
    // Feature: hotel-connect, Property 15: Endpoint protegido rejeita não autenticado
    // Validates: Requirements 7.2

    // Sanity-check a few key endpoints with a garbage Bearer token
    $endpoints = [
        ['GET',  '/api/v1/me'],
        ['GET',  '/api/v1/feed'],
        ['POST', '/api/v1/posts'],
        ['GET',  '/api/v1/me/applications'],
        ['GET',  '/api/v1/search/users?q=test'],
    ];

    foreach ($endpoints as [$method, $uri]) {
        $response = match ($method) {
            'GET'  => $this->withHeader('Authorization', 'Bearer invalid-token-xyz')->getJson($uri),
            'POST' => $this->withHeader('Authorization', 'Bearer invalid-token-xyz')->postJson($uri),
        };

        $response->assertStatus(401)->assertJson(['success' => false]);
    }
})->repeat(3);

// ──────────────────────────────────────────────────────────────
// Property 16: Formato de resposta da API é consistente
// Validates: Requirements 8.1, 8.2, 8.3, 8.4
//
// For any API response (success or error), the JSON body must
// contain: success (bool), message (string), and data OR errors.
// ──────────────────────────────────────────────────────────────

/**
 * Asserts the standard API envelope shape.
 */
function assertEnvelope(\Illuminate\Testing\TestResponse $response, bool $isSuccess): void
{
    $json = $response->json();

    expect($json)->toBeArray();
    expect($json)->toHaveKey('success');
    expect($json)->toHaveKey('message');
    expect($json['success'])->toBeBool();
    expect($json['success'])->toBe($isSuccess);
    expect($json['message'])->toBeString();

    if ($isSuccess) {
        // Success responses must have 'data'
        expect($json)->toHaveKey('data');
    } else {
        // Error responses must have 'errors'
        expect($json)->toHaveKey('errors');
    }
}

/**
 * Assert UUIDs are used as public identifiers (not integer IDs).
 */
function assertUsesUuids(array $data): void
{
    if (isset($data['id'])) {
        // If 'id' is exposed it should not be the raw integer primary key
        // (the spec requires UUIDs as public identifiers — Req 8.4)
        expect($data)->toHaveKey('uuid');
    }
}

it('satisfies Property 16: success responses follow the standard envelope', function () {
    // Feature: hotel-connect, Property 16: Formato de resposta da API é consistente
    // Validates: Requirements 8.1, 8.3, 8.4
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $successEndpoints = [
        ['GET',  '/api/v1/me'],
        ['GET',  '/api/v1/me/hotels'],
        ['GET',  '/api/v1/health'],
    ];

    foreach ($successEndpoints as [$method, $uri]) {
        $response = match ($method) {
            'GET' => $this->getJson($uri),
        };

        assertEnvelope($response, true);
    }
})->repeat(3);

it('satisfies Property 16: 401 error responses follow the standard envelope', function () {
    // Feature: hotel-connect, Property 16: Formato de resposta da API é consistente
    // Validates: Requirements 8.2, 8.3

    $unauthEndpoints = [
        ['GET',  '/api/v1/me'],
        ['GET',  '/api/v1/feed'],
        ['POST', '/api/v1/posts'],
        ['GET',  '/api/v1/me/hotels'],
        ['GET',  '/api/v1/search/jobs?q=test'],
    ];

    foreach ($unauthEndpoints as [$method, $uri]) {
        $response = match ($method) {
            'GET'  => $this->getJson($uri),
            'POST' => $this->postJson($uri),
        };

        assertEnvelope($response, false);
        $response->assertStatus(401);
    }
})->repeat(3);

it('satisfies Property 16: 422 validation error responses follow the standard envelope', function () {
    // Feature: hotel-connect, Property 16: Formato de resposta da API é consistente
    // Validates: Requirements 8.2, 8.3

    // POST /auth/register with empty body → validation error
    $response = $this->postJson('/api/v1/auth/register', []);

    assertEnvelope($response, false);
    $response->assertStatus(422);

    // The errors field must be an array with at least one entry
    $errors = $response->json('errors');
    expect($errors)->toBeArray();
    expect(count($errors))->toBeGreaterThan(0);
})->repeat(3);

it('satisfies Property 16: 404 error responses follow the standard envelope', function () {
    // Feature: hotel-connect, Property 16: Formato de resposta da API é consistente
    // Validates: Requirements 8.2, 8.3

    $response = $this->getJson('/api/v1/users/00000000-0000-0000-0000-999999999999');

    assertEnvelope($response, false);
    $response->assertStatus(404);
})->repeat(3);

it('satisfies Property 16: 403 error responses follow the standard envelope', function () {
    // Feature: hotel-connect, Property 16: Formato de resposta da API é consistente
    // Validates: Requirements 8.2, 8.3

    $user  = User::factory()->create();
    $hotel = Hotel::factory()->create();
    // user is NOT admin of this hotel
    Sanctum::actingAs($user);

    $response = $this->putJson("/api/v1/hotels/{$hotel->slug}", ['name' => 'Unauthorized']);

    assertEnvelope($response, false);
    $response->assertStatus(403);
})->repeat(3);

it('satisfies Property 16: successful entity creation returns UUID as identifier — Req 8.4', function () {
    // Feature: hotel-connect, Property 16: Formato de resposta da API é consistente
    // Validates: Requirements 8.4
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Register response must expose uuid on the user object
    $registerResponse = $this->postJson('/api/v1/auth/register', [
        'name'                  => 'UUID Test User ' . rand(1, 9999),
        'email'                 => 'uuid' . rand(1, 9999) . '@example.com',
        'password'              => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $registerResponse->assertStatus(201);

    $userData = $registerResponse->json('data.user');
    expect($userData)->toHaveKey('uuid');
    // UUID must not be an integer
    expect($userData['uuid'])->toBeString();
    // UUID must look like a UUID (36 chars with dashes)
    expect(strlen($userData['uuid']))->toBe(36);
    // Should NOT expose raw integer 'id'
    expect($userData)->not->toHaveKey('id');

    // Hotel response must expose uuid
    $hotelResponse = $this->postJson('/api/v1/hotels', [
        'name'        => 'Hotel UUID Test ' . rand(1, 9999),
        'description' => 'Test hotel for UUID validation.',
    ]);
    $hotelResponse->assertStatus(201);

    $hotelData = $hotelResponse->json('data');
    expect($hotelData)->toHaveKey('uuid');
    expect($hotelData['uuid'])->toBeString();
    expect(strlen($hotelData['uuid']))->toBe(36);
    expect($hotelData)->not->toHaveKey('id');
})->repeat(3);

it('satisfies Property 16: paginated success responses follow extended envelope with meta and links', function () {
    // Feature: hotel-connect, Property 16: Formato de resposta da API é consistente
    // Validates: Requirements 8.1, 8.3
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/me/hotels');

    $response->assertStatus(200);
    $json = $response->json();

    expect($json['success'])->toBeTrue();
    expect($json['message'])->toBeString();
    expect($json)->toHaveKey('data');
    expect($json)->toHaveKey('meta');
    expect($json)->toHaveKey('links');
    expect($json['meta'])->toHaveKeys(['current_page', 'last_page', 'per_page', 'total']);
})->repeat(3);
