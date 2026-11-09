<?php

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->hotel = Hotel::factory()->create();
    $mk = fn (string $role, array $extra = []) => tap(User::factory()->create(), fn ($u) => HotelUser::create(
        ['hotel_id' => $this->hotel->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'approved'] + $extra));
    $this->rh     = $mk('admin', ['access_role' => 'hr']);
    $this->worker = $mk('employee');
    $this->other  = $mk('employee');
    $this->base   = "/api/v1/workspaces/{$this->hotel->slug}";
});

it('lets HR write a salary but never reads it back, and stores it encrypted', function () {
    Sanctum::actingAs($this->rh);
    $r = $this->putJson("{$this->base}/people/{$this->worker->uuid}/salary", ['salary' => 8500.5])->assertOk();
    expect($r->json('data'))->toBe(['configured' => true]);
    expect(json_encode($r->json()))->not->toContain('8500');

    $raw = DB::table('hotel_users')->where('user_id', $this->worker->id)->value('salary');
    expect($raw)->not->toContain('8500');
    expect(AuditLog::where('action', 'salary.updated')->first()->meta ?? [])->toBeEmpty();
});

it('shows the salary only to the owner of it and only through reveal', function () {
    Sanctum::actingAs($this->rh);
    $this->putJson("{$this->base}/people/{$this->worker->uuid}/salary", ['salary' => 4200])->assertOk();

    Sanctum::actingAs($this->worker);
    // status e perfil não trazem o valor
    $this->getJson("{$this->base}/me/salary")->assertOk()->assertJsonPath('data.configured', true)->assertJsonMissingPath('data.salary');
    expect(json_encode($this->getJson("{$this->base}/me/profile")->json()))->not->toContain('4200');
    expect(json_encode($this->getJson("{$this->base}/people/{$this->worker->uuid}/record")->json()))->not->toContain('4200');

    $r = $this->postJson("{$this->base}/me/salary/reveal")->assertOk();
    expect($r->json('data.salary'))->toEqual(4200)->and($r->headers->get('Cache-Control'))->toContain('no-store');

    // outro colega e o RH enxergam o PRÓPRIO salário (vazio), nunca o do Gabriel
    foreach ([$this->other, $this->rh] as $u) {
        Sanctum::actingAs($u);
        expect($this->postJson("{$this->base}/me/salary/reveal")->json('data.salary'))->toBeNull();
    }
});

it('blocks non-HR from setting salaries', function () {
    Sanctum::actingAs($this->worker);
    $this->putJson("{$this->base}/people/{$this->other->uuid}/salary", ['salary' => 1])->assertForbidden();
    Sanctum::actingAs($this->rh);
    $this->putJson("{$this->base}/people/{$this->other->uuid}/salary", ['salary' => -5])->assertUnprocessable();
});
