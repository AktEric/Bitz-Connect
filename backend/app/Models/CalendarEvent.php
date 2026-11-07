<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Evento ou feriado da empresa no calendário do Workspace. */
class CalendarEvent extends Model
{
    protected $fillable = [
        'uuid', 'hotel_id', 'title', 'description', 'kind', 'starts_on', 'ends_on',
        'starts_time', 'ends_time', 'location', 'department_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(CalendarEventRsvp::class);
    }
}
