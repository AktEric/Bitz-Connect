<?php

/** Segurança da conta: política de senha, verificação em duas etapas (TOTP), sessões e exigência por empresa. */

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use App\Support\Totp;

const SEC_PASSWORD = 'Senha-Forte-123';

function secUser(array $attrs = []): User
{
    return User::factory()->create($attrs + ['password' => SEC_PASSWORD]);
}

function secLogin($test, User $user, array $extra = [])
{
    app('auth')->forgetGuards();

    return $test->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36'])
        ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => SEC_PASSWORD] + $extra);
}

/** Chama a API como o token informado (sessão real), esquecendo o usuário já resolvido. */
function secAs($test, string $token)
{
    app('auth')->forgetGuards();

    return $test->withToken($token);
}

/** Ativa o 2FA de $user e devolve [segredo, códigos de recuperação]. */
function secEnable2fa($test, User $user): array
{
    $token = secLogin($test, $user)->assertOk()->json('data.token');
    $secret = secAs($test, $token)->postJson('/api/v1/auth/2fa/setup')->assertOk()->json('data.secret');
    $codes = secAs($test, $token)->postJson('/api/v1/auth/2fa/enable', ['code' => Totp::code($secret, Totp::step())])->assertOk()->json('data.recovery_codes');

    return [$secret, $codes, $token];
}

it('matches the RFC 6238 test vectors', function () {
    $secret = Totp::base32Encode('12345678901234567890');
    expect(Totp::code($secret, Totp::step(59)))->toBe('287082')
        ->and(Totp::code($secret, Totp::step(1111111109)))->toBe('081804')
        ->and(Totp::code($secret, Totp::step(20000000000)))->toBe('353130');
    expect(Totp::verify($secret, '287082', 1, 59))->toBe(1)->and(Totp::verify($secret, '000000', 1, 59))->toBeNull();
    expect(Totp::base32Decode(Totp::base32Encode('segredo-teste')))->toBe('segredo-teste');
});

it('enforces the password policy with readable messages', function () {
    $base = ['name' => 'Ana', 'email' => 'ana@example.com'];

    foreach (['curta1A', 'tudominusculo1', 'TUDOMAIUSCULO1', 'SemNumeroNenhum'] as $weak) {
        $res = $this->postJson('/api/v1/auth/register', $base + ['password' => $weak, 'password_confirmation' => $weak])->assertStatus(422);
        expect($res->json('errors.password.0'))->toBeString()->not->toContain('validation.');
    }
    $this->postJson('/api/v1/auth/register', $base + ['password' => SEC_PASSWORD, 'password_confirmation' => SEC_PASSWORD])->assertCreated();
});

it('turns two-factor on only after a valid code and shows the recovery codes once', function () {
    $user  = secUser();
    $token = secLogin($this, $user)->assertOk()->json('data.token');

    $setup = secAs($this, $token)->postJson('/api/v1/auth/2fa/setup')->assertOk();
    expect($setup->json('data.uri'))->toStartWith('otpauth://totp/')->toContain('secret=' . $setup->json('data.secret'));
    expect($user->fresh()->hasTwoFactor())->toBeFalse();

    secAs($this, $token)->postJson('/api/v1/auth/2fa/enable', ['code' => '000000'])->assertStatus(422);
    $codes = secAs($this, $token)->postJson('/api/v1/auth/2fa/enable', ['code' => Totp::code($setup->json('data.secret'), Totp::step())])
        ->assertOk()->json('data.recovery_codes');

    expect($codes)->toHaveCount(10)->and($user->fresh()->hasTwoFactor())->toBeTrue();
    // o segredo nunca volta nas respostas
    $overview = secAs($this, $token)->getJson('/api/v1/auth/security')->assertOk();
    expect($overview->getContent())->not->toContain($setup->json('data.secret'))->and($overview->json('data.two_factor.recovery_left'))->toBe(10);
});

it('asks for the second step at login, rejects bad and replayed codes, and burns recovery codes', function () {
    $user = secUser();
    [$secret, $codes] = secEnable2fa($this, $user);

    secLogin($this, $user)->assertStatus(422)->assertJsonStructure(['errors' => ['two_factor_required']]);
    secLogin($this, $user, ['code' => '123456'])->assertStatus(422)->assertJsonStructure(['errors' => ['code']]);

    // o código do passo seguinte vale uma vez; repetir o mesmo é recusado (anti-replay)
    $next = Totp::code($secret, Totp::step() + 1);
    secLogin($this, $user, ['code' => $next])->assertOk()->assertJsonStructure(['data' => ['token']]);
    secLogin($this, $user, ['code' => $next])->assertStatus(422);

    // código de recuperação: funciona uma vez
    secLogin($this, $user, ['recovery_code' => $codes[0]])->assertOk();
    secLogin($this, $user, ['recovery_code' => $codes[0]])->assertStatus(422);
    $token = secLogin($this, $user, ['recovery_code' => $codes[1]])->assertOk()->json('data.token');
    expect(secAs($this, $token)->getJson('/api/v1/auth/security')->json('data.two_factor.recovery_left'))->toBe(8);
});

