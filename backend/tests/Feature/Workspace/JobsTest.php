<?php

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\Notification;
use App\Models\User;
use App\Models\WorkspaceJob;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 12:00:00');
    $this->hotel = Hotel::factory()->create(['name' => 'Hotel Aurora']);
    $mk = fn (string $role, ?User $mgr = null, ?string $access = null) => tap(User::factory()->create(), fn ($u) => HotelUser::create(
        ['hotel_id' => $this->hotel->id, 'user_id' => $u->id, 'role' => $role, 'access_role' => $access, 'status' => 'approved', 'manager_id' => $mgr?->id]));
    $this->rh      = $mk('admin', null, 'hr');
    $this->manager = $mk('employee');
    $this->worker  = $mk('employee', $this->manager);
    $this->peer    = $mk('employee');
    $this->base    = "/api/v1/workspaces/{$this->hotel->slug}";
});
afterEach(fn () => Carbon::setTestNow());

function newJob($t, User $by, array $extra = []): string
{
    Sanctum::actingAs($by);

    return $t->postJson("{$t->base}/jobs", $extra + ['title' => 'Recepcionista noturno', 'description' => 'Atendimento ao hóspede.', 'public' => true, 'contact' => 'rh@aurora.com'])
        ->assertCreated()->json('data.uuid');
}

it('lets managers and RH open jobs but not regular employees', function () {
    Sanctum::actingAs($this->peer);
    $this->postJson("{$this->base}/jobs", ['title' => 'X', 'description' => 'Y'])->assertForbidden();
    expect($this->getJson("{$this->base}/jobs")->json('data.can_create'))->toBeFalse();

    newJob($this, $this->manager);
    newJob($this, $this->rh);
    expect(WorkspaceJob::count())->toBe(2);
});

it('lets staff apply confidentially: only the poster and RH see applicants, the own manager is not told', function () {
    $uuid = newJob($this, $this->manager, ['department_uuid' => null]);

    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/jobs/{$uuid}/apply", ['message' => 'Quero conhecer a área'])->assertOk();
    $this->postJson("{$this->base}/jobs/{$uuid}/apply")->assertOk(); // idempotente
    expect($this->getJson("{$this->base}/jobs")->json('data.items.0.applied'))->toBeTrue();
    expect(Notification::where('type', 'workspace_job_application')->where('user_id', $this->manager->id)->count())->toBe(1);

    // colega comum não vê candidaturas; RH e quem abriu veem
    Sanctum::actingAs($this->peer);
    $this->getJson("{$this->base}/jobs/{$uuid}/applications")->assertNotFound();
    Sanctum::actingAs($this->rh);
    $list = $this->getJson("{$this->base}/jobs/{$uuid}/applications")->assertOk()->json('data');
    expect($list)->toHaveCount(1)->and($list[0]['message'])->toBe('Quero conhecer a área');
    expect(AuditLog::where('action', 'job.applications_viewed')->count())->toBe(1);

    $this->patchJson("{$this->base}/jobs/{$uuid}/applications/{$list[0]['uuid']}", ['status' => 'reviewing'])->assertOk();

    // retirar a candidatura
    Sanctum::actingAs($this->worker);
    $this->deleteJson("{$this->base}/jobs/{$uuid}/apply")->assertOk();
    Sanctum::actingAs($this->rh);
    expect($this->getJson("{$this->base}/jobs/{$uuid}/applications")->json('data'))->toHaveCount(0);
});

it('hides closed jobs from staff, blocks applying, and serves the public page only for open public jobs', function () {
    $pub  = newJob($this, $this->rh);
    $priv = newJob($this, $this->rh, ['public' => false, 'title' => 'Vaga interna']);
    $token = WorkspaceJob::where('uuid', $pub)->value('share_token');

    $this->getJson("/api/v1/public/jobs/{$token}")->assertOk()->assertJsonPath('data.company', 'Hotel Aurora');
    expect(json_encode($this->getJson("/api/v1/public/jobs/{$token}")->json()))->not->toContain('created_by');
    $this->getJson('/api/v1/public/jobs/' . WorkspaceJob::where('uuid', $priv)->value('share_token'))->assertNotFound();

    Sanctum::actingAs($this->rh);
    $this->patchJson("{$this->base}/jobs/{$pub}", ['status' => 'closed'])->assertOk();
    $this->getJson("/api/v1/public/jobs/{$token}")->assertNotFound();
    Sanctum::actingAs($this->peer);
    $this->postJson("{$this->base}/jobs/{$pub}/apply")->assertNotFound();
    expect(collect($this->getJson("{$this->base}/jobs")->json('data.items'))->pluck('title')->all())->toBe(['Vaga interna']);

    // só quem abriu (ou RH) edita
    Sanctum::actingAs($this->manager);
    $this->patchJson("{$this->base}/jobs/{$priv}", ['title' => 'Hack'])->assertNotFound();
});
