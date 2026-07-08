<?php

namespace App\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida criação/edição de curso. Autorização (gate/ownership) fica no controller.
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
class CourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title'       => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category'    => ['nullable', 'string', 'max:50'],
            'level'       => ['nullable', 'in:beginner,intermediate,advanced'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
