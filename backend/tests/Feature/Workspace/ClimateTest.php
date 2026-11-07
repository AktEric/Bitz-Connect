<?php

use App\Models\Department;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->hotel = Hotel::factory()->create();
    $this->deptA = Department::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => 'Recepção']);
    $this->deptB = Department::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => 'Cozinha']);
    $mk = fn (string $role, ?Department $d = null, ?string $access = null) => tap(User::factory()->create(), fn ($u) => HotelUser::create(
        ['hotel_id' => $this->hotel->id, 'user_id' => $u->id, 'role' => $role, 'access_role' => $access, 'status' => 'approved', 'department_id' => $d?->id]));
    $this->rh = $mk('admin', null, 'hr');
    $this->director = $mk('employee', null, 'director');
    $this->staffA = collect(range(1, 5))->map(fn () => $mk('employee', $this->deptA));
    $this->staffB = collect(range(1, 2))->map(fn () => $mk('employee', $this->deptB));
    $this->base = "/api/v1/workspaces/{$this->hotel->slug}";
});

function openSurvey($t): string
{
    Sanctum::actingAs($t->rh);
    $t->postJson("{$t->base}/climate", ['title' => 'Clima do 4º trimestre'])->assertCreated();

    return $t->getJson("{$t->base}/climate")->json('data.items.0.uuid');
}

function respond($t, User $u, string $uuid, int $nps = 9, string $comment = ''): void
{
    Sanctum::actingAs($u);
    $t->postJson("{$t->base}/climate/{$uuid}/answer", ['scores' => ['nps' => $nps, 'recognition' => 4, 'clarity' => 5, 'manager' => 3], 'comment' => $comment])->assertCreated();
}

it('stores answers without any link to the person and allows only one answer each', function () {
    $uuid = openSurvey($this);
    respond($this, $this->staffA[0], $uuid, 10, 'Gosto muito daqui');

    Sanctum::actingAs($this->staffA[0]);
    $this->postJson("{$this->base}/climate/{$uuid}/answer", ['scores' => ['nps' => 1, 'recognition' => 1, 'clarity' => 1, 'manager' => 1]])->assertStatus(409);

    $cols = collect(DB::select("select column_name from information_schema.columns where table_name = 'climate_answers'"))->pluck('column_name')->all();
    expect($cols)->not->toContain('user_id')->and($cols)->not->toContain('created_at');
    expect(DB::table('climate_participations')->where('user_id', $this->staffA[0]->id)->count())->toBe(1);
    expect(DB::table('climate_answers')->count())->toBe(1);

    // valida faixas
    Sanctum::actingAs($this->staffA[1]);
    $this->postJson("{$this->base}/climate/{$uuid}/answer", ['scores' => ['nps' => 11, 'recognition' => 4, 'clarity' => 5, 'manager' => 3]])->assertUnprocessable();
});

it('hides results below the minimum, then shows overall and only large departments', function () {
    $uuid = openSurvey($this);
    foreach ($this->staffA->take(4) as $u) { respond($this, $u, $uuid); }

    Sanctum::actingAs($this->rh);
    expect($this->getJson("{$this->base}/climate/{$uuid}/results")->json('data.hidden'))->toBeTrue();

    respond($this, $this->staffA[4], $uuid, 3, 'Falta reconhecimento');   // 5ª resposta (Recepção)
    respond($this, $this->staffB[0], $uuid, 10);
    respond($this, $this->staffB[1], $uuid, 10);                           // Cozinha tem só 2 respostas

    Sanctum::actingAs($this->director);
    $r = $this->getJson("{$this->base}/climate/{$uuid}/results")->assertOk()->json('data');
    expect($r['hidden'])->toBeFalse()->and($r['summary']['responses'])->toBe(7);
    // 6 promotores (9,9,9,9,10,10 => 6) e 1 detrator (3): (6-1)/7 = 71
    expect($r['summary']['enps'])->toBe(71)->and($r['participation'])->toBe(['answered' => 7, 'eligible' => 9]);
    expect(collect($r['departments'])->pluck('name')->all())->toBe(['Recepção']);   // Cozinha (n=2) suprimida
    expect($r['comments'])->toBe(['Falta reconhecimento']);
});

it('keeps results from regular employees and lets only RH open or close surveys', function () {
    $uuid = openSurvey($this);
    Sanctum::actingAs($this->staffA[0]);
    $this->getJson("{$this->base}/climate/{$uuid}/results")->assertNotFound();
    $this->postJson("{$this->base}/climate", ['title' => 'Minha pesquisa'])->assertForbidden();
    $this->postJson("{$this->base}/climate/{$uuid}/close")->assertNotFound();
    expect($this->getJson("{$this->base}/climate")->json('data.items.0.responses'))->toBeNull();

    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/climate", ['title' => 'Outra pesquisa'])->assertStatus(409);  // já há uma aberta
    $this->postJson("{$this->base}/climate/{$uuid}/close")->assertOk();
    Sanctum::actingAs($this->staffA[0]);
    $this->postJson("{$this->base}/climate/{$uuid}/answer", ['scores' => ['nps' => 8, 'recognition' => 4, 'clarity' => 5, 'manager' => 3]])->assertNotFound();
});

it('lets RH choose the questions and validates answers against them', function () {
    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/climate", ['title' => 'Pulso rápido', 'questions' => [
        ['label' => 'De 0 a 10, indicaria a empresa?', 'type' => 'nps'],
        ['label' => 'Tenho as ferramentas que preciso.', 'type' => 'scale'],
    ]])->assertCreated();
    $item = $this->getJson("{$this->base}/climate")->json('data.items.0');
    expect(collect($item['questions'])->pluck('key')->all())->toBe(['q1', 'q2']);

    Sanctum::actingAs($this->staffA[0]);
    $this->postJson("{$this->base}/climate/{$item['uuid']}/answer", ['scores' => ['q1' => 9]])->assertUnprocessable();       // falta q2
    $this->postJson("{$this->base}/climate/{$item['uuid']}/answer", ['scores' => ['q1' => 9, 'q2' => 4]])->assertCreated();

    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/climate/{$item['uuid']}/close")->assertOk();
    $this->postJson("{$this->base}/climate", ['title' => 'Duas de indicação', 'questions' => [
        ['label' => 'Primeira de indicação aqui', 'type' => 'nps'], ['label' => 'Segunda de indicação aqui', 'type' => 'nps'],
    ]])->assertUnprocessable();
});

it('compares with the previous survey by question text and lists the history', function () {
    $first = openSurvey($this);
    foreach ($this->staffA as $u) { respond($this, $u, $first, 6); }            // 5 respostas: 5 detratores => eNPS -100
    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/climate/{$first}/close")->assertOk();

    $second = openSurvey($this);
    foreach ($this->staffA as $u) { respond($this, $u, $second, 10); }          // 5 promotores => eNPS 100
    Sanctum::actingAs($this->rh);
    $r = $this->getJson("{$this->base}/climate/{$second}/results")->assertOk()->json('data');

    expect($r['comparison']['enps'])->toBe(['current' => 100, 'previous' => -100, 'delta' => 200]);
    expect(collect($r['comparison']['items'])->pluck('delta')->unique()->all())->toBe([0]);   // mesmas notas nas afirmações
    expect(collect($r['history'])->pluck('current')->all())->toBe([false, true]);

    $firstResults = $this->getJson("{$this->base}/climate/{$first}/results")->json('data');
    expect($firstResults['comparison'])->toBeNull();
});
