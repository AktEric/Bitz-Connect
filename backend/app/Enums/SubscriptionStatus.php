<?php

namespace App\Enums;

/**
 * Lifecycle status of a subscription record.
 */
enum SubscriptionStatus: string
{
    case Pending   = 'pending';   // awaiting payment confirmation (future gateways)
    case Active    = 'active';    // currently entitles the subject to plan features
    case Cancelled = 'cancelled'; // ended by the subject; may still be active until expires_at
    case Expired   = 'expired';   // period elapsed

    /** Statuses under which plan features are granted. */
    public function grantsAccess(): bool
    {
        return $this === self::Active;
    }
}
