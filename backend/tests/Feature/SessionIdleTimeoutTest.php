<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function tokenFor(User $user, string $lastUsed): string
{
    $new = $user->createToken('api');
    $new->accessToken->forceFill(['last_used_at' => now()->modify($lastUsed)])->save();

    return $new->plainTextToken;
}

it('mantém a sessão quando o token foi usado há menos de 2 horas', function () {
    $user = User::factory()->create();
    $token = tokenFor($user, '-119 minutes');

    $this->withToken($token)->getJson('/api/v1/me')->assertOk();
});

it('derruba a sessão após mais de 2 horas sem uso', function () {
    $user = User::factory()->create();
    $token = tokenFor($user, '-121 minutes');

    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

it('renova o prazo a cada requisição (sessão deslizante)', function () {
    $user = User::factory()->create();
    $token = tokenFor($user, '-100 minutes');

    $this->withToken($token)->getJson('/api/v1/me')->assertOk();

    $this->travel(100)->minutes();
    // 100 min depois da última requisição ainda está dentro das 2h.
    $this->withToken($token)->getJson('/api/v1/me')->assertOk();
});
