<?php

/** Auditoria: lista de ações para filtro, exportação em CSV e permissões. */

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function auditMember(Hotel $hotel, string $role = 'employee'): User
{
    $user = User::factory()->create();
    HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'approved']);

    return $user;
}

beforeEach(function () {
    $this->hotel  = Hotel::factory()->create();
    $this->base   = "/api/v1/workspaces/{$this->hotel->slug}";
    $this->rh     = auditMember($this->hotel, 'admin');
    $this->worker = auditMember($this->hotel);
});

it('lists the recorded actions so the screen can filter by them', function () {
    foreach (['role.changed', 'payslip.downloaded', 'role.changed'] as $a) {
        AuditLog::create(['hotel_id' => $this->hotel->id, 'actor_id' => $this->rh->id, 'target_id' => $this->worker->id, 'action' => $a, 'created_at' => now()]);
    }
    Sanctum::actingAs($this->rh);
    $res = $this->getJson("{$this->base}/audit")->assertOk();
    expect($res->json('data.actions'))->toBe(['payslip.downloaded', 'role.changed']);
    $this->getJson("{$this->base}/audit?action=role.changed")->assertOk()->assertJsonCount(2, 'data.items');
});

it('exports the audit trail as CSV for RH only and records the export itself', function () {
    AuditLog::create(['hotel_id' => $this->hotel->id, 'actor_id' => $this->rh->id, 'target_id' => $this->worker->id, 'action' => 'role.changed', 'meta' => ['to' => 'hr'], 'ip' => '10.0.0.1', 'created_at' => now()]);

    Sanctum::actingAs($this->worker);
    $this->get("{$this->base}/audit/export")->assertForbidden();

    Sanctum::actingAs($this->rh);
    $csv = $this->get("{$this->base}/audit/export")->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
    expect($csv)->toContain('Data/hora (Brasília);Ação')->toContain('role.changed')->toContain($this->worker->name)->toContain('10.0.0.1');

    // a exportação fica registrada (aparece na próxima leitura)
    $actions = collect($this->getJson("{$this->base}/audit")->json('data.items'))->pluck('action');
    expect($actions)->toContain('audit.exported');
});
