<?php

namespace App\Models;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A subscription held by any subject (User or Hotel).
 * Sprint: monetization-hotelconnect-pro
 */
class Subscription extends Model
{
    protected $fillable = [
        'uuid',
        'subscribable_type',
        'subscribable_id',
        'plan',
        'status',
        'started_at',
        'expires_at',
        'cancelled_at',
        'provider',
        'provider_subscription_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'plan'         => SubscriptionPlan::class,
            'status'       => SubscriptionStatus::class,
            'started_at'   => 'datetime',
            'expires_at'   => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata'     => 'array',
        ];
    }

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    // ──────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────

    /** Active and not past its expiry (if any). */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', SubscriptionStatus::Active->value)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    /** Whether this record currently entitles the subject to its plan. */
    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
