<?php

/**
 * Matriz de acesso do Workspace: RH (admin), gestor, colaborador e estranho.
 * (Escrito na rodada noturna; ainda NÃO foi executado — rode `php artisan test --filter=Workspace`.)
 */

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function wsMember(Hotel $hotel, string $role = 'employee', ?User $manager = null): User
{
    $user = User::factory()->create();
    HotelUser::create([
        'hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => $role,
        'status' => 'approved', 'manager_id' => $manager?->id,
    ]);

    return $user;
}

beforeEach(function () {
    $this->hotel   = Hotel::factory()->create();
    $this->rh      = wsMember($this->hotel, 'admin');
    $this->manager = wsMember($this->hotel);
    $this->worker  = wsMember($this->hotel, 'employee', $this->manager);
    $this->other   = wsMember($this->hotel);
    $this->outsider = User::factory()->create();
    $this->base    = "/api/v1/workspaces/{$this->hotel->slug}";
});

it('blocks people outside the company', function () {
    Sanctum::actingAs($this->outsider);

    $this->getJson($this->base)->assertForbidden();
    $this->getJson("{$this->base}/feed")->assertForbidden();
    $this->getJson("{$this->base}/goals")->assertForbidden();
});

it('lets only RH or managers publish on the feed', function () {
    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/feed", ['body' => 'oi'])->assertForbidden();

    Sanctum::actingAs($this->manager);
    $this->postJson("{$this->base}/feed", ['body' => 'novidade'])->assertCreated();
});

it('routes request approval only to the direct manager or RH', function () {
    Sanctum::actingAs($this->worker);
    $uuid = $this->postJson("{$this->base}/requests", ['type' => 'document', 'title' => 'Declaração'])
        ->assertCreated()->json('data.uuid');

    // o próprio solicitante e um colega não decidem
    $this->postJson("{$this->base}/requests/{$uuid}/decision", ['decision' => 'approved'])->assertForbidden();
    Sanctum::actingAs($this->other);
    $this->postJson("{$this->base}/requests/{$uuid}/decision", ['decision' => 'approved'])->assertNotFound();

    Sanctum::actingAs($this->manager);
    $this->postJson("{$this->base}/requests/{$uuid}/decision", ['decision' => 'approved'])
        ->assertOk()->assertJsonPath('data.status', 'approved');
    $this->postJson("{$this->base}/requests/{$uuid}/decision", ['decision' => 'rejected', 'note' => 'x'])->assertStatus(409);
});

