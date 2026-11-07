<?php

/** Escalas de ponto: jornada por pessoa no espelho e no relógio. */

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

function schedMember(Hotel $hotel, string $role = 'employee'): User
{
    $user = User::factory()->create();
    HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'approved']);

    return $user;
}

beforeEach(function () {
    Carbon::setTestNow('2026-11-06 12:00:00'); // sexta-feira
    $this->hotel  = Hotel::factory()->create();
    $this->rh     = schedMember($this->hotel, 'admin');
    $this->worker = schedMember($this->hotel);
    $this->base   = "/api/v1/workspaces/{$this->hotel->slug}";
});

afterEach(fn () => Carbon::setTestNow());

it('uses the default 8h Monday-to-Friday journey when nobody has a schedule', function () {
    Sanctum::actingAs($this->worker);
    $this->getJson("{$this->base}/time/today")->assertOk()->assertJsonPath('data.daily_minutes', 480);
    $this->getJson("{$this->base}/time/timesheet?month=2026-11")->assertOk()->assertJsonPath('data.expected_minutes', 2400);
});

it('applies the assigned schedule to the clock and the timesheet', function () {
    Sanctum::actingAs($this->rh);
    $uuid = $this->postJson("{$this->base}/schedules", [
        'name' => 'Escala 6x1', 'week_minutes' => [0 => 0, 1 => 360, 2 => 360, 3 => 360, 4 => 360, 5 => 360, 6 => 360],
    ])->assertCreated()->json('data.uuid');
    $this->patchJson("{$this->base}/schedules/assign", ['user_uuid' => $this->worker->uuid, 'schedule_uuid' => $uuid])->assertOk();

    Sanctum::actingAs($this->worker);
    $this->getJson("{$this->base}/time/today")->assertJsonPath('data.daily_minutes', 360);
    // 2 a 6 de novembro: 5 dias de 6h (1º/11 é domingo e o sábado ainda não chegou)
    $this->getJson("{$this->base}/time/timesheet?month=2026-11")->assertJsonPath('data.expected_minutes', 1800);

    // removendo a jornada, a pessoa volta ao padrão
    Sanctum::actingAs($this->rh);
    $this->deleteJson("{$this->base}/schedules/{$uuid}")->assertOk();
    Sanctum::actingAs($this->worker);
    $this->getJson("{$this->base}/time/today")->assertJsonPath('data.daily_minutes', 480);
});

it('validates schedules and keeps them private to RH', function () {
    Sanctum::actingAs($this->worker);
    $this->getJson("{$this->base}/schedules")->assertForbidden();
    $this->postJson("{$this->base}/schedules", ['name' => 'X', 'week_minutes' => [1 => 480]])->assertForbidden();

    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/schedules", ['name' => 'Ruim', 'week_minutes' => [1 => 5000]])->assertStatus(422);
    $this->postJson("{$this->base}/schedules", ['name' => 'Manhã', 'week_minutes' => [1 => 240]])->assertCreated();
    $this->postJson("{$this->base}/schedules", ['name' => 'Manhã', 'week_minutes' => [1 => 240]])->assertStatus(422);

    $res = $this->getJson("{$this->base}/schedules")->assertOk();
    expect($res->json('data.schedules'))->toHaveCount(1);
    expect($res->json('data.members'))->toHaveCount(2);
});
