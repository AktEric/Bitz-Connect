<?php

namespace App\Http\Requests\Mentorship;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida criação/edição de mentoria.
 * Sprint: monetization-hotelconnect-pro (Mentorias)
 */
class MentorshipRequest extends FormRequest
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
            'description'      => ['nullable', 'string', 'max:5000'],
            'category'         => ['nullable', 'string', 'max:50'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'session_type'     => ['nullable', 'in:online,presential'],
            'price_cents'      => ['nullable', 'integer', 'min:0'],
        ];
    }
}
