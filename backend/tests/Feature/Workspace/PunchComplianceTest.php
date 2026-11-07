<?php

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->hotel   = Hotel::factory()->create();
    $this->manager = User::factory()->create();
    $this->worker  = User::factory()->create();
    HotelUser::create(['hotel_id' => $this->hotel->id, 'user_id' => $this->manager->id, 'role' => 'employee', 'status' => 'approved']);
    HotelUser::create(['hotel_id' => $this->hotel->id, 'user_id' => $this->worker->id, 'role' => 'employee', 'status' => 'approved', 'manager_id' => $this->manager->id]);
    $this->base = "/api/v1/workspaces/{$this->hotel->slug}";
});
afterEach(fn () => Carbon::setTestNow());

function cPunch($t, string $at, ?string $type = null)
{
    Carbon::setTestNow($at); // UTC; Brasília = -3h
    Sanctum::actingAs($t->worker);
    $t->postJson("{$t->base}/time/punch", $type ? ["type" => $type] : [])->assertCreated();
}
function alerts($t) { return Notification::where('user_id', $t->manager->id)->where('type', 'workspace_punch_alert')->get(); }

it('warns the manager about a lunch break that is too short', function () {
    cPunch($this, '2026-11-10 11:00:00'); // entrada 08:00
    cPunch($this, '2026-11-10 15:00:00'); // início do intervalo
    cPunch($this, '2026-11-10 15:20:00'); // volta com 20 min
    expect(alerts($this))->toHaveCount(1)->and(alerts($this)->first()->description)->toContain('20 min');
});

it('does not warn for a normal day', function () {
    cPunch($this, '2026-11-10 11:00:00');
    cPunch($this, '2026-11-10 15:00:00');
    cPunch($this, '2026-11-10 16:00:00');
    cPunch($this, '2026-11-10 20:00:00', 'out'); // 8h exatas
    expect(alerts($this))->toHaveCount(0);
});

it('warns about overtime beyond the standard day and the 2h legal limit', function () {
    cPunch($this, '2026-11-10 11:00:00');
    cPunch($this, '2026-11-10 15:00:00');
    cPunch($this, '2026-11-10 16:00:00');
    cPunch($this, '2026-11-10 23:30:00', 'out'); // 11h30 trabalhadas => 3h30 extras
    $text = alerts($this)->first()->description;
    expect($text)->toContain('extra de 3h30')->and($text)->toContain('2h extras');
});

it('flags open shifts that run over the day through the scheduled command, once', function () {
    cPunch($this, '2026-11-10 11:00:00');
    Carbon::setTestNow('2026-11-10 22:30:00'); // 11h30 abertas
    $this->artisan('ponto:alert-overtime')->assertSuccessful();
    $this->artisan('ponto:alert-overtime')->assertSuccessful();
    expect(alerts($this))->toHaveCount(1);
});

it('requires a justification to decide a time adjustment', function () {
    Carbon::setTestNow('2026-11-10 15:00:00');
    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/time/adjustments", ['type' => 'in', 'requested_at' => '2026-11-10 08:00', 'reason' => 'Esqueci de bater'])->assertCreated();
    $uuid = \App\Models\TimeAdjustment::value('uuid');
    Sanctum::actingAs($this->manager);
    $this->postJson("{$this->base}/time/adjustments/{$uuid}/decision", ['decision' => 'approved'])->assertUnprocessable();
    $this->postJson("{$this->base}/time/adjustments/{$uuid}/decision", ['decision' => 'approved', 'note' => 'Confirmado com a equipe'])->assertOk();
});
