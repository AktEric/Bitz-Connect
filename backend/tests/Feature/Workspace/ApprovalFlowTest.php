<?php

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->hotel = Hotel::factory()->create();
    $mk = fn (string $role, array $extra = []) => tap(User::factory()->create(), fn ($u) => HotelUser::create(
        ['hotel_id' => $this->hotel->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'approved'] + $extra));
    $this->owner    = $mk('admin', ['access_role' => 'owner']);
    $this->hr       = $mk('admin', ['access_role' => 'hr']);
    $this->finance  = $mk('employee', ['access_role' => 'finance']);
    $this->director = $mk('employee', ['access_role' => 'director']);
    $this->lead     = $mk('employee', ['job_title' => 'Gerente']);
    $this->ana      = $mk('employee', ['manager_id' => $this->lead->id]);
    $this->bruno    = $mk('employee', ['manager_id' => $this->lead->id]);
    $this->base     = "/api/v1/workspaces/{$this->hotel->slug}";

    $this->flow = fn (string $type, array $steps) => (function () use ($type, $steps) {
        Sanctum::actingAs($this->hr);

        return $this->putJson("{$this->base}/request-flows/{$type}", ['steps' => $steps])->assertOk();
    })->call($this);
    $this->ask = fn (User $by, string $type = 'reimbursement') => (function () use ($by, $type) {
        Sanctum::actingAs($by);

        return $this->postJson("{$this->base}/requests", ['type' => $type, 'title' => 'Pedido de teste', 'amount_cents' => 5000, 'starts_on' => '2026-12-10', 'ends_on' => '2026-12-20'])->assertCreated()->json('data.uuid');
    })->call($this);
    $this->decide = fn (User $by, string $uuid, string $d = 'approved') => (function () use ($by, $uuid, $d) {
        Sanctum::actingAs($by);

        return $this->postJson("{$this->base}/requests/{$uuid}/decision", ['decision' => $d, 'note' => 'ok']);
    })->call($this);
    $this->show = fn (User $by, string $uuid) => (function () use ($by, $uuid) {
        Sanctum::actingAs($by);

        return $this->getJson("{$this->base}/requests/{$uuid}");
    })->call($this);
});

it('runs manager then finance, in order, and shows who approved, who is analysing and who is missing', function () {
    ($this->flow)('reimbursement', ['manager', 'finance']);
    $uuid = ($this->ask)($this->ana);

    $d = ($this->show)($this->ana, $uuid)->assertOk()->json('data');
    expect(collect($d['approvals'])->pluck('role')->all())->toBe(['manager', 'finance'])
        ->and($d['approvals'][0]['status'])->toBe('pending')->and($d['approvals'][0]['is_current'])->toBeTrue()
        ->and(collect($d['approvals'][0]['approvers'])->pluck('uuid')->all())->toContain($this->lead->uuid)
        ->and($d['approvals'][1]['status'])->toBe('waiting')->and(collect($d['approvals'][1]['approvers'])->pluck('uuid')->all())->toBe([$this->finance->uuid])
        ->and($d['stage'])->toBe('Aguardando gestor')->and($d['steps_total'])->toBe(2);

    // financeiro não pode pular a vez do gestor; colega e solicitante também não
    ($this->decide)($this->finance, $uuid)->assertForbidden();
    ($this->decide)($this->bruno, $uuid)->assertStatus(404);
    ($this->decide)($this->ana, $uuid)->assertStatus(403);

    ($this->decide)($this->lead, $uuid)->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.stage', 'Aguardando financeiro');
    // gestor não decide a etapa do financeiro; RH também não
    ($this->decide)($this->lead, $uuid)->assertForbidden();
    ($this->decide)($this->hr, $uuid)->assertForbidden();

    ($this->decide)($this->finance, $uuid)->assertOk()->assertJsonPath('data.status', 'approved');
    $d = ($this->show)($this->ana, $uuid)->json('data');
    expect(collect($d['approvals'])->pluck('status')->all())->toBe(['approved', 'approved'])->and($d['approvals'][1]['decided_by']['uuid'])->toBe($this->finance->uuid);
});

it('stops the whole flow when any step rejects', function () {
    ($this->flow)('document', ['manager', 'hr', 'director']);
    $uuid = ($this->ask)($this->ana, 'document');
    ($this->decide)($this->lead, $uuid)->assertOk();
    ($this->decide)($this->hr, $uuid, 'rejected')->assertOk()->assertJsonPath('data.status', 'rejected');

    $d = ($this->show)($this->ana, $uuid)->json('data');
    expect(collect($d['approvals'])->pluck('status')->all())->toBe(['approved', 'rejected', 'skipped']);
    ($this->decide)($this->director, $uuid)->assertStatus(409);
});

it('lets the owner decide any step and a director decide the director step', function () {
    ($this->flow)('vacation', ['manager', 'director']);
    $uuid = ($this->ask)($this->ana, 'vacation');
    ($this->decide)($this->lead, $uuid)->assertOk();
    ($this->decide)($this->director, $uuid)->assertOk()->assertJsonPath('data.status', 'approved');

    $u2 = ($this->ask)($this->bruno, 'vacation');
    ($this->decide)($this->owner, $u2)->assertOk();            // dono decide a etapa do gestor
    ($this->decide)($this->owner, $u2)->assertOk()->assertJsonPath('data.status', 'approved'); // e a da diretoria
});

