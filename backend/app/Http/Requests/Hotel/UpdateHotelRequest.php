<?php

namespace App\Http\Requests\Hotel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Requirements: 3.3
 */
class UpdateHotelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => ['sometimes', 'string', 'min:2', 'max:255'],
            'description'   => ['sometimes', 'string', 'min:10', 'max:5000'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'country'       => ['nullable', 'string', 'max:100'],
            'website'       => ['nullable', 'url', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
