<?php

/** Painel da diretoria: só agregados, só para dono, RH e diretoria; grupo mínimo para somas por setor. */

use App\Models\Department;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\TimeBankAdjustment;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkspaceRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function dashMember(Hotel $hotel, string $role = 'employee', array $extra = []): User
{
    $user = User::factory()->create();
    HotelUser::create(array_merge(['hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'approved'], $extra));

    return $user;
}

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 12:00:00'); // terça
    Cache::flush();
    $this->hotel = Hotel::factory()->create();
    $this->base  = "/api/v1/workspaces/{$this->hotel->slug}";
    $this->ops   = Department::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => 'Operações']);
    $this->fin   = Department::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => 'Financeiro']);

    $this->rh    = dashMember($this->hotel, 'admin');
    $this->staff = [];
    foreach (range(1, 3) as $i) {
        $this->staff[] = dashMember($this->hotel, 'employee', ['department_id' => $this->ops->id, 'hired_at' => $i === 1 ? '2026-10-01' : '2020-01-10']);
    }
    $this->alone = dashMember($this->hotel, 'employee', ['department_id' => $this->fin->id]);
});

afterEach(fn () => Carbon::setTestNow());

it('shows company numbers to owner, RH and the new director role only', function () {
    Sanctum::actingAs($this->staff[0]);
    $this->getJson("{$this->base}/dashboard")->assertForbidden();

    $director = dashMember($this->hotel);
    HotelUser::where('user_id', $director->id)->update(['access_role' => 'director']);
    Sanctum::actingAs($director);
    $res = $this->getJson("{$this->base}/dashboard")->assertOk();
    expect($res->json('data.people.headcount'))->toBe(6)->and($res->json('data.people.hires_90d'))->toBe(1);

    // a diretoria só lê: não administra pessoas
    $this->getJson("{$this->base}/roles")->assertForbidden();
    Sanctum::actingAs($this->rh);
    $this->getJson("{$this->base}/dashboard")->assertOk();
    $this->getJson($this->base)->assertOk()->assertJsonPath('data.me.access_role', 'owner');
    Sanctum::actingAs($director);
    $this->getJson($this->base)->assertOk()->assertJsonPath('data.me.access_role', 'director')->assertJsonPath('data.me.can_see_dashboard', true);
});

it('counts today, requests, bank and engagement without naming anyone', function () {
    TimeEntry::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $this->staff[0]->id, 'type' => 'in', 'punched_at' => Carbon::parse('2026-11-10 08:00', 'America/Sao_Paulo')->utc(), 'source' => 'web', 'outside_network' => true]);
    TimeEntry::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $this->staff[1]->id, 'type' => 'in', 'punched_at' => Carbon::parse('2026-11-10 08:05', 'America/Sao_Paulo')->utc(), 'source' => 'web']);
    TimeEntry::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $this->staff[1]->id, 'type' => 'out', 'punched_at' => Carbon::parse('2026-11-10 09:00', 'America/Sao_Paulo')->utc(), 'source' => 'web']);
    TimeBankAdjustment::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $this->staff[2]->id, 'minutes' => 120, 'reason' => 'Extra', 'created_by' => $this->rh->id]);
    TimeBankAdjustment::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $this->alone->id, 'minutes' => -60, 'reason' => 'Folga', 'created_by' => $this->rh->id]);
    WorkspaceRequest::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'requester_id' => $this->staff[0]->id, 'type' => 'vacation', 'title' => 'Férias', 'status' => 'pending']);
    WorkspaceRequest::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'requester_id' => $this->staff[1]->id, 'type' => 'document', 'title' => 'Declaração', 'status' => 'approved', 'decided_by' => $this->rh->id, 'decided_at' => now()->addHours(6), 'created_at' => now()]);

    Sanctum::actingAs($this->rh);
    $d = $this->getJson("{$this->base}/dashboard")->assertOk()->json('data');

    expect($d['today']['present'])->toBe(2)->and($d['today']['working_now'])->toBe(1);
    expect($d['time']['outside_network_7d'])->toBe(1)->and($d['time']['bank_to_receive_minutes'])->toBeGreaterThanOrEqual(120);
    expect($d['requests']['pending'])->toBe(1)->and($d['requests']['pending_by_type']['vacation'])->toBe(1)->and($d['requests']['avg_decision_hours'])->toEqual(6)->and($d['requests']['approval_rate'])->toBe(100);

    // nada que identifique pessoas: sem nomes nem e-mails no payload
    $json = json_encode($d);
    expect($json)->not->toContain($this->staff[0]->name)->not->toContain($this->staff[0]->email)->not->toContain($this->alone->name);
});

it('hides department bank sums for groups smaller than the minimum', function () {
    TimeBankAdjustment::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $this->alone->id, 'minutes' => 300, 'reason' => 'Extra', 'created_by' => $this->rh->id]);

    Sanctum::actingAs($this->rh);
    $depts = collect($this->getJson("{$this->base}/dashboard")->json('data.departments'))->keyBy('name');
    expect($depts['Operações']['headcount'])->toBe(3)->and($depts['Operações']['bank_minutes'])->toBe(0);
    expect($depts['Financeiro']['headcount'])->toBe(1)->and($depts['Financeiro']['bank_minutes'])->toBeNull(); // 1 pessoa: não expõe o saldo dela
});

it('lets the owner give the director role and treats it as privileged for two-factor', function () {
    $person = dashMember($this->hotel);
    Sanctum::actingAs($this->rh);
    $this->patchJson("{$this->base}/roles/{$person->uuid}", ['access_role' => 'director'])->assertOk()->assertJsonPath('data.access_role', 'director');

    $this->putJson("{$this->base}/security", ['require_2fa' => true])->assertOk();
    $this->getJson("{$this->base}/security")->assertOk()->assertJsonPath('data.privileged_total', 2); // dono + diretoria
});
