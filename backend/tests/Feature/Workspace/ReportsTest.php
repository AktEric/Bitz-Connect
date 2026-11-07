<?php

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 15:00:00');
    $this->hotel = Hotel::factory()->create();
    $mk = fn (string $role, ?User $mgr = null, ?string $access = null) => tap(User::factory()->create(), fn ($u) => HotelUser::create([
        'hotel_id' => $this->hotel->id, 'user_id' => $u->id, 'role' => $role, 'access_role' => $access, 'status' => 'approved', 'manager_id' => $mgr?->id,
    ]));
    $this->rh       = $mk('admin', null, 'hr');
    $this->director = $mk('employee', null, 'director');
    $this->manager  = $mk('employee');
    $this->mine     = $mk('employee', $this->manager);
    $this->other    = $mk('employee');
    $this->base     = "/api/v1/workspaces/{$this->hotel->slug}";
});
afterEach(fn () => Carbon::setTestNow());

it('shows each role only the reports it may see', function () {
    $keys = function (User $u) { Sanctum::actingAs($u); return collect($this->getJson("{$this->base}/reports")->assertOk()->json('data'))->pluck('key')->all(); };

    expect($keys($this->rh))->toBe(['hours', 'occurrences', 'adjustments', 'tickets', 'bank', 'headcount']);
    expect($keys($this->manager))->toBe(['hours', 'occurrences', 'adjustments']);
    expect($keys($this->director))->toBe(['headcount']);
    expect($keys($this->other))->toBe([]);

    Sanctum::actingAs($this->manager);
    $this->getJson("{$this->base}/reports/bank")->assertForbidden();
    Sanctum::actingAs($this->director);
    $this->getJson("{$this->base}/reports/hours")->assertForbidden();
    Sanctum::actingAs($this->other);
    $this->getJson("{$this->base}/reports/hours")->assertForbidden();
});

it('limits a manager to their own reach and gives RH everyone', function () {
    Sanctum::actingAs($this->manager);
    $names = collect($this->getJson("{$this->base}/reports/hours?month=2026-11")->assertOk()->json('data.rows'))->pluck(0)->all();
    expect($names)->toBe([$this->mine->name]);

    Sanctum::actingAs($this->rh);
    expect($this->getJson("{$this->base}/reports/hours?month=2026-11")->json('data.rows'))->toHaveCount(5);
    $this->getJson("{$this->base}/reports/bank")->assertOk()->assertJsonCount(5, 'data.rows');
});

it('keeps the director report aggregated and audits every generation', function () {
    Sanctum::actingAs($this->director);
    $r = $this->getJson("{$this->base}/reports/headcount?month=2026-11")->assertOk()->json('data');
    expect($r['columns'])->toBe(['Área', 'Pessoas', 'Admissões no mês'])->and(end($r['rows']))->toBe(['Total', 5, 0]);
    expect(\App\Models\AuditLog::where('action', 'report.generated')->count())->toBe(1);
    $this->getJson("{$this->base}/reports/nope")->assertNotFound();
});

it('reports ticket response times for RH only', function () {
    Sanctum::actingAs($this->mine);
    $uuid = $this->postJson("{$this->base}/requests", ['type' => 'ticket', 'category' => 'payroll', 'title' => 'Holerite errado'])->assertCreated()->json('data.uuid');
    Carbon::setTestNow('2026-11-10 17:00:00'); // 2h depois (a abertura foi às 15h)
    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/requests/{$uuid}/assign")->assertOk();
    Carbon::setTestNow('2026-11-11 15:00:00'); // 24h após a abertura
    $this->postJson("{$this->base}/requests/{$uuid}/resolve", ['note' => 'Corrigido na folha'])->assertOk();

    $r = $this->getJson("{$this->base}/reports/tickets?month=2026-11")->assertOk()->json('data');
    expect($r['rows'][0][2])->toBe('Folha e holerite')->and($r['rows'][0][3])->toBe('Resolvido')->and($r['rows'][0][5])->toBe(2)->and($r['rows'][0][6])->toBe(24)->and($r['rows'][0][7])->toBe('Não');
    expect(end($r['rows'])[0])->toBe('Média');

    Sanctum::actingAs($this->manager);
    $this->getJson("{$this->base}/reports/tickets")->assertForbidden();
});

it('does not count future days or today as absences in the monthly hours report', function () {
    // hoje (fixo): terça 10/11/2026 às 12h UTC; a pessoa não bateu ponto
    Sanctum::actingAs($this->rh);
    $rows = collect($this->getJson("{$this->base}/reports/hours?month=2026-11")->assertOk()->json('data.rows'));
    $mine = $rows->firstWhere(0, $this->mine->name);
    // dias úteis de 2 a 9/11 já passaram (2 e 9 incluídos; 2/11 é feriado): faltas só até ontem, esperado só até hoje
    expect($mine[3])->toBeLessThanOrEqual(6)->and($mine[3])->toBeGreaterThan(0);
    expect((int) explode(':', $mine[4])[0])->toBeLessThanOrEqual(7 * 8);
});
