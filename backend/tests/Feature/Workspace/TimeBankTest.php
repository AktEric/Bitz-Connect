<?php

/** Banco de horas: saldo acumulado, férias, lançamentos do RH e permissões. */

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkspaceRequest;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function bankMember(Hotel $hotel, string $role = 'employee', ?User $manager = null): User
{
    $user = User::factory()->create();
    HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'approved', 'manager_id' => $manager?->id]);

    return $user;
}

/** Marca um dia completo (entrada e saída) no horário de Brasília. */
function bankPunchDay(Hotel $hotel, User $user, string $date, string $in = '08:00', string $out = '16:00'): void
{
    foreach (['in' => $in, 'out' => $out] as $type => $time) {
        TimeEntry::create([
            'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'user_id' => $user->id, 'type' => $type,
            'punched_at' => Carbon::parse("{$date} {$time}", 'America/Sao_Paulo')->utc(), 'source' => 'clock',
        ]);
    }
}

beforeEach(function () {
    Carbon::setTestNow('2026-11-06 12:00:00'); // sexta-feira
    $this->hotel   = Hotel::factory()->create();
    $this->rh      = bankMember($this->hotel, 'admin');
    $this->manager = bankMember($this->hotel);
    $this->worker  = bankMember($this->hotel, 'employee', $this->manager);
    $this->other   = bankMember($this->hotel);
    $this->base    = "/api/v1/workspaces/{$this->hotel->slug}";
});

afterEach(fn () => Carbon::setTestNow());

it('has no balance before the person ever punches', function () {
    Sanctum::actingAs($this->worker);
    $this->getJson("{$this->base}/time/bank")->assertOk()
        ->assertJsonPath('data.since', null)->assertJsonPath('data.balance_minutes', 0);
});

it('accumulates worked minus expected over closed days only', function () {
    // seg 2 (8h, ok) e ter 3 (9h) — qua 4 e qui 5 sem marcação (−8h cada); hoje (sex 6) não conta
    bankPunchDay($this->hotel, $this->worker, '2026-11-02');
    bankPunchDay($this->hotel, $this->worker, '2026-11-03', '08:00', '17:00');
    bankPunchDay($this->hotel, $this->worker, '2026-11-06', '08:00', '10:00');

    Sanctum::actingAs($this->worker);
    $res = $this->getJson("{$this->base}/time/bank")->assertOk();
    $res->assertJsonPath('data.since', '2026-11-02')
        ->assertJsonPath('data.worked_minutes', 480 + 540)
        ->assertJsonPath('data.expected_minutes', 4 * 480)
        ->assertJsonPath('data.balance_minutes', 1020 - 1920);
});

it('does not charge days covered by approved vacation', function () {
    bankPunchDay($this->hotel, $this->worker, '2026-11-02');
    WorkspaceRequest::create([
        'uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'requester_id' => $this->worker->id,
        'type' => 'vacation', 'title' => 'Férias', 'starts_on' => '2026-11-03', 'ends_on' => '2026-11-05', 'status' => 'approved',
    ]);

    Sanctum::actingAs($this->worker);
    $this->getJson("{$this->base}/time/bank")->assertOk()->assertJsonPath('data.balance_minutes', 0);
});

it('uses the person schedule for the expected hours', function () {
    $schedule = WorkSchedule::create([
        'uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => 'Meio período',
        'week_minutes' => [0 => 0, 1 => 240, 2 => 240, 3 => 240, 4 => 240, 5 => 240, 6 => 0],
    ]);
    HotelUser::where('user_id', $this->worker->id)->update(['work_schedule_id' => $schedule->id]);
    bankPunchDay($this->hotel, $this->worker, '2026-11-02', '08:00', '12:00');

    Sanctum::actingAs($this->worker);
    // seg–qui: 4 dias × 4h esperadas; trabalhou só segunda (4h) → −12h
    $this->getJson("{$this->base}/time/bank")->assertJsonPath('data.balance_minutes', 240 - 960);
});

it('lets only RH post manual bank entries, with a reason, and notifies the person', function () {
    Sanctum::actingAs($this->manager);
    $this->postJson("{$this->base}/time/bank/adjustments", ['user_uuid' => $this->worker->uuid, 'minutes' => 60, 'reason' => 'x'])->assertForbidden();

    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/time/bank/adjustments", ['user_uuid' => $this->worker->uuid, 'minutes' => 60])->assertStatus(422);
    $this->postJson("{$this->base}/time/bank/adjustments", ['user_uuid' => $this->worker->uuid, 'minutes' => 0, 'reason' => 'x'])->assertStatus(422);
    $this->postJson("{$this->base}/time/bank/adjustments", ['user_uuid' => $this->worker->uuid, 'minutes' => 120, 'reason' => 'Hora extra do evento'])
        ->assertCreated()->assertJsonPath('data.balance_minutes', 120);
    $this->postJson("{$this->base}/time/bank/adjustments", ['user_uuid' => $this->worker->uuid, 'minutes' => -60, 'reason' => 'Folga compensada'])
        ->assertCreated()->assertJsonPath('data.balance_minutes', 60);

    Sanctum::actingAs($this->worker);
    $res = $this->getJson("{$this->base}/time/bank")->assertOk()->assertJsonPath('data.balance_minutes', 60);
    expect($res->json('data.adjustments'))->toHaveCount(2);
});

it('limits who can see someone else bank and the team overview', function () {
    Sanctum::actingAs($this->other);
    $this->getJson("{$this->base}/time/bank?user={$this->worker->uuid}")->assertForbidden();
    $this->getJson("{$this->base}/time/bank/team")->assertForbidden();

    Sanctum::actingAs($this->manager);
    $this->getJson("{$this->base}/time/bank?user={$this->worker->uuid}")->assertOk();
    $this->getJson("{$this->base}/time/bank/team")->assertForbidden();

    Sanctum::actingAs($this->rh);
    $this->getJson("{$this->base}/time/bank/team")->assertOk()->assertJsonCount(4, 'data');
});
