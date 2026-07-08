<?php

namespace App\Domain\Subscription\Services;

use App\Domain\Subscription\Contracts\Subscribable;
use App\Domain\Subscription\Repositories\SubscriptionRepositoryInterface;
use App\Domain\Subscription\Support\PlanCatalog;
use App\Enums\SubscriptionPlan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Manages the subscription lifecycle.
 *
 * Payment is deferred this sprint: subscribe() performs a "fake checkout" that
 * activates the plan immediately. When a gateway (Stripe / Mercado Pago) is
 * added, it plugs in at the activation step — the provider/provider_subscription_id
 * columns and the pending status already exist for that flow.
 */
class SubscriptionService
{
    /** Fake-checkout access period, in days. */
    private const TRIAL_PERIOD_DAYS = 30;

    public function __construct(
        private readonly SubscriptionRepositoryInterface $repository,
    ) {}

    public function activeSubscription(Subscribable $subject): ?Subscription
    {
        return $this->repository->activeFor($subject);
    }

    /**
     * Activate a plan for a subject (fake checkout).
     *
     * Throws \RuntimeException('plan_audience_mismatch') if the plan does not
     * target the subject kind (e.g. PRO Empresa on a User).
     * Throws \RuntimeException('cannot_subscribe_free') for the Free plan —
     * downgrading to Free is done via cancel().
     */
    public function subscribe(Subscribable $subject, SubscriptionPlan $plan): Subscription
    {
        if ($plan === SubscriptionPlan::Free) {
            throw new \RuntimeException('cannot_subscribe_free');
        }

        if (PlanCatalog::audienceFor($plan) !== $subject->subscribableAudience()) {
            throw new \RuntimeException('plan_audience_mismatch');
        }

        return DB::transaction(function () use ($subject, $plan) {
            // Only one active subscription per subject.
            $this->repository->cancelActive($subject);

            return $this->repository->create($subject, $plan, [
                'expires_at' => now()->addDays(self::TRIAL_PERIOD_DAYS),
            ]);
        });
    }

    /**
     * Cancel the subject's active subscription (reverts to Free).
     */
    public function cancel(Subscribable $subject): void
    {
        $this->repository->cancelActive($subject);
    }

    /**
     * Admin override: force a subject onto a plan (or Free) without checkout.
     * Free cancels any active subscription; any other plan subscribes.
     */
    public function setPlan(Subscribable $subject, SubscriptionPlan $plan): ?Subscription
    {
        if ($plan === SubscriptionPlan::Free) {
            $this->cancel($subject);

            return null;
        }

        return $this->subscribe($subject, $plan);
    }
}
