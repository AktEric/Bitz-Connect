<?php

namespace App\Domain\Story\Services;

use App\Domain\Story\DTOs\CreateStoryDTO;
use App\Domain\Story\Repositories\StoryRepositoryInterface;
use App\Models\Story;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Regras de negócio de stories.
 * A autorização de publicação (entitlement publish_stories + dono do
 * job/mentorship) é feita no controller; aqui tratamos persistência e leitura.
 *
 * Sprint: monetization-hotelconnect-pro (Stories)
 */
class StoryService
{
    public function __construct(
        private readonly StoryRepositoryInterface $repository,
    ) {}

    public function feed(): Collection
    {
        return $this->repository->activeFeed();
    }

    public function create(CreateStoryDTO $dto): Story
    {
        return $this->repository->create($dto, (string) Str::uuid());
    }

    public function registerView(Story $story): void
    {
        $this->repository->incrementViews($story);
    }

    public function delete(Story $story): void
    {
        $story->delete();
    }
}
