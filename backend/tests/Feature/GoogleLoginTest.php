<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

function fakeGoogle(array $claims): void
{
    Http::fake(['oauth2.googleapis.com/*' => Http::response($claims + [
        'aud' => 'client-123', 'iss' => 'https://accounts.google.com', 'email_verified' => 'true',
        'exp' => (string) (time() + 600), 'name' => 'Maria Teste',
    ])]);
}

it('hides the Google button when no client id is configured', function () {
    config(['services.google.client_id' => null]);

    $this->getJson('/api/v1/auth/providers')->assertOk()->assertJsonPath('data.google_client_id', null);
    $this->postJson('/api/v1/auth/google', ['credential' => 'x'])->assertStatus(503);
});

it('creates an account and returns a token on first Google login', function () {
    config(['services.google.client_id' => 'client-123']);
    fakeGoogle(['email' => 'Maria@Empresa.com']);

    $this->postJson('/api/v1/auth/google', ['credential' => 'tok'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user' => ['uuid', 'name', 'email']]])
        ->assertJsonPath('data.user.email', 'maria@empresa.com');

    expect(User::where('email', 'maria@empresa.com')->count())->toBe(1);
});

it('logs into the existing account instead of duplicating it', function () {
    config(['services.google.client_id' => 'client-123']);
    User::factory()->create(['email' => 'maria@empresa.com']);
    fakeGoogle(['email' => 'maria@empresa.com']);

    $this->postJson('/api/v1/auth/google', ['credential' => 'tok'])->assertOk();

    expect(User::where('email', 'maria@empresa.com')->count())->toBe(1);
});

it('rejects tokens issued for another app or with unverified e-mail', function () {
    config(['services.google.client_id' => 'client-123']);

    fakeGoogle(['email' => 'a@b.com', 'aud' => 'outro-app']);
    $this->postJson('/api/v1/auth/google', ['credential' => 'tok'])->assertStatus(422);

    fakeGoogle(['email' => 'a@b.com', 'email_verified' => 'false']);
    $this->postJson('/api/v1/auth/google', ['credential' => 'tok'])->assertStatus(422);

    expect(User::where('email', 'a@b.com')->exists())->toBeFalse();
});
