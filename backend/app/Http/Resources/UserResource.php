<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Formats public user data.
 * Requirements: 8.1, 8.4
 */
class UserResource extends JsonResource
{
    /** Optional connection_status resolved by the controller. */
    public ?string $connectionStatus = null;

    /** Respostas de auth (login/cadastro) devolvem o e-mail ao próprio usuário. */
    public bool $exposeEmail = false;

    public function withOwnEmail(): static
    {
        $this->exposeEmail = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'uuid'         => $this->uuid,
            'name'         => $this->name,
            // PII: o e-mail só é exposto ao próprio usuário (perfis públicos não vazam e-mail).
            'email'        => $this->when($this->exposeEmail || $request->user('sanctum')?->id === $this->id, fn () => $this->email),
            'avatar_url'   => $this->avatar_path
                ? asset('storage/' . $this->avatar_path)
                : null,
            'banner_url'   => $this->banner_path
                ? asset('storage/' . $this->banner_path)
                : null,
            'bio'          => $this->bio,
            'specialty'    => $this->specialty,
            'followers_count' => (int) ($this->followers_count ?? 0),
            'videos_count' => $this->whenCounted('videos'),
            'reviews_count' => $this->whenCounted('reviews'),
            'rating_avg'   => $this->when($this->reviews_avg_rating !== null, fn () => round((float) $this->reviews_avg_rating, 1)),
            'city'         => $this->city,
            'state'        => $this->state,
            'website'      => $this->website,
            'social_links' => $this->social_links,
            'role'         => $this->role,
            'created_at'   => $this->created_at?->toISOString(),
            'experiences'  => $this->whenLoaded('experiences', fn () =>
                ExperienceResource::collection($this->experiences)
            ),
            // Subscription info — only when eager-loaded, so collections stay N+1-free.
            'plan'         => $this->whenLoaded('subscriptions', fn () => $this->activePlan()->value),
            'is_pro'       => $this->whenLoaded('subscriptions', fn () => $this->isPro()),
            // É criador (pode ter página de criador) quando está no plano Consultor.
            'is_creator'   => $this->whenLoaded('subscriptions', fn () => $this->activePlan()->value === 'pro_consultor'),
        ];

        // Only included when explicitly set (e.g. by UserController@show)
        if ($this->connectionStatus !== null) {
            $data['connection_status'] = $this->connectionStatus;
        } elseif (array_key_exists('connection_status', $this->additional)) {
            $data['connection_status'] = $this->additional['connection_status'];
        }

        // Definido pelo CreatorController@show
        if (array_key_exists('is_following', $this->additional)) {
            $data['is_following'] = $this->additional['is_following'];
        }

        return $data;
    }
}
