<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Matrícula de um usuário em um curso.
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
class CourseEnrollment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'course_id',
        'user_id',
    ];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
