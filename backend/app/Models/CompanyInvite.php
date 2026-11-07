<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyInvite extends Model
{
    protected $fillable = ['uuid','hotel_id','created_by','token','email','department_id','job_title','max_uses','uses','expires_at','revoked_at'];

    protected $table = 'company_invites';
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function isUsable(): bool
    {
        return ! $this->revoked_at
            && (! $this->expires_at || $this->expires_at->isFuture())
            && $this->uses < $this->max_uses;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
