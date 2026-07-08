<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Requirements: 5.1, 5.2, 5.3, 5.4, 5.6
 */
class Job extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'hotel_id',
        'title',
        'description',
        'requirements',
        'benefits',
        'contract_type',
        'modality',
        'city',
        'state',
        'salary_range',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Route model binding uses uuid.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }
}
