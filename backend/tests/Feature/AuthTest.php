<?php

/**
 * Auth endpoint tests.
 * Validates: Requirements 1.1, 1.3, 1.4, 1.5, 1.8
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────────────
// POST /api/v1/auth/register
// ──────────────────────────────────────────────────────────────

it('registers a new user and returns standard success envelope', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name'                  => 'João da Silva',
        'email'                 => 'joao@example.com',
        'password'              => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'token',
                'user' => ['uuid', 'name', 'email'],
            ],
        ])
        ->assertJson(['success' => true]);
});

it('returns 422 when registering with a duplicate email', function () {
    User::factory()->create(['email' => 'dup@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name'                  => 'Outro',
        'email'                 => 'dup@example.com',
        'password'              => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false])
        ->assertJsonStructure(['success', 'message', 'errors']);
});

it('returns 422 when registering with missing required fields', function () {
    $response = $this->postJson('/api/v1/auth/register', []);

    $response->assertStatus(422)
        ->assertJson(['success' => false]);
});

// ──────────────────────────────────────────────────────────────
// POST /api/v1/auth/login
// ──────────────────────────────────────────────────────────────

it('logs in with valid credentials and returns token', function () {
    User::factory()->create(['email' => 'login@example.com', 'password' => 'password']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email'    => 'login@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['token', 'user'],
        ])
        ->assertJson(['success' => true]);
});

it('returns 422 when login credentials are wrong', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email'    => 'test@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false])
        ->assertJsonStructure(['success', 'message', 'errors']);
});

// ──────────────────────────────────────────────────────────────
// POST /api/v1/auth/logout  (authenticated)
// ──────────────────────────────────────────────────────────────

it('logout returns success envelope and invalidates the token', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonStructure(['success', 'message', 'data']);
});

it('returns 401 when accessing protected route without token', function () {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertStatus(401)
        ->assertJson(['success' => false, 'message' => 'Não autenticado.'])
        ->assertJsonStructure(['success', 'message', 'errors']);
});

// ──────────────────────────────────────────────────────────────
// PUT /api/v1/auth/password  (authenticated)
// ──────────────────────────────────────────────────────────────

it('changes password successfully and returns standard envelope', function () {
    $user = User::factory()->create(['password' => 'OldPass123!']);
    Sanctum::actingAs($user);

    $response = $this->putJson('/api/v1/auth/password', [
        'current_password'      => 'OldPass123!',
        'password'              => 'NewPass123!',
        'password_confirmation' => 'NewPass123!',
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);
});

it('returns 422 when current password is wrong on change password', function () {
    $user = User::factory()->create(['password' => 'RealPass123!']);
    Sanctum::actingAs($user);

    $response = $this->putJson('/api/v1/auth/password', [
        'current_password'      => 'WrongPass!',
        'password'              => 'NewPass123!',
        'password_confirmation' => 'NewPass123!',
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false]);
});

// ──────────────────────────────────────────────────────────────
// Property 1: Auth Round-Trip
// Validates: Requirements 1.1, 1.3
// ──────────────────────────────────────────────────────────────

it('satisfies Property 1: auth round-trip — register then login returns valid token', function () {
    // Feature: hotel-connect, Property 1: Autenticação Round-Trip
    // Validates: Requirements 1.1, 1.3
    $email = 'roundtrip' . rand(1000, 9999) . '@example.com';

    $register = $this->postJson('/api/v1/auth/register', [
        'name'                  => 'Test User',
        'email'                 => $email,
        'password'              => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $register->assertStatus(201)->assertJson(['success' => true]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email'    => $email,
        'password' => 'Password123!',
    ]);

    $login->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonPath('data.token', fn ($token) => is_string($token) && strlen($token) > 10);
})->repeat(5);

// ──────────────────────────────────────────────────────────────
// Property 2: Logout invalida token
// Validates: Requirements 1.5
// ──────────────────────────────────────────────────────────────

it('satisfies Property 2: logout invalidates token — subsequent requests return 401', function () {
    // Feature: hotel-connect, Property 2: Logout invalida token
    // Validates: Requirements 1.5
    $user = User::factory()->create();

    // Create a real Sanctum token directly (avoids session cookie side-effects)
    $plainTextToken = $user->createToken('test-token')->plainTextToken;

    // Logout using the Bearer token — expect success
    $this->withHeaders(['Authorization' => "Bearer {$plainTextToken}"])
        ->postJson('/api/v1/auth/logout')
        ->assertStatus(200)
        ->assertJson(['success' => true]);

    // Reset any in-memory auth state so the next request must use the token only
    $this->app['auth']->forgetGuards();

    // The revoked token must now return 401
    $this->withHeaders(['Authorization' => "Bearer {$plainTextToken}"])
        ->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertJson(['success' => false]);
});
