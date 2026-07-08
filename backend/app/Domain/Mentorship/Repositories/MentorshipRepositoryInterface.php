<?php

namespace App\Domain\Mentorship\Repositories;

use App\Domain\Mentorship\DTOs\CreateMentorshipDTO;
use App\Models\Mentorship;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface MentorshipRepositoryInterface
{
    public function published(int $perPage = 12, ?string $category = null, ?string $search = null): LengthAwarePaginator;

    public function byMentor(User $mentor, int $perPage = 12): LengthAwarePaginator;

    public function create(CreateMentorshipDTO $dto, string $uuid): Mentorship;
}
