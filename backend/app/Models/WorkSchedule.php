<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Jornada de trabalho: minutos esperados em cada dia da semana (0 = domingo … 6 = sábado). */
class WorkSchedule extends Model
{
    /** Jornada padrão de quem não tem escala atribuída: 8h de segunda a sexta. */
    public const DEFAULT_WEEK = [0 => 0, 1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 0];

    protected $fillable = ['uuid', 'hotel_id', 'name', 'week_minutes'];

    protected function casts(): array
    {
        return ['week_minutes' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function members(): HasMany
    {
        return $this->hasMany(HotelUser::class, 'work_schedule_id');
    }

    /** Minutos esperados no dia da semana informado (Carbon dayOfWeek). */
    public static function minutesFor(?self $schedule, int $dayOfWeek): int
    {
        $week = $schedule?->week_minutes ?? self::DEFAULT_WEEK;

        return (int) ($week[$dayOfWeek] ?? $week[(string) $dayOfWeek] ?? 0);
    }
}
