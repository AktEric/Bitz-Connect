<?php

namespace App\Domain\Course\Repositories;

use App\Domain\Course\DTOs\CreateCourseDTO;
use App\Models\Course;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface CourseRepositoryInterface
{
    /** Catálogo público de cursos publicados, com busca e filtro opcionais. */
    public function published(int $perPage = 12, ?string $category = null, ?string $search = null): LengthAwarePaginator;

    /** Cursos publicados de um criador. */
    public function byCreator(User $creator, int $perPage = 12): LengthAwarePaginator;

    /** Cursos em que o usuário está matriculado. */
    public function enrolledFor(User $user, int $perPage = 12): LengthAwarePaginator;

    public function create(CreateCourseDTO $dto, string $uuid): Course;
}
