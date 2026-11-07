<?php

/** Fechamento mensal do ponto: retrato, travas, exportação e permissões. */

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\TimeAdjustment;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function closeMember(Hotel $hotel, string $role = 'employee'): User
{
    $user = User::factory()->create();
    HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'approved']);

    return $user;
}

function closePunchDay(Hotel $hotel, User $user, string $date, string $in = '08:00', string $out = '16:00'): void
{
    foreach (['in' => $in, 'out' => $out] as $type => $time) {
        TimeEntry::create([
            'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'user_id' => $user->id, 'type' => $type,
            'punched_at' => Carbon::parse("{$date} {$time}", 'America/Sao_Paulo')->utc(), 'source' => 'clock',
        ]);
    }
}

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 12:00:00'); // terça; outubro de 2026 já terminou
    $this->hotel  = Hotel::factory()->create();
    $this->rh     = closeMember($this->hotel, 'admin');
    $this->worker = closeMember($this->hotel);
    $this->base   = "/api/v1/workspaces/{$this->hotel->slug}";

    // outubro/2026: 22 dias úteis. Trabalhou 2 dias de 8h e um de 9h (ter 6, qua 7 e qui 8 → dias úteis 1..).
    closePunchDay($this->hotel, $this->worker, '2026-10-05');
    closePunchDay($this->hotel, $this->worker, '2026-10-06');
    closePunchDay($this->hotel, $this->worker, '2026-10-07', '08:00', '17:00');
});

afterEach(fn () => Carbon::setTestNow());

it('previews the month and closes it with a frozen snapshot', function () {
    Sanctum::actingAs($this->rh);
    $preview = $this->getJson("{$this->base}/time/closings/2026-10")->assertOk()->assertJsonPath('data.status', 'open');
    $row = collect($preview->json('data.rows'))->firstWhere('user_uuid', $this->worker->uuid);
    expect($row['days_worked'])->toBe(3);
    expect($row['worked_minutes'])->toBe(480 + 480 + 540);
    expect($row['expected_minutes'])->toBe(22 * 480);
    expect($row['absent_days'])->toBe(19);

    $this->postJson("{$this->base}/time/closings", ['month' => '2026-10'])->assertCreated()->assertJsonPath('data.status', 'closed');
    $this->postJson("{$this->base}/time/closings", ['month' => '2026-10'])->assertStatus(409);

    // mudanças posteriores não alteram o retrato fechado
    closePunchDay($this->hotel, $this->worker, '2026-10-08');
    $closed = $this->getJson("{$this->base}/time/closings/2026-10")->assertOk()->assertJsonPath('data.status', 'closed');
    $row = collect($closed->json('data.rows'))->firstWhere('user_uuid', $this->worker->uuid);
    expect($row['worked_minutes'])->toBe(1500);
});

it('refuses to close an unfinished month or one with pending adjustments', function () {
    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/time/closings", ['month' => '2026-11'])->assertStatus(422);

    TimeAdjustment::create([
        'uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $this->worker->id, 'type' => 'in',
        'requested_at' => Carbon::parse('2026-10-20 08:00', 'America/Sao_Paulo')->utc(), 'reason' => 'Esqueci', 'status' => 'pending',
    ]);
    $this->postJson("{$this->base}/time/closings", ['month' => '2026-10'])->assertStatus(422);
});

it('locks manual entries and approvals inside a closed month, until the owner reopens it', function () {
    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/time/closings", ['month' => '2026-10'])->assertCreated();

    $this->postJson("{$this->base}/time/entries", [
        'user_uuid' => $this->worker->uuid, 'type' => 'in', 'punched_at' => '2026-10-15 08:00', 'note' => 'Correção',
    ])->assertStatus(422);

    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/time/adjustments", ['type' => 'in', 'requested_at' => '2026-10-15 08:00', 'reason' => 'Esqueci'])->assertStatus(422);

    Sanctum::actingAs($this->rh);
    $this->deleteJson("{$this->base}/time/closings/2026-10")->assertOk();
    $this->postJson("{$this->base}/time/entries", [
        'user_uuid' => $this->worker->uuid, 'type' => 'in', 'punched_at' => '2026-10-15 08:00', 'note' => 'Correção',
    ])->assertCreated();
});

it('exports a CSV only for closed months and keeps everything to RH', function () {
    Sanctum::actingAs($this->worker);
    $this->getJson("{$this->base}/time/closings")->assertForbidden();
    $this->postJson("{$this->base}/time/closings", ['month' => '2026-10'])->assertForbidden();

    Sanctum::actingAs($this->rh);
    $this->get("{$this->base}/time/closings/2026-10/export")->assertStatus(422);
    $this->postJson("{$this->base}/time/closings", ['month' => '2026-10'])->assertCreated();

    $csv = $this->get("{$this->base}/time/closings/2026-10/export")->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
    expect($csv)->toContain('Nome;Cargo;Setor;Dias trabalhados')->toContain($this->worker->name)->toContain('25:00');

    $list = $this->getJson("{$this->base}/time/closings")->assertOk()->assertJsonCount(12, 'data');
    expect($list->json('data.0.month'))->toBe('2026-10');
    expect($list->json('data.0.status'))->toBe('closed');
});

it('lets only the owner reopen a closed month', function () {
    $hr = closeMember($this->hotel);
    HotelUser::where('user_id', $hr->id)->update(['access_role' => 'hr']);

    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/time/closings", ['month' => '2026-10'])->assertCreated();

    Sanctum::actingAs($hr);
    $this->deleteJson("{$this->base}/time/closings/2026-10")->assertForbidden();

    Sanctum::actingAs($this->rh);
    $this->deleteJson("{$this->base}/time/closings/2026-10")->assertOk();
    $this->deleteJson("{$this->base}/time/closings/2026-10")->assertNotFound();
});
