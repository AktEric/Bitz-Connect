<?php

/** Dispositivo no ponto: navegador/aparelho, dispositivos cadastrados com aprovação, redes autorizadas e IP. */

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\Notification;
use App\Models\TimeDevice;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\DeviceInfo;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

const DEV_CHROME_WIN = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';
const DEV_KEY_A = 'chave-do-pc-recepcao-0001';
const DEV_KEY_B = 'chave-do-celular-do-ze-002';

function devMember(Hotel $hotel, string $role = 'employee', ?User $manager = null): User
{
    $user = User::factory()->create();
    HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'approved', 'manager_id' => $manager?->id]);

    return $user;
}

beforeEach(function () {
    Carbon::setTestNow('2026-11-06 12:00:00');
    $this->hotel   = Hotel::factory()->create();
    $this->rh      = devMember($this->hotel, 'admin');
    $this->manager = devMember($this->hotel);
    $this->worker  = devMember($this->hotel, 'employee', $this->manager);
    $this->other   = devMember($this->hotel);
    $this->base    = "/api/v1/workspaces/{$this->hotel->slug}";
});

afterEach(fn () => Carbon::setTestNow());

function devSettings($test, array $extra): void
{
    Sanctum::actingAs($test->rh);
    $test->putJson("{$test->base}/time/settings", array_merge(['geo' => 'off', 'photo' => 'off'], $extra))->assertOk();
}

/** Marca como o colaborador, com User-Agent e chave de dispositivo opcional. */
function devPunch($test, User $user, ?string $key = null, array $body = [], string $ua = DEV_CHROME_WIN)
{
    Sanctum::actingAs($user);
    $headers = ['User-Agent' => $ua] + ($key ? ['X-Device-Key' => $key] : []);

    return $test->withHeaders($headers)->postJson("{$test->base}/time/punch", $body);
}

