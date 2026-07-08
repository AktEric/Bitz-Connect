<?php

namespace App\Http\Requests\Feed;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates post update input.
 * Requirements: 4.3
 */
class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content'  => ['required', 'string', 'min:1', 'max:3000'],
            'hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
        ];
    }
}
