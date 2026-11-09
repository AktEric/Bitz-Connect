<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto de um anúncio (arquivo em disco privado). */
class MarketplaceImage extends Model
{
    protected $fillable = ['uuid', 'listing_id', 'path', 'mime', 'size', 'position'];

    protected $hidden = ['path'];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'listing_id');
    }
}
