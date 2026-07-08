<?php

namespace App\Domain\Course\Repositories;

use App\Domain\Course\DTOs\CreateCourseDTO;
use App\Models\Course;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentCourseRepository implements CourseRepositoryInterface
{
    public function published(int $perPage = 12, ?string $category = null, ?string $search = null): LengthAwarePaginator
    {
        return Course::query()
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

    public function byCreator(User $creator, int $perPage = 12): LengthAwarePaginator
    {
        return $creator->courses()
            ->published()
            ->with('user.subscriptions')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->latest()
            ->paginate($perPage);
    }

    public function enrolledFor(User $user, int $perPage = 12): LengthAwarePaginator
    {
        return Course::query()
            ->whereIn('id', $user->courseEnrollments()->select('course_id'))
            ->with('user.subscriptions')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->latest()
            ->paginate($perPage);
    }

    public function create(CreateCourseDTO $dto, string $uuid): Course
    {
        return Course::create([
            'uuid'        => $uuid,
            'user_id'     => $dto->user_id,
            'title'       => $dto->title,
            'description' => $dto->description,
            'category'    => $dto->category,
            'level'       => $dto->level,
            'price_cents' => $dto->price_cents,
            'status'      => 'draft',
        ]);
    }
}
