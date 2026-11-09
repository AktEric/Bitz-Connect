<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Fluxo de aprovação de um tipo de solicitação da empresa. */
class RequestFlow extends Model
{
    protected $fillable = ['hotel_id', 'type', 'steps', 'updated_by'];

    protected function casts(): array
    {
        return ['steps' => 'array'];
    }
}
