<?php

namespace App\Domain\Job\Services;

use App\Domain\Job\DTOs\ApplyJobDTO;
use App\Domain\Job\DTOs\CreateJobDTO;
use App\Domain\Job\DTOs\UpdateJobDTO;
use App\Domain\Job\Repositories\JobRepositoryInterface;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Business logic for job management and applications.
 * Requirements: 5.1 – 5.9
 */
class JobService
{
    public function __construct(
        private readonly JobRepositoryInterface $jobRepository,
    ) {}

    /**
     * List active jobs paginated, optionally filtered by contract_type/modality/city/state.
     * Requirements: 5.1, 5.2
     */
    public function listActiveJobs(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->jobRepository->getAllActive($perPage, $filters);
    }

    /**
     * Create a new job linked to a hotel.
     * Requirements: 5.1, 5.2
     */
    public function createJob(CreateJobDTO $dto): Job
    {
        $uuid = (string) Str::uuid();

        return $this->jobRepository->create($dto, $uuid);
    }

    /**
     * Update an existing job's details.
     * Requirements: 5.3
     */
    public function updateJob(Job $job, UpdateJobDTO $dto): Job
    {
        return $this->jobRepository->update($job, $dto);
    }

    /**
     * Update the status of a job (active / paused / closed).
     * Requirements: 5.4
     */
    public function updateJobStatus(Job $job, string $status): Job
    {
        return $this->jobRepository->updateStatus($job, $status);
    }

    /**
     * Apply an authenticated user to an active job.
     * Throws 422 if the job is not active (Req 5.6).
     * Throws 422 if the user already applied (Req 5.7).
     * Requirements: 5.5, 5.6, 5.7
     */
    public function applyToJob(Job $job, ApplyJobDTO $dto): JobApplication
    {
        // Req 5.6 — job must be active
        if ($job->status !== 'active') {
            throw new \RuntimeException('job_not_active');
        }

        // Req 5.7 — prevent duplicate application
        if ($this->jobRepository->hasApplied($job->id, $dto->user_id)) {
            throw new \RuntimeException('already_applied');
        }

        $uuid = (string) Str::uuid();

        return $this->jobRepository->applyToJob($dto, $uuid);
    }

    /**
     * List paginated applications for a job.
     * Requirements: 5.8
     */
    public function getApplications(Job $job, int $perPage = 15): LengthAwarePaginator
    {
        return $this->jobRepository->getApplications($job, $perPage);
    }

    /**
     * List paginated applications for a user.
     * Requirements: 5.9
     */
    public function getUserApplications(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->jobRepository->getUserApplications($user, $perPage);
    }
}