it('reads browser, system and device type from the user agent', function () {
    expect(DeviceInfo::parse(DEV_CHROME_WIN)['label'])->toBe('Chrome 130 · Windows 10/11 · desktop');
    expect(DeviceInfo::parse('Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1')['label'])
        ->toBe('Safari 17 · iOS 17 · mobile');
    expect(DeviceInfo::parse('Mozilla/5.0 (Linux; Android 14; SM-X700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36')['label'])
        ->toBe('Chrome 129 · Android 14 · tablet');
    expect(DeviceInfo::parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.2849.5')['browser'])->toBe('Edge 130');
    expect(DeviceInfo::parse(null)['label'])->toBe('Navegador desconhecido · Sistema desconhecido · desktop');
});

it('always records browser, system and IP on the punch, even with devices off', function () {
    devPunch($this, $this->worker)->assertCreated();

    $e = TimeEntry::where('user_id', $this->worker->id)->first();
    expect($e->device_label)->toBe('Chrome 130 · Windows 10/11 · desktop')->and($e->ip)->not->toBeNull()->and($e->device_id)->toBeNull();

    // quem tem permissão enxerga IP e aparelho no espelho
    Sanctum::actingAs($this->manager);
    $day = collect($this->getJson("{$this->base}/time/timesheet?user={$this->worker->uuid}&month=2026-11")->assertOk()->json('data.days'))->firstWhere('date', '2026-11-06');
    expect($day['entries'][0]['device_label'])->toBe('Chrome 130 · Windows 10/11 · desktop')->and($day['entries'][0]['ip'])->not->toBeNull();
});

it('registers devices automatically in log mode and flags only the first punch', function () {
    devSettings($this, ['devices' => 'log']);

    devPunch($this, $this->worker, DEV_KEY_A, ['device_name' => 'Recepção — PC 01'])->assertCreated();
    $first = TimeEntry::where('user_id', $this->worker->id)->first();
    expect($first->new_device)->toBeTrue()->and($first->device_name)->toBe('Recepção — PC 01');
    expect(TimeDevice::where('hotel_id', $this->hotel->id)->first()->status)->toBe('approved');

    Carbon::setTestNow('2026-11-06 13:00:00');
    devPunch($this, $this->worker, DEV_KEY_A)->assertCreated();
    expect(TimeEntry::where('user_id', $this->worker->id)->orderByDesc('id')->first()->new_device)->toBeFalse();
    expect(TimeDevice::count())->toBe(1);
});

it('requires RH approval of unknown devices in required mode and tells the admins', function () {
    devSettings($this, ['devices' => 'required']);

    devPunch($this, $this->worker)->assertStatus(422)->assertJsonPath('code', 'device_key_missing');
    devPunch($this, $this->worker, DEV_KEY_A)->assertStatus(422)->assertJsonPath('code', 'device_name_required');
    devPunch($this, $this->worker, DEV_KEY_A, ['device_name' => 'Recepção — PC 01'])->assertStatus(422)->assertJsonPath('code', 'device_pending');
    expect(TimeEntry::count())->toBe(0);
    expect(Notification::where('user_id', $this->rh->id)->where('type', 'workspace_device_pending')->count())->toBe(1);
    devPunch($this, $this->worker, DEV_KEY_A)->assertStatus(422)->assertJsonPath('code', 'device_pending');

    $uuid = TimeDevice::first()->uuid;
    Sanctum::actingAs($this->worker);
    $this->getJson("{$this->base}/time/device", ['X-Device-Key' => DEV_KEY_A])->assertOk()->assertJsonPath('data.status', 'pending');
    $this->patchJson("{$this->base}/time/devices/{$uuid}", ['status' => 'approved'])->assertForbidden();

    Sanctum::actingAs($this->rh);
    $this->patchJson("{$this->base}/time/devices/{$uuid}", ['status' => 'approved'])->assertOk();

    // aprovado: outra pessoa também pode usar o mesmo computador compartilhado
    devPunch($this, $this->worker, DEV_KEY_A)->assertCreated();
    devPunch($this, $this->other, DEV_KEY_A)->assertCreated();
    $e = TimeEntry::where('user_id', $this->worker->id)->first();
    expect($e->device_name)->toBe('Recepção — PC 01')->and($e->device_id)->toBe(TimeDevice::first()->id);
    Sanctum::actingAs($this->rh);
    expect($this->getJson("{$this->base}/time/devices")->json('data.0.users_count'))->toBe(2);

    // bloqueado: nenhum usuário marca por ele
    $this->patchJson("{$this->base}/time/devices/{$uuid}", ['status' => 'blocked'])->assertOk();
    Carbon::setTestNow('2026-11-06 14:00:00');
    devPunch($this, $this->worker, DEV_KEY_A)->assertStatus(422)->assertJsonPath('code', 'device_blocked');
});

it('keeps old punches readable after a device is removed', function () {
    devSettings($this, ['devices' => 'log']);
    devPunch($this, $this->worker, DEV_KEY_B, ['device_name' => 'Celular do Zé'])->assertCreated();

    Sanctum::actingAs($this->rh);
    $uuid = TimeDevice::first()->uuid;
    $this->deleteJson("{$this->base}/time/devices/{$uuid}")->assertOk();
    $e = TimeEntry::first();
    expect($e->device_id)->toBeNull()->and($e->device_name)->toBe('Celular do Zé');
});

it('flags or blocks punches outside the authorized networks', function () {
    // o IP do cliente nos testes é 127.0.0.1
    devSettings($this, ['networks' => [['name' => 'Matriz — Wi-Fi', 'cidr' => '127.0.0.0/8']]]);
    devPunch($this, $this->worker)->assertCreated();
    $e = TimeEntry::first();
    expect($e->network_name)->toBe('Matriz — Wi-Fi')->and($e->outside_network)->toBeFalse();

    devSettings($this, ['networks' => [['name' => 'Filial', 'cidr' => '10.0.0.0/8']]]);
    Carbon::setTestNow('2026-11-06 13:00:00');
    devPunch($this, $this->worker)->assertCreated();
    expect(TimeEntry::orderByDesc('id')->first()->outside_network)->toBeTrue()->and(TimeEntry::orderByDesc('id')->first()->network_name)->toBeNull();

    devSettings($this, ['networks' => [['name' => 'Filial', 'cidr' => '10.0.0.0/8']], 'networks_block' => true]);
    Carbon::setTestNow('2026-11-06 14:00:00');
    devPunch($this, $this->worker, null)->assertStatus(422)->assertJsonPath('code', 'network_outside');
});

it('validates network and device settings and keeps them to RH', function () {
    Sanctum::actingAs($this->worker);
    $this->putJson("{$this->base}/time/settings", ['geo' => 'off', 'photo' => 'off', 'devices' => 'required'])->assertForbidden();
    $this->getJson("{$this->base}/time/devices")->assertForbidden();

    Sanctum::actingAs($this->rh);
    $this->putJson("{$this->base}/time/settings", ['geo' => 'off', 'photo' => 'off', 'devices' => 'bogus'])->assertStatus(422);
    $this->putJson("{$this->base}/time/settings", ['geo' => 'off', 'photo' => 'off', 'networks' => [['name' => 'X', 'cidr' => 'nao-e-ip']]])->assertStatus(422);
    $this->putJson("{$this->base}/time/settings", ['geo' => 'off', 'photo' => 'off', 'networks' => [['name' => 'X', 'cidr' => '10.0.0.0/33']]])->assertStatus(422);
    $this->putJson("{$this->base}/time/settings", ['geo' => 'off', 'photo' => 'off', 'networks' => [['name' => 'Matriz', 'cidr' => '200.150.10.0/24'], ['name' => 'Casa do dono', 'cidr' => '2001:db8::/32']]])->assertOk();

    $res = $this->getJson("{$this->base}/time/settings")->assertOk();
    expect($res->json('data.networks'))->toHaveCount(2)->and($res->json('data.current_ip'))->not->toBeNull();

    // salvar só geo/photo não apaga redes e dispositivos já configurados
    $this->putJson("{$this->base}/time/settings", ['geo' => 'optional', 'photo' => 'off'])->assertOk();
    expect($this->getJson("{$this->base}/time/settings")->json('data.networks'))->toHaveCount(2);

    // o colaborador só descobre se há exigência, não os endereços das redes
    Sanctum::actingAs($this->worker);
    $req = $this->getJson("{$this->base}/time/today")->assertOk()->json('data.requirements');
    expect($req['networks'])->toBeTrue()->and(json_encode($req))->not->toContain('200.150');
});
