<?php

namespace App\Domain\Subscription\Services;

use App\Domain\Subscription\Contracts\Subscribable;
use App\Domain\Subscription\Support\PlanCatalog;
use App\Enums\Feature;
use App\Enums\SubscriptionPlan;

/**
 * Centralized entitlement layer (Sprint requirement §3).
 *
 * Every "can this subject do X?" question in the system — backend and, via the
 * exposed entitlement map, frontend — resolves through this service. Callers
 * never inspect the plan string directly, so introducing a new plan or moving a
 * feature between tiers is a config change with no scattered edits.
 */
class EntitlementService
{
    /**
     * The plan currently in effect for a subject. Absent/inactive → Free.
     */
    public function activePlan(Subscribable $subject): SubscriptionPlan
    {
        $subscription = $subject->subscriptions()->active()->latest('started_at')->first();

        return $subscription?->plan ?? SubscriptionPlan::default();
    }

    /**
     * Whether a subject is entitled to a given feature.
     */
    public function allows(Subscribable $subject, Feature $feature): bool
    {
        return in_array($feature->value, $this->featureValuesFor($subject), true);
    }

    /**
     * Feature values granted to the subject by its active plan.
     *
     * @return array<int, string>
     */
    public function featureValuesFor(Subscribable $subject): array
    {
        return PlanCatalog::featuresFor($this->activePlan($subject));
    }

    /**
     * Full entitlement map (every known feature → bool) for the subject.
     * This is what the frontend consumes so the UI can gate consistently.
     *
     * @return array<string, bool>
     */
    public function entitlements(Subscribable $subject): array
    {
        $granted = $this->featureValuesFor($subject);

        $map = [];
        foreach (Feature::cases() as $feature) {
            $map[$feature->value] = in_array($feature->value, $granted, true);
        }

        return $map;
    }
}
