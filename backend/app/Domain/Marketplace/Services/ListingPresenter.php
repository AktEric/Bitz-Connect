<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Models\MarketplaceImage;
use App\Models\MarketplaceListing;
use Illuminate\Support\Facades\URL;

/** Formata anúncios para a API (lista, detalhe e cartão dentro do mural). Fotos saem só com URL assinada curta. */
class ListingPresenter
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function imageUrl(MarketplaceImage $img): string
    {
        return URL::temporarySignedRoute('marketplace.media', now()->addMinutes(30), ['uuid' => $img->uuid], absolute: false);
    }

    /** Cartão resumido (grade, mural, chat). $liked: ids de anúncios curtidos por quem vê; null = consulta no banco. */
    public function summary(MarketplaceListing $l, int $viewerId, ?array $liked = null): array
    {
        $l->loadMissing(['images', 'seller']);
        $isLiked = $liked !== null ? in_array($l->id, $liked, true) : $l->likes()->where('user_id', $viewerId)->exists();

        return [
            'uuid'         => $l->uuid,
            'title'        => $l->title,
            'price_cents'  => $l->price_cents,
            'category'     => $l->category,
            'condition'    => $l->condition,
            'location'     => $l->location,
            'status'       => $l->status,
            'cover_url'    => $l->images->first() ? $this->imageUrl($l->images->first()) : null,
            'images_count' => $l->images->count(),
            'likes_count'  => $l->likes_count,
            'reposts_count' => $l->reposts_count,
            'liked'        => $isLiked,
            'is_mine'      => $l->seller_id === $viewerId,
            'seller'       => $this->access->person($l->seller),
            'created_at'   => $l->created_at?->toISOString(),
        ];
    }

    /** Detalhe completo: tudo do resumo + todas as fotos e a descrição. */
    public function detail(MarketplaceListing $l, int $viewerId, bool $canModerate): array
    {
        return $this->summary($l, $viewerId) + [
            'description' => $l->description,
            'views_count' => $l->seller_id === $viewerId || $canModerate ? $l->views_count : null,
            'sold_at'     => $l->sold_at?->toISOString(),
            'images'      => $l->images->map(fn (MarketplaceImage $i) => ['uuid' => $i->uuid, 'url' => $this->imageUrl($i)])->values(),
            'can_edit'    => $l->seller_id === $viewerId,
            'can_moderate' => $canModerate,
        ];
    }
}
