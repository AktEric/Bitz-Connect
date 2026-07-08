<?php

namespace App\Domain\Subscription\Repositories;

use App\Domain\Subscription\Contracts\Subscribable;
use App\Enums\SubscriptionPlan;
use App\Models\Subscription;

interface SubscriptionRepositoryInterface
{
    /** The subject's currently-active subscription, if any. */
    public function activeFor(Subscribable $subject): ?Subscription;

    /** Create a new subscription record for the subject on the given plan. */
    public function create(Subscribable $subject, SubscriptionPlan $plan, array $attributes = []): Subscription;

    /** Mark all of a subject's active subscriptions as cancelled. */
    public function cancelActive(Subscribable $subject): void;
}
