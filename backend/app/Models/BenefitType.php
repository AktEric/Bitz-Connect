<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tipo de benefício do catálogo da empresa. */
class BenefitType extends Model
{
    public const COLORS = ['green', 'blue', 'amber', 'rose', 'purple', 'teal', 'orange', 'gray'];

    /** Catálogo inicial sugerido, criado na primeira vez que a empresa abre os benefícios. */
    public const DEFAULTS = [
        ['Vale alimentação', 'green'], ['Vale refeição', 'orange'], ['Vale transporte', 'blue'], ['Plano de saúde', 'rose'],
        ['Plano odontológico', 'teal'], ['Seguro de vida', 'purple'], ['Auxílio creche', 'amber'], ['Gympass', 'gray'],
    ];

    protected $fillable = ['uuid', 'hotel_id', 'name', 'color', 'active', 'sort'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
