<?php

namespace App\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida criação/edição de aula.
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
class LessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title'            => [$required, 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'content'          => ['nullable', 'string', 'max:20000'],
            'video_url'        => ['nullable', 'url', 'max:500'],
            'position'         => ['nullable', 'integer', 'min:0'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
