<?php

namespace App\Domain\Subscription\Repositories;

use App\Domain\Subscription\Contracts\Subscribable;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Support\Str;

class EloquentSubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function activeFor(Subscribable $subject): ?Subscription
    {
        return $subject->subscriptions()->active()->latest('started_at')->first();
    }

    public function create(Subscribable $subject, SubscriptionPlan $plan, array $attributes = []): Subscription
    {
        return $subject->subscriptions()->create(array_merge([
            'uuid'       => (string) Str::uuid(),
            'plan'       => $plan->value,
            'status'     => SubscriptionStatus::Active->value,
            'started_at' => now(),
        ], $attributes));
    }

    public function cancelActive(Subscribable $subject): void
    {
        $subject->subscriptions()
            ->where('status', SubscriptionStatus::Active->value)
            ->update([
                'status'       => SubscriptionStatus::Cancelled->value,
                'cancelled_at' => now(),
            ]);
    }
}
