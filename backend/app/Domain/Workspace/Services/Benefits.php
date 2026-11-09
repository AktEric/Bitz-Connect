<?php

namespace App\Domain\Workspace\Services;

use App\Models\BenefitPackage;
use App\Models\BenefitType;
use App\Models\EmployeeBenefit;
use App\Models\Hotel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/** Regras de benefícios: catálogo inicial, próximo depósito, apresentação e aplicação de pacotes. */
class Benefits
{
    public const TZ = 'America/Sao_Paulo';

    /** Cria o catálogo sugerido na primeira vez (idempotente). */
    public function ensureCatalog(Hotel $hotel): void
    {
        if (BenefitType::where('hotel_id', $hotel->id)->exists()) {
            return;
        }
        foreach (BenefitType::DEFAULTS as $i => [$name, $color]) {
            BenefitType::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'name' => $name, 'color' => $color, 'sort' => $i]);
        }
    }

    public function isActive(EmployeeBenefit $b, ?CarbonImmutable $today = null): bool
    {
        $today ??= CarbonImmutable::now(self::TZ)->startOfDay();

        return (! $b->starts_on || $b->starts_on->startOfDay() <= $today) && (! $b->ends_on || $b->ends_on->endOfDay() >= $today);
    }

    /** Próxima data de depósito (mensal: o dia escolhido, ajustado ao fim de meses curtos; pontual: a data, se ainda não passou). */
    public function nextDeposit(EmployeeBenefit $b, ?CarbonImmutable $today = null): ?CarbonImmutable
    {
        $today ??= CarbonImmutable::now(self::TZ)->startOfDay();
        if ($b->ends_on && $b->ends_on->endOfDay() < $today) {
            return null;
        }
        if ($b->frequency === 'one_time') {
            return $b->deposit_on && $b->deposit_on->startOfDay() >= $today ? CarbonImmutable::parse($b->deposit_on->toDateString(), self::TZ) : null;
        }
        if (! $b->deposit_day) {
            return null;
        }
        $pick = fn (CarbonImmutable $month) => $month->startOfMonth()->addDays(min($b->deposit_day, $month->daysInMonth) - 1);
        $date = $pick($today);
        if ($date < $today) {
            $date = $pick($today->addMonthNoOverflow());
        }
        if ($b->starts_on && $date < CarbonImmutable::parse($b->starts_on->toDateString(), self::TZ)) {
            $date = $pick(CarbonImmutable::parse($b->starts_on->toDateString(), self::TZ));
            if ($date < CarbonImmutable::parse($b->starts_on->toDateString(), self::TZ)) {
                $date = $pick($date->addMonthNoOverflow());
            }
        }

        return $b->ends_on && $date > CarbonImmutable::parse($b->ends_on->toDateString(), self::TZ) ? null : $date;
    }

    public function present(EmployeeBenefit $b): array
    {
        $b->loadMissing('type');
        $next = $this->nextDeposit($b);

        return [
            'uuid' => $b->uuid,
            'type' => ['uuid' => $b->type->uuid, 'name' => $b->type->name, 'color' => $b->type->color],
            'amount_cents' => $b->amountCents(),
            'frequency' => $b->frequency,
            'deposit_day' => $b->deposit_day,
            'deposit_on' => $b->deposit_on?->toDateString(),
            'next_deposit_on' => $next?->toDateString(),
            'starts_on' => $b->starts_on?->toDateString(),
            'ends_on' => $b->ends_on?->toDateString(),
            'active' => $this->isActive($b),
            'note' => $b->note,
        ];
    }

    /** Aplica o pacote à pessoa: cria os benefícios que ela ainda não tem (por tipo vigente). Devolve quantos criou. */
    public function applyPackage(BenefitPackage $pkg, Hotel $hotel, int $userId, ?int $by): int
    {
        $created = 0;
        foreach ((array) $pkg->items as $item) {
            $type = BenefitType::where('hotel_id', $hotel->id)->where('uuid', $item['benefit_type_uuid'] ?? '')->where('active', true)->first();
            if (! $type) {
                continue;
            }
            $has = EmployeeBenefit::where('hotel_id', $hotel->id)->where('user_id', $userId)->where('benefit_type_id', $type->id)->whereNull('ends_on')->exists();
            if ($has) {
                continue;
            }
            $cents = $item['amount_cents'] ?? null;
            EmployeeBenefit::create([
                'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'user_id' => $userId, 'benefit_type_id' => $type->id,
                'amount' => $cents === null ? null : (string) (int) $cents, 'frequency' => 'monthly', 'deposit_day' => $item['deposit_day'] ?? null,
                'starts_on' => CarbonImmutable::now(self::TZ)->toDateString(), 'note' => $item['note'] ?? null, 'created_by' => $by,
            ]);
            $created++;
        }

        return $created;
    }
}
