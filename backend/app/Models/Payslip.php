<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payslip extends Model
{
    protected $fillable = ['uuid','hotel_id','user_id','uploaded_by','kind','title','reference_month','amount_cents','file_path','original_name','mime','size','first_viewed_at'];

    protected $table = 'payslips';
    protected $hidden = ['file_path'];

    protected function casts(): array
    {
        return ['reference_month' => 'date', 'first_viewed_at' => 'datetime', 'amount_cents' => 'integer', 'size' => 'integer'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
