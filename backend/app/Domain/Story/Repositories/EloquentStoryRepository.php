<?php

namespace App\Domain\Story\Repositories;

use App\Domain\Story\DTOs\CreateStoryDTO;
use App\Models\Story;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class EloquentStoryRepository implements StoryRepositoryInterface
{
    public function activeFeed(): Collection
    {
        return Story::query()
            ->with(['job.hotel.subscriptions', 'mentorship.user.subscriptions'])
            ->tap(fn (Builder $q) => $this->withLikedByMe($q))
            ->latest()
            ->get();
    }

    /** Adiciona o flag liked_by_me para o usuário autenticado, sem N+1. */
    private function withLikedByMe(Builder $query): void
    {
        if ($userId = Auth::id()) {
            $query->withExists(['likes as liked_by_me' => fn ($q) => $q->where('user_id', $userId)]);
        }
    }

    public function create(CreateStoryDTO $dto, string $uuid): Story
    {
        return Story::create([
            'uuid'          => $uuid,
            'story_type'    => $dto->story_type,
            'job_id'        => $dto->job_id,
            'mentorship_id' => $dto->mentorship_id,
            'caption'       => $dto->caption,
            'image_path'    => $dto->image_path,
        ]);
    }

    public function incrementViews(Story $story): void
    {
        $story->increment('views_count');
    }
}
