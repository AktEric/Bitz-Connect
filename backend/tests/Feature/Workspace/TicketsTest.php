<?php

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\Notification;
use App\Models\User;
use App\Models\WorkspaceRequest;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 12:00:00');
    $this->hotel = Hotel::factory()->create();
    $mk = fn (string $role, ?User $mgr = null, ?string $access = null) => tap(User::factory()->create(), fn ($u) => HotelUser::create(
        ['hotel_id' => $this->hotel->id, 'user_id' => $u->id, 'role' => $role, 'access_role' => $access, 'status' => 'approved', 'manager_id' => $mgr?->id]));
    $this->rh      = $mk('admin', null, 'hr');
    $this->manager = $mk('employee');
    $this->worker  = $mk('employee', $this->manager);
    $this->base    = "/api/v1/workspaces/{$this->hotel->slug}";
});
afterEach(fn () => Carbon::setTestNow());

function openTicket($t): string
{
    Sanctum::actingAs($t->worker);

    return $t->postJson("{$t->base}/requests", ['type' => 'ticket', 'category' => 'payroll', 'title' => 'Desconto errado no holerite', 'description' => 'Veja o desconto de VT'])
        ->assertCreated()->json('data.uuid');
}

it('sends a ticket straight to RH with a 48h deadline, hidden from the manager', function () {
    $uuid = openTicket($this);
    $r = WorkspaceRequest::where('uuid', $uuid)->first();
    expect($r->category)->toBe('payroll')->and($r->due_at->toIso8601String())->toBe(Carbon::now()->addHours(48)->toIso8601String());
    expect(Notification::where('user_id', $this->rh->id)->where('title', 'like', 'Novo chamado%')->count())->toBe(1);
    expect(Notification::where('user_id', $this->manager->id)->count())->toBe(0);

    Sanctum::actingAs($this->manager);
    $this->getJson("{$this->base}/requests/{$uuid}")->assertNotFound();
    expect($this->getJson("{$this->base}/requests?scope=review")->json('data.items'))->toBe([]);

    Sanctum::actingAs($this->rh);
    expect($this->getJson("{$this->base}/requests?scope=review")->json('data.items'))->toHaveCount(1);

    // categoria é obrigatória num chamado
    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/requests", ['type' => 'ticket', 'title' => 'Sem categoria'])->assertUnprocessable();
});

it('runs the flow: assume, converse, resolve with a required justification', function () {
    $uuid = openTicket($this);

    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/requests/{$uuid}/assign")->assertNotFound();      // só o RH assume
    $this->postJson("{$this->base}/requests/{$uuid}/decision", ['decision' => 'approved'])->assertStatus(422);

    Carbon::setTestNow('2026-11-10 15:00:00');
    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/requests/{$uuid}/assign")->assertOk()->assertJsonPath('data.status', 'in_progress')->assertJsonPath('data.assigned_to.name', $this->rh->name);
    expect(WorkspaceRequest::where('uuid', $uuid)->first()->first_response_at)->not->toBeNull();

    $this->postJson("{$this->base}/requests/{$uuid}/resolve", [])->assertUnprocessable();
    $this->postJson("{$this->base}/requests/{$uuid}/resolve", ['note' => 'Ajustado na folha de novembro'])->assertOk()->assertJsonPath('data.status', 'resolved');
    $this->postJson("{$this->base}/requests/{$uuid}/resolve", ['note' => 'De novo, de novo'])->assertStatus(409);

    expect(AuditLog::where('action', 'ticket.resolved')->count())->toBe(1);
    expect(Notification::where('user_id', $this->worker->id)->where('title', 'Chamado resolvido')->exists())->toBeTrue();
});

it('marks an unanswered ticket as overdue after the deadline', function () {
    $uuid = openTicket($this);
    Sanctum::actingAs($this->rh);
    expect($this->getJson("{$this->base}/requests/{$uuid}")->json('data.overdue'))->toBeFalse();
    Carbon::setTestNow('2026-11-13 12:00:00');
    expect($this->getJson("{$this->base}/requests/{$uuid}")->json('data.overdue'))->toBeTrue();
});

it('keeps tickets out of the manager global search', function () {
    openTicket($this);
    Sanctum::actingAs($this->manager);
    expect($this->getJson("{$this->base}/search?q=holerite")->assertOk()->json('data.requests'))->toBe([]);
    Sanctum::actingAs($this->rh);
    expect($this->getJson("{$this->base}/search?q=holerite")->json('data.requests'))->toHaveCount(1);
});