it('requires password and a valid code to disable or regenerate', function () {
    $user = secUser();
    [$secret, $codes, $token] = secEnable2fa($this, $user);
    $code = fn () => Totp::code($secret, Totp::step() + 1);

    secAs($this, $token)->postJson('/api/v1/auth/2fa/disable', ['password' => 'errada', 'code' => $code()])->assertStatus(422);
    secAs($this, $token)->postJson('/api/v1/auth/2fa/disable', ['password' => SEC_PASSWORD, 'code' => '000000'])->assertStatus(422);

    $new = secAs($this, $token)->postJson('/api/v1/auth/2fa/recovery-codes', ['password' => SEC_PASSWORD, 'recovery_code' => $codes[2]])->assertOk()->json('data.recovery_codes');
    expect($new)->toHaveCount(10)->and(array_intersect($new, $codes))->toBeEmpty();

    secAs($this, $token)->postJson('/api/v1/auth/2fa/disable', ['password' => SEC_PASSWORD, 'recovery_code' => $new[0]])->assertOk();
    expect($user->fresh()->hasTwoFactor())->toBeFalse();
    secLogin($this, $user)->assertOk();
});

it('lists sessions with device and IP and lets the user end the others', function () {
    $user = secUser();
    $t1 = secLogin($this, $user)->assertOk()->json('data.token');
    $t2 = secLogin($this, $user)->assertOk()->json('data.token');

    $list = secAs($this, $t2)->getJson('/api/v1/auth/security')->assertOk()->json('data.sessions');
    expect($list)->toHaveCount(2)->and(collect($list)->where('current', true))->toHaveCount(1);
    expect($list[0]['label'])->toBe('Chrome 130 · Windows 10/11 · desktop')->and($list[0]['ip'])->not->toBeNull();

    $other = collect($list)->firstWhere('current', false);
    secAs($this, $t2)->deleteJson("/api/v1/auth/sessions/{$other['id']}")->assertOk();
    secAs($this, $t1)->getJson('/api/v1/auth/security')->assertUnauthorized(); // a sessão encerrada deixa de valer

    $t3 = secLogin($this, $user)->assertOk()->json('data.token');
    secAs($this, $t3)->postJson('/api/v1/auth/sessions/revoke-others')->assertOk()->assertJsonPath('data.revoked', 1);
    expect(secAs($this, $t3)->getJson('/api/v1/auth/security')->json('data.sessions'))->toHaveCount(1);
});

it('lets the owner require two-factor for privileged profiles and blocks them until they enable it', function () {
    $hotel = Hotel::factory()->create();
    $owner = secUser();
    $emp   = secUser();
    HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $owner->id, 'role' => 'admin', 'status' => 'approved']);
    HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $emp->id, 'role' => 'employee', 'status' => 'approved']);
    $base = "/api/v1/workspaces/{$hotel->slug}";

    $ownerToken = secLogin($this, $owner)->assertOk()->json('data.token');
    $empToken   = secLogin($this, $emp)->assertOk()->json('data.token');

    secAs($this, $empToken)->putJson("{$base}/security", ['require_2fa' => true])->assertForbidden();
    secAs($this, $ownerToken)->putJson("{$base}/security", ['require_2fa' => true])->assertOk();

    // dono sem 2FA: abre o Workspace (com o aviso) e a política, mas o resto é bloqueado
    $show = secAs($this, $ownerToken)->getJson($base)->assertOk();
    expect($show->json('data.me.needs_two_factor'))->toBeTrue();
    secAs($this, $ownerToken)->getJson("{$base}/security")->assertOk()->assertJsonPath('data.privileged_without_2fa.0.uuid', $owner->uuid);
    secAs($this, $ownerToken)->getJson("{$base}/feed")->assertForbidden()->assertJsonPath('code', 'two_factor_required');

    // colaborador comum não é afetado
    secAs($this, $empToken)->getJson("{$base}/feed")->assertOk();

    // depois de ativar, o acesso volta
    $secret = secAs($this, $ownerToken)->postJson('/api/v1/auth/2fa/setup')->json('data.secret');
    secAs($this, $ownerToken)->postJson('/api/v1/auth/2fa/enable', ['code' => Totp::code($secret, Totp::step())])->assertOk();
    secAs($this, $ownerToken)->getJson("{$base}/feed")->assertOk();
    expect(secAs($this, $ownerToken)->getJson($base)->json('data.me.needs_two_factor'))->toBeFalse();
});
