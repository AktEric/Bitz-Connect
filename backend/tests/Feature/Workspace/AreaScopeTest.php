<?php

/** Gestor de área, árvore hierárquica e substituições: quem enxerga e aprova quem. */

use App\Models\Department;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

function areaMember(Hotel $hotel, string $role = 'employee', ?User $manager = null, ?Department $dept = null): User
{
    $user = User::factory()->create();
    HotelUser::create([
        'hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'approved',
        'manager_id' => $manager?->id, 'department_id' => $dept?->id,
    ]);

    return $user;
}

beforeEach(function () {
    Carbon::setTestNow('2026-11-06 12:00:00');
    $this->hotel = Hotel::factory()->create();
    $this->base  = "/api/v1/workspaces/{$this->hotel->slug}";
    $this->rh    = areaMember($this->hotel, 'admin');

    $this->ops   = Department::create(['uuid' => (string) Illuminate\Support\Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => 'Operações']);
    $this->fin   = Department::create(['uuid' => (string) Illuminate\Support\Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => 'Financeiro']);

    $this->head     = areaMember($this->hotel);                                  // chefe da área de Operações
    $this->opsLead  = areaMember($this->hotel, 'employee', null, $this->ops);    // coordenador (sem chefe direto)
    $this->opsStaff = areaMember($this->hotel, 'employee', $this->opsLead, $this->ops);
    $this->opsOther = areaMember($this->hotel, 'employee', null, $this->ops);
    $this->finStaff = areaMember($this->hotel, 'employee', null, $this->fin);
    $this->ops->update(['head_id' => $this->head->id]);
});

afterEach(fn () => Carbon::setTestNow());

it('lets an area head see and approve everyone in the area, but nobody outside it', function () {
    Sanctum::actingAs($this->opsStaff);
    $uuid = $this->postJson("{$this->base}/requests", ['type' => 'document', 'title' => 'Declaração'])->assertCreated()->json('data.uuid');

    Sanctum::actingAs($this->head);
    $this->getJson("{$this->base}/time/timesheet?user={$this->opsStaff->uuid}&month=2026-11")->assertOk();
    $this->getJson("{$this->base}/time/timesheet?user={$this->opsOther->uuid}&month=2026-11")->assertOk();
    $this->getJson("{$this->base}/time/timesheet?user={$this->finStaff->uuid}&month=2026-11")->assertForbidden();
    $this->getJson("{$this->base}/time/team")->assertOk()->assertJsonCount(3, 'data'); // staff, lead e other
    $this->postJson("{$this->base}/requests/{$uuid}/decision", ['decision' => 'approved'])->assertOk();

    $me = $this->getJson($this->base)->assertOk();
    expect($me->json('data.me.is_manager'))->toBeTrue()
        ->and($me->json('data.me.team_size'))->toBe(3)
        ->and($me->json('data.me.areas_headed'))->toBe(['Operações']);
});

it('lets a manager of managers see the whole tree below, not only direct reports', function () {
    $director = areaMember($this->hotel);
    $manager  = areaMember($this->hotel, 'employee', $director);
    $worker   = areaMember($this->hotel, 'employee', $manager);

    Sanctum::actingAs($director);
    $this->getJson("{$this->base}/time/timesheet?user={$worker->uuid}&month=2026-11")->assertOk();
    $this->getJson("{$this->base}/time/timesheet?user={$this->finStaff->uuid}&month=2026-11")->assertForbidden();

    Sanctum::actingAs($worker);
    $this->getJson("{$this->base}/time/team")->assertForbidden();
});

it('routes requests of people without a manager to the area head, then to RH', function () {
    Sanctum::actingAs($this->opsOther);
    $this->postJson("{$this->base}/requests", ['type' => 'document', 'title' => 'Comprovante'])->assertCreated();
    expect(Notification::where('user_id', $this->head->id)->where('type', 'workspace_request')->count())->toBe(1);
    expect(Notification::where('user_id', $this->rh->id)->where('type', 'workspace_request')->count())->toBe(0);

    Sanctum::actingAs($this->finStaff); // sem gestor e sem chefe: vai para o RH
    $this->postJson("{$this->base}/requests", ['type' => 'document', 'title' => 'Outro'])->assertCreated();
    expect(Notification::where('user_id', $this->rh->id)->where('type', 'workspace_request')->count())->toBe(1);
});

it('gives the substitute the manager scope only while the delegation is active', function () {
    $substitute = areaMember($this->hotel);

    Sanctum::actingAs($this->head);
    $this->postJson("{$this->base}/delegations", ['to_user_uuid' => $substitute->uuid, 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-14'])->assertCreated();

    Sanctum::actingAs($substitute);
    $this->getJson("{$this->base}/time/timesheet?user={$this->opsStaff->uuid}&month=2026-11")->assertForbidden(); // ainda não começou

    Carbon::setTestNow('2026-11-11 09:00:00');
    $this->getJson("{$this->base}/time/timesheet?user={$this->opsStaff->uuid}&month=2026-11")->assertOk();
    $this->getJson("{$this->base}/time/timesheet?user={$this->finStaff->uuid}&month=2026-11")->assertForbidden();

    Carbon::setTestNow('2026-11-15 09:00:00');
    $this->getJson("{$this->base}/time/timesheet?user={$this->opsStaff->uuid}&month=2026-11")->assertForbidden(); // acabou
});

it('only lets RH or the manager create delegations, and only for people who have a team', function () {
    $substitute = areaMember($this->hotel);
    $payload = ['to_user_uuid' => $substitute->uuid, 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-12'];

    Sanctum::actingAs($this->finStaff); // sem equipe
    $this->postJson("{$this->base}/delegations", $payload)->assertStatus(422);
    $this->postJson("{$this->base}/delegations", $payload + ['from_user_uuid' => $this->head->uuid])->assertForbidden();

    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/delegations", $payload + ['from_user_uuid' => $this->head->uuid])->assertCreated();
    $this->postJson("{$this->base}/delegations", ['to_user_uuid' => $this->head->uuid, 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-09', 'from_user_uuid' => $this->head->uuid])->assertStatus(422);
});

it('keeps area management to RH and records the change in the audit trail', function () {
    Sanctum::actingAs($this->head);
    $this->getJson("{$this->base}/areas")->assertForbidden();
    $this->patchJson("{$this->base}/areas/{$this->fin->uuid}", ['head_uuid' => $this->head->uuid])->assertForbidden();

    Sanctum::actingAs($this->rh);
    $res = $this->getJson("{$this->base}/areas")->assertOk();
    expect($res->json('data.areas'))->toHaveCount(2)->and($res->json('data.people'))->toHaveCount(6);

    $this->patchJson("{$this->base}/areas/{$this->fin->uuid}", ['head_uuid' => $this->opsLead->uuid])->assertOk()
        ->assertJsonPath('data.head.uuid', $this->opsLead->uuid);
    $this->patchJson("{$this->base}/areas/{$this->fin->uuid}", ['head_uuid' => User::factory()->create()->uuid])->assertNotFound();
    $this->patchJson("{$this->base}/areas/{$this->fin->uuid}", ['head_uuid' => null])->assertOk()->assertJsonPath('data.head', null);

    $actions = collect($this->getJson("{$this->base}/audit")->json('data.items'))->pluck('action');
    expect($actions->filter(fn ($a) => $a === 'area.head_changed'))->toHaveCount(2);
});