it('keeps the default (manager only) when nothing is configured', function () {
    $uuid = ($this->ask)($this->ana, 'schedule');
    ($this->decide)($this->lead, $uuid)->assertOk()->assertJsonPath('data.status', 'approved');
});

it('snapshots the flow: changing it later does not touch requests in progress', function () {
    ($this->flow)('reimbursement', ['manager', 'finance']);
    $uuid = ($this->ask)($this->ana);
    ($this->flow)('reimbursement', ['manager']);                 // empresa simplifica depois
    ($this->decide)($this->lead, $uuid)->assertOk()->assertJsonPath('data.status', 'pending');
    $new = ($this->ask)($this->bruno);                           // pedidos novos seguem o novo fluxo
    ($this->decide)($this->lead, $new)->assertOk()->assertJsonPath('data.status', 'approved');
});

it('skips a step when nobody in the company holds that role', function () {
    $hotel = Hotel::factory()->create();
    $mk = fn (string $role, array $extra = []) => tap(User::factory()->create(), fn ($u) => HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'approved'] + $extra));
    $hr = $mk('admin', ['access_role' => 'hr']);               // sem dono, sem financeiro
    $boss = $mk('employee');
    $worker = $mk('employee', ['manager_id' => $boss->id]);
    $base = "/api/v1/workspaces/{$hotel->slug}";

    Sanctum::actingAs($hr);
    $this->putJson("{$base}/request-flows/reimbursement", ['steps' => ['manager', 'finance']])->assertOk();
    Sanctum::actingAs($worker);
    $uuid = $this->postJson("{$base}/requests", ['type' => 'reimbursement', 'title' => 'x', 'amount_cents' => 100])->json('data.uuid');
    Sanctum::actingAs($boss);
    $this->postJson("{$base}/requests/{$uuid}/decision", ['decision' => 'approved'])->assertOk()->assertJsonPath('data.status', 'approved');
    Sanctum::actingAs($worker);
    expect(collect($this->getJson("{$base}/requests/{$uuid}")->json('data.approvals'))->pluck('status')->all())->toBe(['approved', 'skipped']);
});

it('only HR and the owner configure flows; everyone can read them; manager is always first', function () {
    Sanctum::actingAs($this->ana);
    $r = $this->getJson("{$this->base}/request-flows")->assertOk();
    expect($r->json('data.can_edit'))->toBeFalse()->and(collect($r->json('data.types'))->pluck('type')->all())->toContain('reimbursement');
    $this->putJson("{$this->base}/request-flows/reimbursement", ['steps' => ['manager', 'finance']])->assertForbidden();
    Sanctum::actingAs($this->lead);
    $this->putJson("{$this->base}/request-flows/reimbursement", ['steps' => ['manager', 'finance']])->assertForbidden();
    Sanctum::actingAs($this->finance);
    $this->putJson("{$this->base}/request-flows/reimbursement", ['steps' => ['manager']])->assertForbidden();

    Sanctum::actingAs($this->hr);
    $this->putJson("{$this->base}/request-flows/reimbursement", ['steps' => ['director', 'manager', 'director', 'finance']])->assertOk();
    $steps = collect($this->getJson("{$this->base}/request-flows")->json('data.types'))->firstWhere('type', 'reimbursement');
    expect($steps['steps'])->toBe(['manager', 'director', 'finance'])->and($steps['is_default'])->toBeFalse();
    $this->putJson("{$this->base}/request-flows/ticket", ['steps' => ['manager']])->assertNotFound();
    $this->putJson("{$this->base}/request-flows/vacation", ['steps' => ['ceo']])->assertUnprocessable();
});

it('shows a finance person the requests that reach their step, and nothing else', function () {
    ($this->flow)('reimbursement', ['manager', 'finance']);
    $uuid = ($this->ask)($this->ana);
    ($this->flow)('vacation', ['manager']);
    $other = ($this->ask)($this->bruno, 'vacation');

    Sanctum::actingAs($this->finance);
    $uuids = collect($this->getJson("{$this->base}/requests?scope=review")->json('data.items'))->pluck('uuid')->all();
    expect($uuids)->toContain($uuid)->not->toContain($other);
    $this->getJson("{$this->base}/requests/{$other}")->assertNotFound();
    expect($this->getJson("{$this->base}")->json('data.me.permissions'))->toContain('requests.review')->not->toContain('requests.configure');

    // contador de pendências só conta o que está na etapa dela
    expect($this->getJson($this->base)->json('data.counts.requests_to_review'))->toBe(0);   // ainda está com o gestor
    ($this->decide)($this->lead, $uuid)->assertOk();
    Sanctum::actingAs($this->finance);
    expect($this->getJson($this->base)->json('data.counts.requests_to_review'))->toBe(1);
});
