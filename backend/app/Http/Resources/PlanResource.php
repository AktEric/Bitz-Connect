<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats a plan definition from the catalog for the pricing page.
 * The underlying resource is an array from config/plans.php, augmented with
 * its plan key under 'id'.
 */
class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->resource['id'],
            'name'        => $this->resource['name'],
            'tagline'     => $this->resource['tagline'] ?? null,
            'price_cents' => $this->resource['price_cents'] ?? 0,
            'price_note'  => $this->resource['price_note'] ?? null,
            'interval'    => $this->resource['interval'] ?? null,
            'audience'    => $this->resource['audience'] ?? 'user',
            'featured'    => $this->resource['featured'] ?? false,
            'features'    => $this->resource['features'] ?? [],
            'benefits'    => $this->resource['benefits'] ?? [],
        ];
    }
}
