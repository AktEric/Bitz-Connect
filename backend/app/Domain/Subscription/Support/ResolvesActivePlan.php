<?php

namespace App\Domain\Subscription\Support;

use App\Enums\SubscriptionPlan;

/**
 * Convenience plan resolution for Subscribable models.
 *
 * Prefers an already-loaded `subscriptions` relation (so resources rendered in
 * collections don't trigger N+1) and only queries as a fallback for a single
 * model. The authoritative entitlement source remains EntitlementService.
 */
trait ResolvesActivePlan
{
    public function activePlan(): SubscriptionPlan
    {
        $subscriptions = $this->relationLoaded('subscriptions')
            ? $this->subscriptions
            : $this->subscriptions()->get();

        $active = $subscriptions->first(fn ($s) => $s->isActive());

        return $active?->plan ?? SubscriptionPlan::default();
    }

    public function isPro(): bool
    {
        return $this->activePlan()->isPro();
    }
}
