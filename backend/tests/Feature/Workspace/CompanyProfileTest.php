<?php

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use App\Models\WorkspacePost;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 12:00:00');
    $this->hotel = Hotel::factory()->create(['name' => 'Hotel Aurora']);
    $dept = $this->hotel->departments()->create(['uuid' => (string) Illuminate\Support\Str::uuid(), 'name' => 'Recepção']);
    $mk = fn (string $role, array $extra = []) => tap(User::factory()->create(), fn ($u) => HotelUser::create(
        ['hotel_id' => $this->hotel->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'approved'] + $extra));
    $this->rh     = $mk('admin', ['access_role' => 'hr']);
    $this->boss   = $mk('employee', ['job_title' => 'Gerente']);
    $this->hbd    = $mk('employee', ['birth_date' => '1990-11-10', 'job_title' => 'Recepcionista', 'department_id' => $dept->id]);
    $this->newbie = $mk('employee', ['hired_at' => '2026-11-10', 'job_title' => 'Camareira', 'department_id' => $dept->id, 'manager_id' => $this->boss->id]);
    $this->base   = "/api/v1/workspaces/{$this->hotel->slug}";
});
afterEach(fn () => Carbon::setTestNow());

it('congratulates and welcomes as the company profile, mentioning person, role and sector', function () {
    $this->artisan('workspace:birthday-posts')->assertSuccessful();
    $this->artisan('workspace:welcome-posts')->assertSuccessful();
    $this->artisan('workspace:welcome-posts')->assertSuccessful(); // idempotente

    expect(WorkspacePost::where('kind', 'welcome')->count())->toBe(1);

    Sanctum::actingAs($this->boss);
    $items = collect($this->getJson("{$this->base}/feed")->assertOk()->json('data.items'));
    $b = $items->firstWhere('kind', 'birthday');
    $w = $items->firstWhere('kind', 'welcome');

    expect($b['author']['is_company'])->toBeTrue()->and($b['author']['name'])->toBe('Hotel Aurora')
        ->and($b['body'])->toContain('Recepcionista')->toContain('setor Recepção')
        ->and($b['mentions'])->toHaveKey(strtolower($this->hbd->uuid))
        ->and($b['subject']['uuid'])->toBe($this->hbd->uuid);
    expect($w['author']['is_company'])->toBeTrue()->and($w['body'])->toContain('time de Recepção')->toContain('Camareira')
        ->and($w['subject']['uuid'])->toBe($this->newbie->uuid)->and($w['can_delete'])->toBeFalse();
});

it('lets only admins publish as the company', function () {
    Sanctum::actingAs($this->rh);
    $this->postJson("{$this->base}/feed", ['body' => 'Aviso oficial', 'as_company' => true])->assertCreated()->assertJsonPath('data.author.is_company', true);

    Sanctum::actingAs($this->boss);
    $r = $this->postJson("{$this->base}/feed", ['body' => 'Tentando', 'as_company' => true])->assertCreated();
    expect($r->json('data.author.is_company') ?? false)->toBeFalse()->and($r->json('data.author.uuid'))->toBe($this->boss->uuid);
});
