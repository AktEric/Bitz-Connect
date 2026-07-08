<?php

namespace App\Domain\Video\Services;

use App\Domain\Video\DTOs\CreateVideoDTO;
use App\Domain\Video\Repositories\VideoRepositoryInterface;
use App\Models\User;
use App\Models\Video;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Regras de negócio de vídeos.
 * A autorização de publicação (entitlement publish_videos) é feita no controller
 * via Gate; aqui tratamos apenas a persistência e leitura.
 *
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
class VideoService
{
    public function __construct(
        private readonly VideoRepositoryInterface $repository,
    ) {}

    public function feed(int $perPage = 12, ?string $category = null): LengthAwarePaginator
    {
        return $this->repository->feed($perPage, $category);
    }

    public function byCreator(User $creator, int $perPage = 12): LengthAwarePaginator
    {
        return $this->repository->byCreator($creator, $perPage);
    }

    public function create(CreateVideoDTO $dto): Video
    {
        return $this->repository->create($dto, (string) Str::uuid());
    }

    public function registerView(Video $video): void
    {
        $this->repository->incrementViews($video);
    }
}
