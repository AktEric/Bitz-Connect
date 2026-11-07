<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Domain\Subscription\Contracts\Subscribable;
use App\Domain\Subscription\Support\ResolvesActivePlan;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Requirements: 3.1, 3.2, 6.1, 6.2, 8.1, 3.5
 */
class Hotel extends Model implements Subscribable
{
    use HasFactory, ResolvesActivePlan, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'cnpj',
        'slug',
        'segment',
        'description',
        'logo_path',
        'cover_path',
        'city',
        'state',
        'country',
        'website',
        'phone',
        'contact_email',
        'followers_count',
        'time_settings',
        'security_settings',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'time_settings' => 'array',
            'security_settings' => 'array',
        ];
    }

    /**
     * Route model binding uses slug.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    public function hotelUsers(): HasMany
    {
        return $this->hasMany(HotelUser::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function chatGroups(): HasMany
    {
        return $this->hasMany(ChatGroup::class);
    }

    /** Pivot: users who are admins of this hotel */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'hotel_users')
            ->withPivot(['role', 'status'])
            ->wherePivot('role', 'admin')
            ->wherePivot('status', 'approved')
            ->withTimestamps();
    }

    /** Approved collaborators */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'hotel_users')
            ->withPivot(['role', 'status'])
            ->wherePivot('role', 'employee')
            ->wherePivot('status', 'approved')
            ->withTimestamps();
    }

    /** Users following this hotel (Requirements: 8.1) */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'hotel_followers')->withTimestamps();
    }

    public function hotelFollowers(): HasMany
    {
        return $this->hasMany(HotelFollower::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(HotelClaim::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscribable');
    }

    public function subscribableAudience(): string
    {
        return 'hotel';
    }
}
