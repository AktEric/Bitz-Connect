<?php

/** Destaques da Início: aniversariantes do dia, ausências, novos colaboradores, vagas e eventos. */

use App\Models\Department;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use App\Models\WorkspaceJob;
use App\Models\WorkspaceRequest;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function hlMember(Hotel $hotel, string $role = 'employee', ?User $manager = null, ?Department $dept = null, array $extra = []): User
{
    $user = User::factory()->create();
    HotelUser::create(array_merge([
        'hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'approved',
        'manager_id' => $manager?->id, 'department_id' => $dept?->id, 'hired_at' => '2020-01-01',
    ], $extra));

    return $user;
}

beforeEach(function () {
    Carbon::setTestNow('2026-11-09 12:00:00');
    $this->hotel = Hotel::factory()->create();
    $this->base  = "/api/v1/workspaces/{$this->hotel->slug}/home/highlights";
    $this->ops   = Department::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => 'Operações']);

    $this->rh      = hlMember($this->hotel, 'admin');
    $this->manager = hlMember($this->hotel, 'employee', null, $this->ops);
    $this->worker  = hlMember($this->hotel, 'employee', $this->manager, $this->ops, ['birth_date' => '1995-11-09', 'job_title' => 'Recepcionista']);
    $this->shy     = hlMember($this->hotel, 'employee', null, null, ['birth_date' => '1990-11-09', 'birthday_visible' => false]);
    $this->newbie  = hlMember($this->hotel, 'employee', null, $this->ops, ['hired_at' => '2026-11-03', 'job_title' => 'Camareira']);
    $this->veteran = hlMember($this->hotel, 'employee', null, null, ['hired_at' => '2026-08-01']);
});

afterEach(fn () => Carbon::setTestNow());

it('lists today birthdays, leaving out the ones the person chose to hide', function () {
    Sanctum::actingAs($this->rh);
    $b = $this->getJson($this->base)->assertOk()->json('data.birthdays_today');

    expect($b)->toHaveCount(1)
        ->and($b[0]['uuid'])->toBe($this->worker->uuid)
        ->and($b[0]['job_title'])->toBe('Recepcionista');
});

it('lists people hired in the last 60 days, newest first', function () {
    Sanctum::actingAs($this->worker);
    $h = $this->getJson($this->base)->assertOk()->json('data.new_hires');

    expect(array_column(array_column($h, 'person'), 'uuid'))->toBe([$this->newbie->uuid])
        ->and($h[0]['department'])->toBe('Operações')
        ->and($h[0]['hired_at'])->toBe('2026-11-03');
});

it('shows absences with the calendar visibility rules', function () {
    WorkspaceRequest::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'requester_id' => $this->worker->id, 'type' => 'vacation',
        'title' => 'Férias', 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-20', 'status' => 'approved']);
    WorkspaceRequest::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'requester_id' => $this->veteran->id, 'type' => 'vacation',
        'title' => 'Férias', 'starts_on' => '2026-11-25', 'ends_on' => '2026-12-05', 'status' => 'approved']);
    WorkspaceRequest::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'requester_id' => $this->newbie->id, 'type' => 'vacation',
        'title' => 'Férias', 'starts_on' => '2026-11-12', 'ends_on' => '2026-11-14', 'status' => 'pending']);

    Sanctum::actingAs($this->rh);
    $a = $this->getJson($this->base)->assertOk()->json('data.absences');
    expect(array_column(array_column($a, 'person'), 'uuid'))->toBe([$this->worker->uuid, $this->veteran->uuid])
        ->and($a[0]['ongoing'])->toBeTrue()->and($a[0]['ends_on'])->toBe('2026-11-20')
        ->and($a[1]['ongoing'])->toBeFalse();

    // gestor vê só a própria equipe; colega de fora não vê as férias dos outros
    Sanctum::actingAs($this->manager);
    expect(array_column(array_column($this->getJson($this->base)->json('data.absences'), 'person'), 'uuid'))->toBe([$this->worker->uuid]);
    Sanctum::actingAs($this->newbie);
    expect($this->getJson($this->base)->json('data.absences'))->toBe([]);
});

it('lists only open jobs', function () {
    $job = fn (array $a) => WorkspaceJob::create($a + ['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'description' => 'x', 'share_token' => Str::random(40)]);
    $job(['title' => 'Recepcionista', 'status' => 'open', 'department_id' => $this->ops->id, 'closes_on' => '2026-11-30']);
    $job(['title' => 'Encerrada', 'status' => 'closed']);
    $job(['title' => 'Vencida', 'status' => 'open', 'closes_on' => '2026-11-01']);

    Sanctum::actingAs($this->worker);
    $jobs = $this->getJson($this->base)->assertOk()->json('data.jobs');
    expect(array_column($jobs, 'title'))->toBe(['Recepcionista'])->and($jobs[0]['department'])->toBe('Operações');
});

it('is only for members of the company', function () {
    Sanctum::actingAs(User::factory()->create());
    $this->getJson($this->base)->assertForbidden();
});
