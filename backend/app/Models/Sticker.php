<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Figurinha criada por um usuário (imagem em disco privado). Cada pessoa guarda a sua coleção. */
class Sticker extends Model
{
    protected $fillable = ['uuid', 'created_by', 'path', 'mime', 'size'];

    protected $hidden = ['path'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
