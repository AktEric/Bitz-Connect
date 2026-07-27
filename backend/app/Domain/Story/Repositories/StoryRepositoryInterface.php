<?php

namespace App\Domain\Story\Repositories;

use App\Domain\Story\DTOs\CreateStoryDTO;
use App\Models\Story;
use Illuminate\Database\Eloquent\Collection;

interface StoryRepositoryInterface
{
    /** Stories ativos, mais recentes primeiro, com job/mentorship + dono pré-carregados. */
    public function activeFeed(): Collection;

    public function create(CreateStoryDTO $dto, string $uuid): Story;

    /** Incrementa atomicamente o contador de visualizações. */
    public function incrementViews(Story $story): void;
}
