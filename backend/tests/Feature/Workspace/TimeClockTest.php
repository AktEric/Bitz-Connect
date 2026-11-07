<?php

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->hotel = Hotel::factory()->create();
    $mk = function (string $role, $manager = null) {
        $u = User::factory()->create();
        HotelUser::create(['hotel_id' => $this->hotel->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'approved', 'manager_id' => $manager?->id]);

        return $u;
    };
    $this->rh      = $mk('admin');
    $this->manager = $mk('employee');
    $this->worker  = $mk('employee', $this->manager);
    $this->other   = $mk('employee');
    $this->base    = "/api/v1/workspaces/{$this->hotel->slug}/time";
});

it('walks the day: in, break, back, out and rejects invalid steps', function () {
    Sanctum::actingAs($this->worker);

    $this->getJson("{$this->base}/today")->assertOk()->assertJsonPath('data.state', 'out');
    $this->postJson("{$this->base}/punch", ['type' => 'out'])->assertStatus(422);

    $this->postJson("{$this->base}/punch")->assertCreated()->assertJsonPath('data.state', 'working');
    // Marcação repetida em menos de 15s é barrada.
    $this->postJson("{$this->base}/punch", ['type' => 'break_start'])->assertStatus(429);
});

it('computes worked minutes ignoring the break', function () {
    $clock = app(\App\Domain\Workspace\Services\TimeClock::class);
    $d = CarbonImmutable::parse('2026-11-02 08:00', 'UTC');
    $mk = fn ($type, $t) => new TimeEntry(['type' => $type, 'punched_at' => $d->setTimeFromTimeString($t)]);
    $entries = collect([$mk('in', '08:00'), $mk('break_start', '12:00'), $mk('break_end', '13:00'), $mk('out', '17:00')]);

    expect($clock->workedMinutes($entries))->toBe(480)
        ->and($clock->state($entries))->toBe('out');
});

it('limits the timesheet and the team view by role', function () {
    Sanctum::actingAs($this->other);
    $this->getJson("{$this->base}/timesheet?user={$this->worker->uuid}")->assertForbidden();
    $this->getJson("{$this->base}/team")->assertForbidden();

    Sanctum::actingAs($this->manager);
    $this->getJson("{$this->base}/timesheet?user={$this->worker->uuid}")->assertOk();
    $this->getJson("{$this->base}/team")->assertOk()->assertJsonCount(1, 'data');

    Sanctum::actingAs($this->rh);
    $this->getJson("{$this->base}/team")->assertOk()->assertJsonCount(4, 'data');
});

it('requires a note and permission for manual entries', function () {
    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/entries", [
        'user_uuid' => $this->worker->uuid, 'type' => 'in', 'punched_at' => now()->subHour()->toDateTimeString(), 'note' => 'esqueci',
    ])->assertForbidden();

    Sanctum::actingAs($this->manager);
    $this->postJson("{$this->base}/entries", [
        'user_uuid' => $this->worker->uuid, 'type' => 'in', 'punched_at' => now()->subHour()->toDateTimeString(),
    ])->assertStatus(422);
    $this->postJson("{$this->base}/entries", [
        'user_uuid' => $this->worker->uuid, 'type' => 'in', 'punched_at' => now()->subHour()->toDateTimeString(), 'note' => 'esqueceu de bater',
    ])->assertCreated();
});

it('lets a worker request an adjustment and only the manager approves it into a manual entry', function () {
    Sanctum::actingAs($this->worker);
    $uuid = $this->postJson("{$this->base}/adjustments", [
        'type' => 'in', 'requested_at' => now()->subHours(3)->toDateTimeString(), 'reason' => 'Esqueci de bater',
    ])->assertCreated()->json('data.uuid');

    // O próprio colaborador e um estranho ao time não decidem.
    $this->postJson("{$this->base}/adjustments/{$uuid}/decision", ['decision' => 'approved'])->assertForbidden();
    Sanctum::actingAs($this->other);
    $this->postJson("{$this->base}/adjustments/{$uuid}/decision", ['decision' => 'approved'])->assertForbidden();
    $this->getJson("{$this->base}/adjustments?scope=review")->assertForbidden();

    Sanctum::actingAs($this->manager);
    $this->getJson("{$this->base}/adjustments?scope=review")->assertOk()->assertJsonCount(1, 'data');
    $this->postJson("{$this->base}/adjustments/{$uuid}/decision", ['decision' => 'approved', 'note' => 'Conferido com o gestor'])
        ->assertOk()->assertJsonPath('data.status', 'approved');
    $this->postJson("{$this->base}/adjustments/{$uuid}/decision", ['decision' => 'rejected', 'note' => 'Já analisado antes'])->assertStatus(422);

    expect(TimeEntry::where('user_id', $this->worker->id)->where('source', 'manual')->count())->toBe(1);
    // Gestor foi avisado do pedido e o colaborador da decisão.
    expect(\App\Models\Notification::where('user_id', $this->manager->id)->where('type', 'workspace_request')->exists())->toBeTrue()
        ->and(\App\Models\Notification::where('user_id', $this->worker->id)->where('type', 'workspace_request_decision')->exists())->toBeTrue();
});
