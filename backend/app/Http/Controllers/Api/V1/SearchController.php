<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Search\DTOs\SearchQueryDTO;
use App\Domain\Search\Services\SearchService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchRequest;
use App\Http\Resources\HotelResource;
use App\Http\Resources\JobResource;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Handles full-text search endpoints for hotels, jobs, and users.
 * All endpoints require authentication (auth:sanctum middleware in routes).
 * Requirements: 6.1, 6.2, 6.3, 6.4, 6.5
 */
class SearchController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly SearchService $searchService) {}

    /**
     * GET /search/hotels?q=&per_page=
     * Search hotels by name, description, city, and state.
     * Requirements: 6.1, 6.5
     */
    public function searchHotels(SearchRequest $request): JsonResponse
    {
        $dto       = SearchQueryDTO::fromArray($request->validated());
        $paginator = $this->searchService->searchHotels($dto);

        $paginator->getCollection()->loadMissing('subscriptions'); // selo PRO sem N+1
        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($hotel) => new HotelResource($hotel))
        );

        return $this->paginated('Hotéis encontrados com sucesso.', $paginator);
    }

    /**
     * GET /search/jobs?q=&per_page=
     * Search active jobs by title and description.
     * Requirements: 6.2, 6.5
     */
    public function searchJobs(SearchRequest $request): JsonResponse
    {
        $dto       = SearchQueryDTO::fromArray($request->validated());
        $paginator = $this->searchService->searchJobs($dto);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($job) => new JobResource($job))
        );

        return $this->paginated('Vagas encontradas com sucesso.', $paginator);
    }

    /**
     * GET /search/users?q=&per_page=
     * Search users by name.
     * Requirements: 6.3, 6.5
     */
    public function searchUsers(SearchRequest $request): JsonResponse
    {
        $dto       = SearchQueryDTO::fromArray($request->validated());
        $paginator = $this->searchService->searchUsers($dto);

        $paginator->getCollection()->loadMissing('subscriptions'); // selo PRO sem N+1
        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($user) => new UserResource($user))
        );

        return $this->paginated('Usuários encontrados com sucesso.', $paginator);
    }
}
