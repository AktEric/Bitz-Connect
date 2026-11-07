<?php

/** NSR e hash encadeado: marcações à prova de adulteração, comprovante e verificação de integridade. */

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

function intMember(Hotel $hotel, string $role = 'employee', ?User $manager = null): User
{
    $user = User::factory()->create();
    HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'approved', 'manager_id' => $manager?->id]);

    return $user;
}

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 12:00:00');
    $this->hotel   = Hotel::factory()->create(['cnpj' => '12345678000199']);
    $this->other   = Hotel::factory()->create();
    $this->base    = "/api/v1/workspaces/{$this->hotel->slug}";
    $this->rh      = intMember($this->hotel, 'admin');
    $this->manager = intMember($this->hotel);
    $this->worker  = intMember($this->hotel, 'employee', $this->manager);
    $this->peer    = intMember($this->hotel);
});

afterEach(fn () => Carbon::setTestNow());

function intPunch($test, User $user, string $at)
{
    Carbon::setTestNow($at);
    Sanctum::actingAs($user);

    return $test->postJson("{$test->base}/time/punch")->assertCreated();
}

it('numbers every punch per company in sequence and chains the hashes', function () {
    intPunch($this, $this->worker, '2026-11-10 08:00:00');
    intPunch($this, $this->peer, '2026-11-10 08:05:00');
    intPunch($this, $this->worker, '2026-11-10 12:00:00');

    $entries = TimeEntry::where('hotel_id', $this->hotel->id)->orderBy('nsr')->get();
    expect($entries->pluck('nsr')->all())->toBe([1, 2, 3]);
    expect($entries[0]->hash)->toHaveLength(64)->and($entries[1]->hash)->not->toBe($entries[0]->hash);
    expect($entries[1]->hash)->toBe(\App\Domain\Workspace\Services\TimeEntryRecorder::hashFor(
        $this->hotel->id, 2, $entries[1]->user_id, $entries[1]->type, $entries[1]->punched_at->utc()->format('Y-m-d\TH:i:s\Z'), $entries[1]->source, $entries[0]->hash,
    ));

    // outra empresa tem a própria sequência
    $stranger = intMember($this->other);
    Sanctum::actingAs($stranger);
    $this->postJson("/api/v1/workspaces/{$this->other->slug}/time/punch")->assertCreated();
    expect(TimeEntry::where('hotel_id', $this->other->id)->value('nsr'))->toBe(1);
});

it('detects an edited or deleted record in the integrity check', function () {
    intPunch($this, $this->worker, '2026-11-10 08:00:00');
    intPunch($this, $this->peer, '2026-11-10 08:05:00');
    intPunch($this, $this->worker, '2026-11-10 12:00:00');

    Sanctum::actingAs($this->rh);
    $this->getJson("{$this->base}/time/integrity")->assertOk()->assertJsonPath('data.ok', true)->assertJsonPath('data.checked', 3);

    // adulteração direta no banco: muda a hora da 2ª marcação
    DB::table('time_entries')->where('hotel_id', $this->hotel->id)->where('nsr', 2)->update(['punched_at' => '2026-11-10 07:00:00']);
    $r = $this->getJson("{$this->base}/time/integrity")->assertOk()->assertJsonPath('data.ok', false)->assertJsonPath('data.first_broken_nsr', 2)->json('data');
    expect($r['reason'])->toContain('alterado');

    // desfaz e apaga a 2ª: a sequência ganha um buraco
    DB::table('time_entries')->where('hotel_id', $this->hotel->id)->where('nsr', 2)->update(['punched_at' => '2026-11-10 08:05:00']);
    $this->getJson("{$this->base}/time/integrity")->assertJsonPath('data.ok', true);
    DB::table('time_entries')->where('hotel_id', $this->hotel->id)->where('nsr', 2)->delete();
    $this->getJson("{$this->base}/time/integrity")->assertJsonPath('data.ok', false)->assertJsonPath('data.first_broken_nsr', 2);
});

it('gives a receipt to the person, their manager and RH, and to nobody else', function () {
    intPunch($this, $this->worker, '2026-11-10 08:00:00');
    $uuid = TimeEntry::where('user_id', $this->worker->id)->value('uuid');

    Sanctum::actingAs($this->worker);
    $r = $this->getJson("{$this->base}/time/entries/{$uuid}/receipt")->assertOk()->json('data');
    expect($r['company']['cnpj'])->toBe('12345678000199')->and($r['person']['name'])->toBe($this->worker->name)
        ->and($r['nsr'])->toBe(1)->and($r['hash'])->toHaveLength(64)->and($r['local_date'])->toBe('10/11/2026')->and($r['local_time'])->toBe('05:00:00'); // 08:00 UTC = 05:00 em Brasília

    foreach ([$this->manager, $this->rh] as $viewer) {
        Sanctum::actingAs($viewer);
        $this->getJson("{$this->base}/time/entries/{$uuid}/receipt")->assertOk();
    }
    Sanctum::actingAs($this->peer);
    $this->getJson("{$this->base}/time/entries/{$uuid}/receipt")->assertNotFound();
    Sanctum::actingAs($this->worker);
    $this->getJson("{$this->base}/time/integrity")->assertForbidden();
});

it('keeps manual entries and approved adjustments in the same chain', function () {
    intPunch($this, $this->worker, '2026-11-10 08:00:00');

    Sanctum::actingAs($this->manager);
    Carbon::setTestNow('2026-11-10 12:00:00');
    $this->postJson("{$this->base}/time/entries", ['user_uuid' => $this->worker->uuid, 'type' => 'out', 'punched_at' => '2026-11-10 08:30', 'note' => 'Esqueceu de bater'])->assertCreated();

    expect(TimeEntry::where('hotel_id', $this->hotel->id)->orderBy('nsr')->pluck('nsr')->all())->toBe([1, 2]);
    Sanctum::actingAs($this->rh);
    $this->getJson("{$this->base}/time/integrity")->assertJsonPath('data.ok', true)->assertJsonPath('data.checked', 2);
});
