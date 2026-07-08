<?php

namespace App\Domain\Subscription\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Implemented by any model that can hold subscriptions (User, Hotel).
 * Lets the subscription/entitlement services treat subjects uniformly.
 */
interface Subscribable
{
    public function subscriptions(): MorphMany;

    /** The subject kind used to validate plan audience: 'user' | 'hotel'. */
    public function subscribableAudience(): string;
}
