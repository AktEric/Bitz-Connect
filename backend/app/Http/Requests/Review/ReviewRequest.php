<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida a criação/atualização de uma avaliação.
 * Sprint: monetization-hotelconnect-pro (Avaliações)
 */
class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
