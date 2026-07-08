<?php

/**
 * Job application endpoint tests.
 * Validates: Requirements 5.5, 5.6, 5.7, 5.8, 5.9
 */

use App\Models\Hotel;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────

/**
 * Creates an active job linked to a hotel whose admin is $adminUser.
 */
function makeActiveJob(?User $adminUser = null): Job
{
    $hotel = Hotel::factory()->create();

    if ($adminUser !== null) {
        $hotel->hotelUsers()->create(['user_id' => $adminUser->id, 'role' => 'admin']);
    }

    return Job::factory()->active()->create(['hotel_id' => $hotel->id]);
}

// ──────────────────────────────────────────────────────────────
// POST /api/v1/jobs/{uuid}/apply
// ──────────────────────────────────────────────────────────────

it('authenticated user can apply to an active job and gets 201', function () {
    $user = User::factory()->create();
    $job  = makeActiveJob();

    Sanctum::actingAs($user);

    $response = $this->postJson("/api/v1/jobs/{$job->uuid}/apply");

    $response->assertStatus(201)
        ->assertJson(['success' => true])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['uuid', 'status'],
        ]);
});

it('returns 401 when applying to a job without authentication', function () {
    $job = makeActiveJob();

    $response = $this->postJson("/api/v1/jobs/{$job->uuid}/apply");

    $response->assertStatus(401)->assertJson(['success' => false]);
});

// ──────────────────────────────────────────────────────────────
// Edge case: candidatura duplicada (Req 5.7)
// ──────────────────────────────────────────────────────────────

it('returns error when applying to the same job twice (duplicate application)', function () {
    // Validates: Requirement 5.7
    $user = User::factory()->create();
    $job  = makeActiveJob();

    Sanctum::actingAs($user);

    // First application — should succeed
    $this->postJson("/api/v1/jobs/{$job->uuid}/apply")->assertStatus(201);

    // Second application — must return error
    $response = $this->postJson("/api/v1/jobs/{$job->uuid}/apply");

    $response->assertStatus(422)
        ->assertJson(['success' => false])
        ->assertJsonFragment(['message' => 'Você já se candidatou a esta vaga.']);
});

// ──────────────────────────────────────────────────────────────
// Edge case: vaga pausada (Req 5.6)
// ──────────────────────────────────────────────────────────────

it('returns error when applying to a paused job', function () {
    // Validates: Requirement 5.6
    $user = User::factory()->create();
    $job  = Job::factory()->paused()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson("/api/v1/jobs/{$job->uuid}/apply");

    $response->assertStatus(422)
        ->assertJson(['success' => false])
        ->assertJsonFragment(['message' => 'Esta vaga não está disponível.']);
});

// ──────────────────────────────────────────────────────────────
// Edge case: vaga encerrada (Req 5.6)
// ──────────────────────────────────────────────────────────────

it('returns error when applying to a closed job', function () {
    // Validates: Requirement 5.6
    $user = User::factory()->create();
    $job  = Job::factory()->closed()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson("/api/v1/jobs/{$job->uuid}/apply");

    $response->assertStatus(422)
        ->assertJson(['success' => false])
        ->assertJsonFragment(['message' => 'Esta vaga não está disponível.']);
});

// ──────────────────────────────────────────────────────────────
// GET /api/v1/me/applications (Req 5.9)
// ──────────────────────────────────────────────────────────────

it('GET /me/applications returns paginated list of user applications', function () {
    $user = User::factory()->create();
    $job  = makeActiveJob();

    // Create an application directly via factory
    JobApplication::factory()->create(['job_id' => $job->id, 'user_id' => $user->id]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/me/applications');

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

it('returns 401 on GET /me/applications without authentication', function () {
    $response = $this->getJson('/api/v1/me/applications');

    $response->assertStatus(401)->assertJson(['success' => false]);
});

// ──────────────────────────────────────────────────────────────
// GET /api/v1/jobs/{uuid}/applications (Req 5.8)
// ──────────────────────────────────────────────────────────────

it('hotel admin can list applications for a job', function () {
    $admin = User::factory()->create();
    $job   = makeActiveJob($admin);

    // Create a candidate and their application
    $candidate = User::factory()->create();
    JobApplication::factory()->create(['job_id' => $job->id, 'user_id' => $candidate->id]);

    Sanctum::actingAs($admin);

    $response = $this->getJson("/api/v1/jobs/{$job->uuid}/applications");

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonStructure([
            'success',
            'message',
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

it('non-admin user gets 403 when listing applications for a job', function () {
    $other = User::factory()->create();
    $job   = makeActiveJob();   // no admin linked to $other

    Sanctum::actingAs($other);

    $response = $this->getJson("/api/v1/jobs/{$job->uuid}/applications");

    $response->assertStatus(403)->assertJson(['success' => false]);
});

it('returns 401 on GET /jobs/{uuid}/applications without authentication', function () {
    $job = makeActiveJob();

    $response = $this->getJson("/api/v1/jobs/{$job->uuid}/applications");

    $response->assertStatus(401)->assertJson(['success' => false]);
});

// ──────────────────────────────────────────────────────────────
// Property 13: Candidatura registra vínculo correto
// Validates: Requirements 5.5, 5.8, 5.9
// ──────────────────────────────────────────────────────────────

it('satisfies Property 13: after applying, job appears in /me/applications AND user appears in /jobs/{uuid}/applications', function () {
    // Feature: hotel-connect, Property 13: Candidatura registra vínculo correto
    // Validates: Requirements 5.5, 5.8, 5.9

    $admin     = User::factory()->create();
    $candidate = User::factory()->create();
    $job       = makeActiveJob($admin);

    // Step 1: Candidate applies
    Sanctum::actingAs($candidate);

    $apply = $this->postJson("/api/v1/jobs/{$job->uuid}/apply");
    $apply->assertStatus(201)->assertJson(['success' => true]);

    // Step 2: Job must appear in candidate's /me/applications
    $myApps = $this->getJson('/api/v1/me/applications');
    $myApps->assertStatus(200);

    $appliedJobUuids = collect($myApps->json('data'))
        ->pluck('job.uuid');

    expect($appliedJobUuids)->toContain($job->uuid);

    // Step 3: Candidate must appear in the job's /applications (as hotel admin)
    Sanctum::actingAs($admin);

    $jobApps = $this->getJson("/api/v1/jobs/{$job->uuid}/applications");
    $jobApps->assertStatus(200);

    $candidateUuids = collect($jobApps->json('data'))
        ->pluck('user.uuid');

    expect($candidateUuids)->toContain($candidate->uuid);
})->repeat(10);
