<?php

namespace App\Domain\Video\Repositories;

use App\Domain\Video\DTOs\CreateVideoDTO;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class EloquentVideoRepository implements VideoRepositoryInterface
{
    public function feed(int $perPage = 12, ?string $category = null): LengthAwarePaginator
    {
        return Video::query()
            ->published()
            ->with('user.subscriptions')
            ->tap(fn (Builder $q) => $this->withLikedByMe($q))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->latest()
            ->paginate($perPage);
    }

    public function byCreator(User $creator, int $perPage = 12): LengthAwarePaginator
    {
        return $creator->videos()
            ->published()
            ->with('user.subscriptions')
            ->tap(fn (Builder $q) => $this->withLikedByMe($q))
            ->latest()
            ->paginate($perPage);
    }

    /** Adiciona o flag liked_by_me para o usuário autenticado, sem N+1. */
    private function withLikedByMe(Builder $query): void
    {
        if ($userId = Auth::id()) {
            $query->withExists(['likes as liked_by_me' => fn ($q) => $q->where('user_id', $userId)]);
        }
    }

    public function create(CreateVideoDTO $dto, string $uuid): Video
    {
        return Video::create([
            'uuid'             => $uuid,
            'user_id'          => $dto->user_id,
            'title'            => $dto->title,
            'description'      => $dto->description,
            'video_path'       => $dto->video_path,
            'thumbnail_path'   => $dto->thumbnail_path,
            'duration_seconds' => $dto->duration_seconds,
            'category'         => $dto->category,
            'tags'             => $dto->tags,
        ]);
    }

    public function incrementViews(Video $video): void
    {
        $video->increment('views_count');
    }
}
