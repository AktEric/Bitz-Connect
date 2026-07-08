<?php

namespace App\Domain\Job\Repositories;

use App\Domain\Job\DTOs\ApplyJobDTO;
use App\Domain\Job\DTOs\CreateJobDTO;
use App\Domain\Job\DTOs\UpdateJobDTO;
use App\Models\Hotel;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Eloquent implementation of JobRepositoryInterface.
 * Requirements: 5.1 – 5.9
 */
class EloquentJobRepository implements JobRepositoryInterface
{
    public function getAllActive(int $perPage = 15): LengthAwarePaginator
    {
        return Job::where('status', 'active')
            ->with('hotel')
            ->latest()
            ->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?Job
    {
        return Job::where('uuid', $uuid)->first();
    }

    public function create(CreateJobDTO $dto, string $uuid): Job
    {
        $hotel = Hotel::where('uuid', $dto->hotel_uuid)->firstOrFail();

        return Job::create([
            'uuid'          => $uuid,
            'hotel_id'      => $hotel->id,
            'title'         => $dto->title,
            'description'   => $dto->description,
            'requirements'  => $dto->requirements,
            'benefits'      => $dto->benefits,
            'contract_type' => $dto->contract_type,
            'modality'      => $dto->modality,
            'city'          => $dto->city,
            'state'         => $dto->state,
            'salary_range'  => $dto->salary_range,
            'status'        => 'active',
        ]);
    }

    public function update(Job $job, UpdateJobDTO $dto): Job
    {
        $fields = array_filter([
            'title'         => $dto->title,
            'description'   => $dto->description,
            'requirements'  => $dto->requirements,
            'benefits'      => $dto->benefits,
            'contract_type' => $dto->contract_type,
            'modality'      => $dto->modality,
            'city'          => $dto->city,
            'state'         => $dto->state,
            'salary_range'  => $dto->salary_range,
        ], fn ($v) => $v !== null);

        $job->fill($fields)->save();

        return $job->fresh();
    }

    public function updateStatus(Job $job, string $status): Job
    {
        $job->update(['status' => $status]);

        return $job->fresh();
    }

    public function getApplications(Job $job, int $perPage = 15): LengthAwarePaginator
    {
        return $job->applications()
            ->with('user')
            ->latest()
            ->paginate($perPage);
    }

    public function getUserApplications(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return JobApplication::where('user_id', $user->id)
            ->with('job.hotel')
            ->latest()
            ->paginate($perPage);
    }

    public function hasApplied(int $jobId, int $userId): bool
    {
        return JobApplication::where('job_id', $jobId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function applyToJob(ApplyJobDTO $dto, string $uuid): JobApplication
    {
        return JobApplication::create([
            'uuid'         => $uuid,
            'job_id'       => $dto->job_id,
            'user_id'      => $dto->user_id,
            'cover_letter' => $dto->cover_letter,
            'status'       => 'pending',
        ]);
    }
}
