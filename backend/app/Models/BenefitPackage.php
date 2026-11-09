<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Modelo de benefícios aplicado de uma vez a uma pessoa (no convite ou no cadastro). */
class BenefitPackage extends Model
{
    protected $fillable = ['uuid', 'hotel_id', 'name', 'items'];

    protected function casts(): array
    {
        return ['items' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
