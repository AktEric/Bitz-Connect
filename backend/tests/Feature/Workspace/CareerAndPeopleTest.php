<?php

use App\Models\CareerEntry;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use App\Models\WorkspaceJob;
use App\Models\WorkspacePost;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->hotel = Hotel::factory()->create();
    $this->mk = fn (string $role, array $extra = []) => tap(User::factory()->create(), fn ($u) => HotelUser::create(
        ['hotel_id' => $this->hotel->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'approved'] + $extra));
    $this->rh      = ($this->mk)('admin', ['access_role' => 'hr']);
    $this->boss    = ($this->mk)('employee', ['job_title' => 'Gerente']);
    $this->worker  = ($this->mk)('employee', ['job_title' => 'Analista', 'manager_id' => $this->boss->id, 'hired_at' => '2020-02-01']);
    $this->peer    = ($this->mk)('employee', ['manager_id' => $this->boss->id]);
    $this->base    = "/api/v1/workspaces/{$this->hotel->slug}";
});

it('exposes capabilities by hierarchy so the UI hides what the person cannot do', function () {
    $caps = fn (User $u) => (function () use ($u) {
        Sanctum::actingAs($u);

        return $this->getJson($this->base)->assertOk()->json('data.me.permissions');
    })->call($this);

    expect($caps($this->rh))->toContain('people.manage', 'career.manage', 'jobs.manage', 'audit.view', 'feed.moderate')
        ->and($caps($this->boss))->toContain('jobs.manage', 'feed.post', 'time.team')->not->toContain('people.manage', 'audit.view', 'career.manage')
        ->and($caps($this->worker))->toContain('feed.view', 'jobs.apply')->not->toContain('jobs.manage', 'feed.post', 'time.team', 'reports.view');
});

it('lets everyone read a colleague career but only HR edit it', function () {
    CareerEntry::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $this->worker->id,
        'title' => 'Estagiário', 'started_on' => '2020-02-01', 'ended_on' => '2021-01-31']);

    Sanctum::actingAs($this->peer);
    $r = $this->getJson("{$this->base}/people/{$this->worker->uuid}/career")->assertOk();
    expect($r->json('data.items'))->toHaveCount(1)->and($r->json('data.can_edit'))->toBeFalse();
    $this->postJson("{$this->base}/people/{$this->worker->uuid}/career", ['title' => 'X', 'started_on' => '2022-01-01'])->assertForbidden();

    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/people/{$this->worker->uuid}/career", ['title' => 'Analista', 'started_on' => '2021-02-01'])
        ->assertCreated()->assertJsonPath('data.items.0.title', 'Analista')->assertJsonPath('data.items.0.current', true);
    $this->postJson("{$this->base}/people/{$this->worker->uuid}/career", ['title' => 'Y', 'started_on' => '2022-05-01', 'ended_on' => '2022-01-01'])->assertUnprocessable();
});

it('does not leak career of people outside the company', function () {
    $outsider = User::factory()->create();
    Sanctum::actingAs($this->worker);
    $this->getJson("{$this->base}/people/{$outsider->uuid}/career")->assertNotFound();
});

it('rolls the career forward when HR marks an internal applicant as hired', function () {
    $job = WorkspaceJob::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'created_by' => $this->rh->id,
        'title' => 'Analista Sênior', 'description' => 'x', 'status' => 'open', 'share_token' => Str::random(30)]);

    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/jobs/{$job->uuid}/apply", [])->assertOk();
    $app = $job->applications()->first();

    Sanctum::actingAs($this->rh);
    $this->patchJson("{$this->base}/jobs/{$job->uuid}/applications/{$app->uuid}", ['status' => 'hired'])->assertOk();
    // repetir não duplica o histórico
    $this->patchJson("{$this->base}/jobs/{$job->uuid}/applications/{$app->uuid}", ['status' => 'hired'])->assertOk();

    $entries = CareerEntry::where('user_id', $this->worker->id)->orderBy('started_on')->get();
    expect($entries)->toHaveCount(2)
        ->and($entries[0]->title)->toBe('Analista')->and($entries[0]->ended_on)->not->toBeNull()
        ->and($entries[1]->title)->toBe('Analista Sênior')->and($entries[1]->ended_on)->toBeNull()->and($entries[1]->source)->toBe('job')
        ->and(HotelUser::where('user_id', $this->worker->id)->value('job_title'))->toBe('Analista Sênior');
});

it('shows leaders, team and sector peers of a person', function () {
    $dept = $this->hotel->departments()->create(['uuid' => (string) Str::uuid(), 'name' => 'Operações', 'head_id' => $this->boss->id]);
    HotelUser::whereIn('user_id', [$this->worker->id, $this->peer->id, $this->boss->id])->update(['department_id' => $dept->id]);

    Sanctum::actingAs($this->peer);
    $r = $this->getJson("{$this->base}/people/{$this->worker->uuid}/org")->assertOk();
    expect($r->json('data.manager.uuid'))->toBe($this->boss->uuid)
        ->and($r->json('data.department.name'))->toBe('Operações')
        ->and($r->json('data.department.head.uuid'))->toBe($this->boss->uuid)
        ->and(collect($r->json('data.peers'))->pluck('uuid')->all())->toContain($this->peer->uuid, $this->boss->uuid);

    $r = $this->getJson("{$this->base}/people/{$this->boss->uuid}/org")->assertOk();
    expect(collect($r->json('data.team'))->pluck('uuid')->all())->toContain($this->worker->uuid, $this->peer->uuid);
});

it('supports the four new reactions and lists who reacted', function () {
    $post = WorkspacePost::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'author_id' => $this->rh->id, 'body' => 'Oi']);

    foreach ([[$this->worker, 'laugh'], [$this->peer, 'sad'], [$this->boss, 'thinking']] as [$u, $reaction]) {
        Sanctum::actingAs($u);
        $this->postJson("{$this->base}/feed/{$post->uuid}/react", ['reaction' => $reaction])->assertOk();
    }
    $this->postJson("{$this->base}/feed/{$post->uuid}/react", ['reaction' => 'surprised'])->assertOk()->assertJsonPath('data.my_reaction', 'surprised');
    $this->postJson("{$this->base}/feed/{$post->uuid}/react", ['reaction' => 'angry'])->assertUnprocessable();

    $r = $this->getJson("{$this->base}/feed/{$post->uuid}/reactions")->assertOk();
    expect($r->json('data.total'))->toBe(3)->and($r->json('data.counts.laugh'))->toBe(1)->and($r->json('data.counts.surprised'))->toBe(1)
        ->and(collect($r->json('data.items'))->pluck('person.uuid')->all())->toContain($this->worker->uuid, $this->peer->uuid);

    $outsider = User::factory()->create();
    Sanctum::actingAs($outsider);
    $this->getJson("{$this->base}/feed/{$post->uuid}/reactions")->assertNotFound();
});
