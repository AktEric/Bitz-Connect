<?php

namespace App\Domain\Search\Services;

use App\Domain\Search\DTOs\SearchQueryDTO;
use App\Models\Hotel;
use App\Models\Job;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Performs full-text search across hotels, jobs, and users using
 * PostgreSQL GIN indexes with unaccent support.
 * Requirements: 6.1, 6.2, 6.3, 6.5
 */
class SearchService
{
    /**
     * Search hotels by name, description, city, and state.
     * Requirements: 6.1, 6.5
     */
    public function searchHotels(SearchQueryDTO $dto): LengthAwarePaginator
    {
        $tsQuery = $this->buildTsQuery($dto->query);

        return Hotel::whereNull('deleted_at')
            ->whereRaw(
                "to_tsvector('portuguese', unaccent(coalesce(name,'')) || ' ' || unaccent(coalesce(description,'')) || ' ' || unaccent(coalesce(city,'')) || ' ' || unaccent(coalesce(state,''))) @@ plainto_tsquery('portuguese', unaccent(?))",
                [$dto->query]
            )
            ->orderByRaw(
                "ts_rank(to_tsvector('portuguese', unaccent(coalesce(name,'')) || ' ' || unaccent(coalesce(description,'')) || ' ' || unaccent(coalesce(city,'')) || ' ' || unaccent(coalesce(state,''))), plainto_tsquery('portuguese', unaccent(?))) DESC",
                [$dto->query]
            )
            ->paginate($dto->perPage);
    }

    /**
     * Search active jobs by title and description.
     * Requirements: 6.2, 6.5
     */
    public function searchJobs(SearchQueryDTO $dto): LengthAwarePaginator
    {
        return Job::whereNull('deleted_at')
            ->where('status', 'active')
            ->whereRaw(
                "to_tsvector('portuguese', unaccent(coalesce(title,'')) || ' ' || unaccent(coalesce(description,''))) @@ plainto_tsquery('portuguese', unaccent(?))",
                [$dto->query]
            )
            ->orderByRaw(
                "ts_rank(to_tsvector('portuguese', unaccent(coalesce(title,'')) || ' ' || unaccent(coalesce(description,''))), plainto_tsquery('portuguese', unaccent(?))) DESC",
                [$dto->query]
            )
            ->with('hotel')
            ->paginate($dto->perPage);
    }

    /**
     * Search users by name.
     * Requirements: 6.3, 6.5
     */
    public function searchUsers(SearchQueryDTO $dto): LengthAwarePaginator
    {
        return User::whereNull('deleted_at')
            ->whereRaw(
                "to_tsvector('portuguese', unaccent(coalesce(name,''))) @@ plainto_tsquery('portuguese', unaccent(?))",
                [$dto->query]
            )
            ->orderByRaw(
                "ts_rank(to_tsvector('portuguese', unaccent(coalesce(name,''))), plainto_tsquery('portuguese', unaccent(?))) DESC",
                [$dto->query]
            )
            ->paginate($dto->perPage);
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    /**
     * Build a safe tsquery string from a raw input.
     * Using plainto_tsquery handles multi-word phrases without operator injection.
     */
    private function buildTsQuery(string $query): string
    {
        return trim($query);
    }
}