it('keeps payslips private to the owner and RH', function () {
    Storage::fake('local');

    Sanctum::actingAs($this->rh);
    $uuid = $this->post("{$this->base}/payslips", [
        'user_uuid' => $this->worker->uuid, 'kind' => 'payslip', 'title' => 'Holerite',
        'file' => UploadedFile::fake()->create('h.pdf', 50, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.uuid');

    Sanctum::actingAs($this->other);
    $this->getJson("{$this->base}/payslips/{$uuid}/download")->assertNotFound();

    Sanctum::actingAs($this->worker);
    $this->get("{$this->base}/payslips/{$uuid}/download")->assertOk();
    $this->postJson("{$this->base}/payslips", [])->assertForbidden();
});

it('rejects non-document uploads as payslips', function () {
    Storage::fake('local');
    Sanctum::actingAs($this->rh);

    $this->post("{$this->base}/payslips", [
        'user_uuid' => $this->worker->uuid, 'kind' => 'payslip', 'title' => 'x',
        'file' => UploadedFile::fake()->create('a.php', 1, 'text/plain'),
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('lets a manager see only the direct reports goals and PDI', function () {
    Sanctum::actingAs($this->manager);
    $this->postJson("{$this->base}/goals", [
        'owner_uuid' => $this->worker->uuid, 'title' => 'Meta', 'target_value' => 10,
        'period_start' => now()->toDateString(), 'period_end' => now()->addMonth()->toDateString(),
    ])->assertCreated();
    $this->postJson("{$this->base}/goals", [
        'owner_uuid' => $this->other->uuid, 'title' => 'Meta', 'target_value' => 10,
        'period_start' => now()->toDateString(), 'period_end' => now()->addMonth()->toDateString(),
    ])->assertForbidden();

    $this->getJson("{$this->base}/goals?scope=team")->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->base}/goals?scope=all")->assertForbidden();
});

it('marks a goal achieved when the target is reached', function () {
    Sanctum::actingAs($this->rh);
    $uuid = $this->postJson("{$this->base}/goals", [
        'owner_uuid' => $this->worker->uuid, 'title' => 'Meta', 'target_value' => 10,
        'period_start' => now()->toDateString(), 'period_end' => now()->addMonth()->toDateString(),
    ])->json('data.uuid');

    Sanctum::actingAs($this->worker);
    $this->postJson("{$this->base}/goals/{$uuid}/updates", ['value' => 10])
        ->assertOk()->assertJsonPath('data.status', 'achieved');
});

it('lists segment templates and applies one only for admins', function () {
    Sanctum::actingAs($this->worker);
    $this->getJson('/api/v1/segment-templates')->assertOk()
        ->assertJsonFragment(['key' => 'restaurante']);
    $this->postJson("/api/v1/hotels/{$this->hotel->slug}/segment-template", ['segment' => 'restaurante'])
        ->assertForbidden();

    Sanctum::actingAs($this->rh);
    $this->postJson("/api/v1/hotels/{$this->hotel->slug}/segment-template", ['segment' => 'restaurante'])
        ->assertOk()->assertJsonPath('data.created.0', 'Cozinha');
    // Reaplicar não duplica.
    $this->postJson("/api/v1/hotels/{$this->hotel->slug}/segment-template", ['segment' => 'restaurante'])
        ->assertOk()->assertJsonCount(0, 'data.created');
    $this->postJson("/api/v1/hotels/{$this->hotel->slug}/segment-template", ['segment' => 'inexistente'])
        ->assertStatus(422);
});

it('exposes the effective access role and marks my companies as admin', function () {
    Sanctum::actingAs($this->rh);
    $this->getJson($this->base)->assertOk()->assertJsonPath('data.me.access_role', 'owner');
    $this->getJson('/api/v1/me/hotels')->assertOk()->assertJsonPath('data.0.is_admin', true);

    Sanctum::actingAs($this->manager);
    $this->getJson($this->base)->assertOk()->assertJsonPath('data.me.access_role', 'manager');
});

it('makes the access role actually restrict what each role can do', function () {
    $setRole = fn (User $u, string $role) => HotelUser::where('hotel_id', $this->hotel->id)
        ->where('user_id', $u->id)->update(['access_role' => $role]);

    // RH anexa um holerite para a colaboradora
    Sanctum::actingAs($this->rh);
    $this->post("{$this->base}/payslips", [
        'user_uuid' => $this->worker->uuid, 'kind' => 'payslip', 'title' => 'Holerite',
        'file' => UploadedFile::fake()->create('h.pdf', 20, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertCreated();

    // Financeiro: lida com holerites, mas não administra pessoas
    $finance = wsMember($this->hotel);
    $setRole($finance, 'finance');
    Sanctum::actingAs($finance);
    $this->getJson("{$this->base}/payslips?all=1")->assertOk()->assertJsonCount(1, 'data.items');
    $this->getJson("{$this->base}/roles")->assertForbidden();
    $this->postJson("{$this->base}/feed", ['body' => 'aviso'])->assertForbidden();

    // Um admin antigo rebaixado a "employee" perde os poderes de RH
    $demoted = wsMember($this->hotel, 'admin');
    $setRole($demoted, 'employee');
    Sanctum::actingAs($demoted);
    $this->getJson("{$this->base}/roles")->assertForbidden();
    $this->getJson("{$this->base}/payslips?all=1")->assertOk()->assertJsonCount(0, 'data.items');
    $this->postJson("{$this->base}/payslips", [])->assertForbidden();

    // Colaborador promovido a RH ganha acesso
    $setRole($this->other, 'hr');
    Sanctum::actingAs($this->other);
    $this->getJson("{$this->base}/roles")->assertOk()->assertJsonPath('data.can_edit', false);
    $this->postJson("{$this->base}/feed", ['body' => 'aviso do RH'])->assertCreated();
});

it('lets only the owner change roles and keeps at least one owner', function () {
    Sanctum::actingAs($this->worker);
    $this->patchJson("{$this->base}/roles/{$this->other->uuid}", ['access_role' => 'hr'])->assertForbidden();

    Sanctum::actingAs($this->rh);
    $this->getJson("{$this->base}/roles")->assertOk()->assertJsonPath('data.can_edit', true);
    $this->patchJson("{$this->base}/roles/{$this->other->uuid}", ['access_role' => 'finance'])->assertOk();
    $this->patchJson("{$this->base}/roles/{$this->other->uuid}", ['access_role' => 'bogus'])->assertStatus(422);

    // único dono não pode se rebaixar
    $this->patchJson("{$this->base}/roles/{$this->rh->uuid}", ['access_role' => 'hr'])->assertStatus(422);
});

it('records sensitive HR actions in an audit trail only RH can read', function () {
    Sanctum::actingAs($this->rh);
    $uuid = $this->post("{$this->base}/payslips", [
        'user_uuid' => $this->worker->uuid, 'kind' => 'payslip', 'title' => 'Holerite',
        'file' => UploadedFile::fake()->create('h.pdf', 20, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.uuid');
    $this->get("{$this->base}/payslips/{$uuid}/download")->assertOk();
    $this->patchJson("{$this->base}/roles/{$this->other->uuid}", ['access_role' => 'finance'])->assertOk();

    // o próprio dono do holerite baixando não gera registro de terceiro
    Sanctum::actingAs($this->worker);
    $this->get("{$this->base}/payslips/{$uuid}/download")->assertOk();
    $this->getJson("{$this->base}/audit")->assertForbidden();

    Sanctum::actingAs($this->rh);
    $res = $this->getJson("{$this->base}/audit")->assertOk();
    $actions = collect($res->json('data.items'))->pluck('action')->all();
    expect($actions)->toContain('payslip.created', 'payslip.downloaded', 'role.changed');
    expect(collect($actions)->filter(fn ($a) => $a === 'payslip.downloaded'))->toHaveCount(1);
    $this->getJson("{$this->base}/audit?action=role.changed")->assertOk()->assertJsonCount(1, 'data.items');
});

it('keeps the company employee list private to logged-in users', function () {
    $this->getJson("/api/v1/hotels/{$this->hotel->slug}/employees")->assertUnauthorized();
    $this->getJson("/api/v1/hotels/{$this->hotel->slug}/admins")->assertUnauthorized();

    Sanctum::actingAs($this->worker);
    $this->getJson("/api/v1/hotels/{$this->hotel->slug}/employees")->assertOk();
});
