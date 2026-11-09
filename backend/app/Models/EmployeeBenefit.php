<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Benefício recebido por uma pessoa (valor criptografado em repouso). */
class EmployeeBenefit extends Model
{
    protected $fillable = ['uuid', 'hotel_id', 'user_id', 'benefit_type_id', 'amount', 'frequency', 'deposit_day', 'deposit_on', 'starts_on', 'ends_on', 'note', 'created_by'];

    protected $hidden = ['amount'];

    protected function casts(): array
    {
        return ['amount' => 'encrypted', 'deposit_on' => 'date', 'starts_on' => 'date', 'ends_on' => 'date', 'deposit_day' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(BenefitType::class, 'benefit_type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function amountCents(): ?int
    {
        return $this->amount === null || $this->amount === '' ? null : (int) $this->amount;
    }
}
