<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceRepost extends Model
{
    protected $fillable = ['listing_id', 'user_id', 'post_id'];
}
