<?php

namespace App\Enums;

/**
 * Subscription plan tiers.
 *
 * New plans are added here and given a definition in config/plans.php —
 * no other code needs to change to introduce a new tier.
 */
enum SubscriptionPlan: string
{
    case Free         = 'free';
    case ProConsultor = 'pro_consultor';
    case ProEmpresa   = 'pro_empresa';

    /** The default plan applied when a subject has no active subscription. */
    public static function default(): self
    {
        return self::Free;
    }

    public function isPro(): bool
    {
        return $this !== self::Free;
    }

    /**
     * Which kind of subject this plan targets.
     * 'user'    → individual people (Free, PRO Consultor)
     * 'hotel'   → companies (PRO Empresa)
     */
    public function audience(): string
    {
        return match ($this) {
            self::ProEmpresa => 'hotel',
            default          => 'user',
        };
    }
}
