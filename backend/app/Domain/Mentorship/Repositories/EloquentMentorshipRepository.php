<?php

namespace App\Domain\Mentorship\Repositories;

use App\Domain\Mentorship\DTOs\CreateMentorshipDTO;
use App\Models\Mentorship;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentMentorshipRepository implements MentorshipRepositoryInterface
{
    public function published(int $perPage = 12, ?string $category = null, ?string $search = null): LengthAwarePaginator
    {
        return Mentorship::query()
            ->published()
            ->with('user.subscriptions')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($search, fn ($q) => $q->where(fn ($sub) => $sub
                ->where('title', 'ilike', "%{$search}%")
                ->orWhere('description', 'ilike', "%{$search}%")))
            ->latest()
            ->paginate($perPage);
    }

    public function byMentor(User $mentor, int $perPage = 12): LengthAwarePaginator
    {
        return $mentor->mentorships()
            ->published()
            ->with('user.subscriptions')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->latest()
            ->paginate($perPage);
    }

    public function create(CreateMentorshipDTO $dto, string $uuid): Mentorship
    {
        return Mentorship::create([
            'uuid'             => $uuid,
            'user_id'          => $dto->user_id,
            'title'            => $dto->title,
            'description'      => $dto->description,
            'category'         => $dto->category,
            'duration_minutes' => $dto->duration_minutes,
            'session_type'     => $dto->session_type,
            'price_cents'      => $dto->price_cents,
            'status'           => 'draft',
        ]);
    }
}
