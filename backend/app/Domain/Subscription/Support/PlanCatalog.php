<?php

namespace App\Domain\Subscription\Support;

use App\Enums\SubscriptionPlan;

/**
 * Read-only accessor over config/plans.php.
 * The catalog is the single source of truth for plan pricing, audience,
 * feature grants and benefit copy.
 */
class PlanCatalog
{
    /** @return array<string, array<string, mixed>> keyed by plan value */
    public static function all(): array
    {
        return config('plans.plans', []);
    }

    public static function currency(): string
    {
        return config('plans.currency', 'BRL');
    }

    /** @return array<string, mixed>|null */
    public static function get(SubscriptionPlan $plan): ?array
    {
        return config('plans.plans.' . $plan->value);
    }

    /**
     * Feature values granted by a plan.
     *
     * @return array<int, string>
     */
    public static function featuresFor(SubscriptionPlan $plan): array
    {
        return config('plans.plans.' . $plan->value . '.features', []);
    }

    /** Audience a plan targets: 'user' | 'hotel'. Falls back to the enum. */
    public static function audienceFor(SubscriptionPlan $plan): string
    {
        return config('plans.plans.' . $plan->value . '.audience', $plan->audience());
    }

    public static function exists(string $plan): bool
    {
        return config('plans.plans.' . $plan) !== null;
    }
}
