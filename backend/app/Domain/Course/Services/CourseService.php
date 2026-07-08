<?php

namespace App\Domain\Course\Services;

use App\Domain\Course\DTOs\CreateCourseDTO;
use App\Domain\Course\Repositories\CourseRepositoryInterface;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Regras de negócio de cursos e aulas. A autorização (gate create_courses e
 * propriedade do curso) é verificada no controller.
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
class CourseService
{
    public function __construct(
        private readonly CourseRepositoryInterface $repository,
    ) {}

    public function catalog(int $perPage = 12, ?string $category = null, ?string $search = null): LengthAwarePaginator
    {
        return $this->repository->published($perPage, $category, $search);
    }

    public function byCreator(User $creator, int $perPage = 12): LengthAwarePaginator
    {
        return $this->repository->byCreator($creator, $perPage);
    }

    public function enrolledFor(User $user, int $perPage = 12): LengthAwarePaginator
    {
        return $this->repository->enrolledFor($user, $perPage);
    }

    public function create(CreateCourseDTO $dto): Course
    {
        return $this->repository->create($dto, (string) Str::uuid());
    }

    public function update(Course $course, array $data): Course
    {
        $course->update(array_filter([
            'title'       => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'category'    => $data['category'] ?? null,
            'level'       => $data['level'] ?? null,
            'price_cents' => $data['price_cents'] ?? null,
        ], fn ($v) => $v !== null));

        return $course->fresh();
    }

    public function publish(Course $course): Course
    {
        $course->update(['status' => 'published']);

        return $course->fresh();
    }

    // ── Aulas ────────────────────────────────────────────────

    public function addLesson(Course $course, array $data): CourseLesson
    {
        return DB::transaction(function () use ($course, $data) {
            $position = $data['position'] ?? ((int) $course->lessons()->max('position') + 1);

            $lesson = $course->lessons()->create([
                'uuid'             => (string) Str::uuid(),
                'title'            => $data['title'],
                'description'      => $data['description'] ?? null,
                'content'          => $data['content'] ?? null,
                'video_url'        => $data['video_url'] ?? null,
                'position'         => $position,
                'duration_seconds' => $data['duration_seconds'] ?? null,
            ]);
            $course->increment('lessons_count');

            return $lesson;
        });
    }

    public function updateLesson(CourseLesson $lesson, array $data): CourseLesson
    {
        $lesson->update(array_filter([
            'title'            => $data['title'] ?? null,
            'description'      => $data['description'] ?? null,
            'content'          => $data['content'] ?? null,
            'video_url'        => $data['video_url'] ?? null,
            'position'         => $data['position'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
        ], fn ($v) => $v !== null));

        return $lesson->fresh();
    }

    public function deleteLesson(CourseLesson $lesson): void
    {
        DB::transaction(function () use ($lesson) {
            $course = $lesson->course;
            $lesson->delete();
            if ($course->lessons_count > 0) {
                $course->decrement('lessons_count');
            }
        });
    }

    // ── Matrícula ────────────────────────────────────────────

    /** Matrícula gratuita (idempotente). Retorna true se criou uma nova. */
    public function enroll(Course $course, User $user): bool
    {
        if ($course->enrollments()->where('user_id', $user->id)->exists()) {
            return false;
        }

        return DB::transaction(function () use ($course, $user) {
            $course->enrollments()->create(['user_id' => $user->id]);
            $course->increment('enrollments_count');

            return true;
        });
    }

    public function isEnrolled(Course $course, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $course->enrollments()->where('user_id', $user->id)->exists();
    }
}
