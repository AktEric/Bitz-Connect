<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Job\DTOs\ApplyJobDTO;
use App\Domain\Job\DTOs\CreateJobDTO;
use App\Domain\Job\DTOs\UpdateJobDTO;
use App\Domain\Job\Services\JobService;
use App\Events\Job\JobApplicationCreated;
use App\Events\Job\JobApplicationStatusChanged;
use App\Events\Job\JobPublished;
use App\Http\Controllers\Controller;
use App\Http\Requests\Job\ApplyJobRequest;
use App\Http\Requests\Job\CreateJobRequest;
use App\Http\Requests\Job\UpdateJobRequest;
use App\Http\Requests\Job\UpdateJobStatusRequest;
use App\Http\Resources\JobApplicationResource;
use App\Http\Resources\JobResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Job;
use App\Models\JobApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Handles job and job application endpoints.
 * Requirements: 5.1 – 5.9
 */
class JobController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly JobService $jobService,
    ) {}

    /**
     * GET /jobs
     * List active jobs (paginated).
     * Requirements: 5.1, 5.2
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['contract_type', 'modality', 'city', 'state']);

        $paginator = $this->jobService->listActiveJobs(15, $filters);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($job) => new JobResource($job))
        );

        return $this->paginated('Vagas carregadas com sucesso.', $paginator);
    }

    /**
     * POST /jobs
     * Publish a new job (HotelAdmin only).
     * Requirements: 5.1
     */
    public function store(CreateJobRequest $request): JsonResponse
    {
        $this->authorize('create', Job::class);

        $user = $request->user();

        // Ensure the authenticated user is an admin of the given hotel
        if (! $user->isHotelAdminOfUuid($request->validated('hotel_uuid'))) {
            return $this->forbidden('Você não é administrador deste hotel.');
        }

        $job = $this->jobService->createJob(
            CreateJobDTO::fromArray($request->validated()),
        );

        // Fire event so connections/followers can be notified
        JobPublished::dispatch($job->load('hotel'));

        return $this->created('Vaga publicada com sucesso.', new JobResource($job->load('hotel')));
    }

    /**
     * GET /jobs/{uuid}
     * Show a single job.
     * Requirements: 5.2
     */
    public function show(Job $job): JsonResponse
    {
        $this->authorize('view', $job);

        return $this->success('Vaga carregada com sucesso.', new JobResource($job->load('hotel')));
    }

    /**
     * PUT /jobs/{uuid}
     * Update job details (HotelAdmin only).
     * Requirements: 5.3
     */
    public function update(UpdateJobRequest $request, Job $job): JsonResponse
    {
        $this->authorize('update', $job);

        $job = $this->jobService->updateJob(
            $job,
            UpdateJobDTO::fromArray($request->validated()),
        );

        return $this->success('Vaga atualizada com sucesso.', new JobResource($job->load('hotel')));
    }

    /**
     * PATCH /jobs/{uuid}/status
     * Update job status (HotelAdmin only).
     * Requirements: 5.4
     */
    public function updateStatus(UpdateJobStatusRequest $request, Job $job): JsonResponse
    {
        $this->authorize('update', $job);

        $job = $this->jobService->updateJobStatus($job, $request->validated('status'));

        return $this->success('Status da vaga atualizado com sucesso.', new JobResource($job));
    }

    /**
     * POST /jobs/{uuid}/apply
     * Apply to a job (authenticated user).
     * Requirements: 5.5, 5.6, 5.7
     */
    public function apply(ApplyJobRequest $request, Job $job): JsonResponse
    {
        $user = $request->user();

        $dto = ApplyJobDTO::fromArray($request->validated(), $job->id, $user->id);

        try {
            $application = $this->jobService->applyToJob($job, $dto);
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'job_not_active'  => $this->error('Esta vaga não está disponível.', [], 422),
                'already_applied' => $this->error('Você já se candidatou a esta vaga.', [], 422),
                default           => throw $e,
            };
        }

        // Fire event to notify hotel admins
        JobApplicationCreated::dispatch($application, $job, $user);

        return $this->created(
            'Candidatura registrada com sucesso.',
            new JobApplicationResource($application->load('job.hotel')),
        );
    }

    /**
     * GET /jobs/{uuid}/applications
     * List applications for a job (HotelAdmin only).
     * Requirements: 5.8
     */
    public function applications(Request $request, Job $job): JsonResponse
    {
        $this->authorize('viewApplications', $job);

        $paginator = $this->jobService->getApplications($job);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($app) => new JobApplicationResource($app))
        );

        return $this->paginated('Candidaturas carregadas com sucesso.', $paginator);
    }

    /**
     * PATCH /jobs/{uuid}/applications/{application}/status
     * Update a job application's status (HotelAdmin only).
     * Requirements: 5.8
     */
    public function updateApplicationStatus(Request $request, Job $job, JobApplication $application): JsonResponse
    {
        $this->authorize('viewApplications', $job);

        if ($application->job_id !== $job->id) {
            return $this->notFound('Candidatura não encontrada para esta vaga.');
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'viewed', 'rejected', 'hired'])],
        ]);

        $oldStatus = $application->status;
        $application->update(['status' => $data['status']]);

        JobApplicationStatusChanged::dispatch($application, $job, $oldStatus, $data['status']);

        return $this->success(
            'Status da candidatura atualizado com sucesso.',
            new JobApplicationResource($application->load('job.hotel', 'user')),
        );
    }

    /**
     * GET /me/applications
     * List the authenticated user's applications.
     * Requirements: 5.9
     */
    public function myApplications(Request $request): JsonResponse
    {
        $paginator = $this->jobService->getUserApplications($request->user());

        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($app) => new JobApplicationResource($app))
        );

        return $this->paginated('Suas candidaturas foram carregadas com sucesso.', $paginator);
    }
}
