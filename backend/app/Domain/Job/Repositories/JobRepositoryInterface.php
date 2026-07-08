<?php

namespace App\Domain\Job\Repositories;

use App\Domain\Job\DTOs\ApplyJobDTO;
use App\Domain\Job\DTOs\CreateJobDTO;
use App\Domain\Job\DTOs\UpdateJobDTO;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Contract for job data access.
 * Requirements: 5.1 – 5.9
 */
interface JobRepositoryInterface
{
    /** List active jobs paginated (with hotel eager-loaded). */
    public function getAllActive(int $perPage = 15): LengthAwarePaginator;

    /** Find a job by its UUID. */
    public function findByUuid(string $uuid): ?Job;

    /** Create a new job record. */
    public function create(CreateJobDTO $dto, string $uuid): Job;

    /** Update an existing job with the given DTO. */
    public function update(Job $job, UpdateJobDTO $dto): Job;

    /** Update only the status field of a job. */
    public function updateStatus(Job $job, string $status): Job;

    /** List paginated applications for a job (with user eager-loaded). */
    public function getApplications(Job $job, int $perPage = 15): LengthAwarePaginator;

    /** List paginated applications for a user (with job and hotel eager-loaded). */
    public function getUserApplications(User $user, int $perPage = 15): LengthAwarePaginator;

    /** Check whether a user has already applied to the given job. */
    public function hasApplied(int $jobId, int $userId): bool;

    /** Persist a new application. */
    public function applyToJob(ApplyJobDTO $dto, string $uuid): JobApplication;
}
