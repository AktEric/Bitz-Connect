<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceLike extends Model
{
    protected $fillable = ['listing_id', 'user_id'];
}
