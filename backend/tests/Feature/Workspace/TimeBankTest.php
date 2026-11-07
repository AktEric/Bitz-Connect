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
    // seg 2 é Finados (feriado: esperado 0, as 8h trabalhadas viram saldo), ter 3 (9h) — qua 4 e qui 5 sem marcação (−8h cada); hoje (sex 6) não conta
    bankPunchDay($this->hotel, $this->worker, '2026-11-02');
    bankPunchDay($this->hotel, $this->worker, '2026-11-03', '08:00', '17:00');
    bankPunchDay($this->hotel, $this->worker, '2026-11-06', '08:00', '10:00');

    Sanctum::actingAs($this->worker);
    $res = $this->getJson("{$this->base}/time/bank")->assertOk();
    $res->assertJsonPath('data.since', '2026-11-02')
        ->assertJsonPath('data.worked_minutes', 480 + 540)
        ->assertJsonPath('data.expected_minutes', 3 * 480)
        ->assertJsonPath('data.balance_minutes', 1020 - 1440);
});

it('does not charge days covered by approved vacation', function () {
    bankPunchDay($this->hotel, $this->worker, '2026-11-02');
    WorkspaceRequest::create([
        'uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'requester_id' => $this->worker->id,
        'type' => 'vacation', 'title' => 'Férias', 'starts_on' => '2026-11-03', 'ends_on' => '2026-11-05', 'status' => 'approved',
    ]);

    Sanctum::actingAs($this->worker);
    // 2/11 é feriado (Finados): as 8h trabalhadas viram saldo; 3 a 5/11 são férias e não geram débito
    $this->getJson("{$this->base}/time/bank")->assertOk()->assertJsonPath('data.balance_minutes', 480);
});

it('uses the person schedule for the expected hours', function () {
    $schedule = WorkSchedule::create([
        'uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => 'Meio período',
        'week_minutes' => [0 => 0, 1 => 240, 2 => 240, 3 => 240, 4 => 240, 5 => 240, 6 => 0],
    ]);
    HotelUser::where('user_id', $this->worker->id)->update(['work_schedule_id' => $schedule->id]);
    bankPunchDay($this->hotel, $this->worker, '2026-11-02', '08:00', '12:00');

    Sanctum::actingAs($this->worker);
    // seg 2/11 é feriado (esperado 0; 4h trabalhadas); ter–qui: 3 dias × 4h esperadas sem marcação → 240 − 720
    $this->getJson("{$this->base}/time/bank")->assertJsonPath('data.balance_minutes', 240 - 720);
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

it('does not expect work on national holidays, company holidays or vacations, and says which holiday it is', function () {
    \App\Models\CalendarEvent::create([
        'uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'title' => 'Aniversário da cidade', 'kind' => 'holiday',
        'starts_on' => '2026-11-04', 'created_by' => $this->rh->id,
    ]);
    WorkspaceRequest::create([
        'uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'requester_id' => $this->worker->id,
        'type' => 'vacation', 'title' => 'Férias', 'starts_on' => '2026-11-05', 'ends_on' => '2026-11-05', 'status' => 'approved',
    ]);

    Sanctum::actingAs($this->worker);
    $days = collect($this->getJson("{$this->base}/time/timesheet?month=2026-11")->assertOk()->json('data.days'))->keyBy('date');

    expect($days['2026-11-02']['expected_minutes'])->toBe(0)->and($days['2026-11-02']['holiday'])->toBe('Finados');   // nacional (segunda)
    expect($days['2026-11-03']['expected_minutes'])->toBe(480)->and($days['2026-11-03']['holiday'])->toBeNull();      // dia comum
    expect($days['2026-11-04']['expected_minutes'])->toBe(0)->and($days['2026-11-04']['holiday'])->toBe('Aniversário da cidade'); // da empresa
    expect($days['2026-11-05']['expected_minutes'])->toBe(0);                                                          // férias aprovadas
    expect($days['2026-11-06']['expected_minutes'])->toBe(480);
});

it('keeps "ponto facultativo" days as regular work days unless the company registers them', function () {
    Sanctum::actingAs($this->worker);
    $day = collect($this->getJson("{$this->base}/time/timesheet?month=2026-02")->assertOk()->json('data.days'))->firstWhere('date', '2026-02-17'); // terça de Carnaval
    expect($day['expected_minutes'])->toBe(480)->and($day['holiday'])->toBeNull();

    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/calendar/events", ['title' => 'Carnaval', 'kind' => 'holiday', 'starts_on' => '2026-02-16', 'ends_on' => '2026-02-17'])->assertCreated();

    Sanctum::actingAs($this->worker);
    $day = collect($this->getJson("{$this->base}/time/timesheet?month=2026-02")->json('data.days'))->firstWhere('date', '2026-02-17');
    expect($day['expected_minutes'])->toBe(0)->and($day['holiday'])->toBe('Carnaval');
});
