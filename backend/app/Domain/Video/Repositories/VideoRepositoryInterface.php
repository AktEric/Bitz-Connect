<?php

namespace App\Domain\Video\Repositories;

use App\Domain\Video\DTOs\CreateVideoDTO;
use App\Models\User;
use App\Models\Video;
use Illuminate\Pagination\LengthAwarePaginator;

interface VideoRepositoryInterface
{
    /** Feed público paginado, opcionalmente filtrado por categoria. */
    public function feed(int $perPage = 12, ?string $category = null): LengthAwarePaginator;

    /** Vídeos publicados de um criador (paginado). */
    public function byCreator(User $creator, int $perPage = 12): LengthAwarePaginator;

    public function create(CreateVideoDTO $dto, string $uuid): Video;

    /** Incrementa atomicamente o contador de visualizações. */
    public function incrementViews(Video $video): void;
}
