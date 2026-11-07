<?php

/** Busca global do Workspace: sem acento, só o que a pessoa pode ver. */

use App\Models\CalendarEvent;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use App\Models\WorkspacePost;
use App\Models\WorkspaceRequest;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function searchMember(Hotel $hotel, array $user = [], array $member = []): User
{
    $u = User::factory()->create($user);
    HotelUser::create(array_merge(['hotel_id' => $hotel->id, 'user_id' => $u->id, 'role' => 'employee', 'status' => 'approved'], $member));

    return $u;
}

beforeEach(function () {
    $this->hotel = Hotel::factory()->create();
    $this->base  = "/api/v1/workspaces/{$this->hotel->slug}";
    $this->ops   = Department::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => 'Operações']);
    $this->fin   = Department::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => 'Financeiro']);

    $this->rh     = searchMember($this->hotel, [], ['role' => 'admin']);
    $this->joao   = searchMember($this->hotel, ['name' => 'João da Silva'], ['department_id' => $this->ops->id, 'job_title' => 'Recepcionista']);
    $this->maria  = searchMember($this->hotel, ['name' => 'Maria Souza'], ['department_id' => $this->fin->id, 'job_title' => 'Analista financeiro']);
});

function searchFor($test, string $q)
{
    return $test->getJson("{$test->base}/search?q=" . urlencode($q));
}

it('finds people ignoring accents, case and by job title or area', function () {
    Sanctum::actingAs($this->maria);

    expect(array_column(searchFor($this, 'joao')->assertOk()->json('data.people'), 'name'))->toBe(['João da Silva']);
    expect(array_column(searchFor($this, 'JOÃO')->json('data.people'), 'name'))->toBe(['João da Silva']);
    expect(array_column(searchFor($this, 'recepcion')->json('data.people'), 'name'))->toBe(['João da Silva']);
    expect(array_column(searchFor($this, 'operacoes')->json('data.people'), 'name'))->toBe(['João da Silva']);
    expect(searchFor($this, 'xyz-nada')->json('data.people'))->toBe([]);
});

it('treats wildcard characters literally and validates the term', function () {
    Sanctum::actingAs($this->maria);
    expect(searchFor($this, '%%')->assertOk()->json('data.people'))->toBe([]);
    searchFor($this, 'a')->assertStatus(422);
    $this->getJson("{$this->base}/search")->assertStatus(422);
});

it('only returns posts and events the person is allowed to see', function () {
    WorkspacePost::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'author_id' => $this->rh->id, 'body' => 'Escala de feriado para todos', 'pinned' => false]);
    WorkspacePost::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'author_id' => $this->rh->id, 'department_id' => $this->ops->id, 'body' => 'Escala secreta de Operações', 'pinned' => false]);
    CalendarEvent::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'title' => 'Treino de escala', 'starts_on' => '2026-11-12', 'department_id' => $this->ops->id, 'created_by' => $this->rh->id]);

    Sanctum::actingAs($this->maria); // do Financeiro
    $r = searchFor($this, 'escala')->assertOk();
    expect(array_column($r->json('data.posts'), 'excerpt'))->toBe(['Escala de feriado para todos'])->and($r->json('data.events'))->toBe([]);

    Sanctum::actingAs($this->joao); // de Operações
    $r = searchFor($this, 'escala')->assertOk();
    expect($r->json('data.posts'))->toHaveCount(2)->and($r->json('data.events'))->toHaveCount(1);

    Sanctum::actingAs($this->rh);
    expect(searchFor($this, 'escala')->json('data.posts'))->toHaveCount(2);
});

it('does not leak other people\'s requests', function () {
    WorkspaceRequest::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'requester_id' => $this->maria->id, 'type' => 'document', 'title' => 'Declaração de renda', 'status' => 'pending']);

    Sanctum::actingAs($this->maria);
    expect(searchFor($this, 'declaracao')->json('data.requests'))->toHaveCount(1);
    Sanctum::actingAs($this->joao);
    expect(searchFor($this, 'declaracao')->json('data.requests'))->toBe([]);
    Sanctum::actingAs($this->rh);
    expect(searchFor($this, 'declaracao')->json('data.requests'))->toHaveCount(1);
});

it('keeps the search to company members', function () {
    Sanctum::actingAs(User::factory()->create());
    searchFor($this, 'joao')->assertForbidden();
});
