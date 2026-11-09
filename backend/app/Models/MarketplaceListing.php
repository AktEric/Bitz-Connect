<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Anúncio do marketplace interno da empresa. */
class MarketplaceListing extends Model
{
    use SoftDeletes;

    protected $fillable = ['uuid', 'hotel_id', 'seller_id', 'title', 'description', 'price_cents', 'category', 'condition', 'location', 'status', 'sold_at'];

    protected function casts(): array
    {
        return ['price_cents' => 'integer', 'sold_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(MarketplaceImage::class, 'listing_id')->orderBy('position')->orderBy('id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(MarketplaceLike::class, 'listing_id');
    }
}
