<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Requirements: 2.2
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                   => ['sometimes', 'string', 'max:255'],
            'bio'                    => ['sometimes', 'nullable', 'string', 'max:1000'],
            'specialty'              => ['sometimes', 'nullable', 'string', 'max:120'],
            'city'                   => ['sometimes', 'nullable', 'string', 'max:100'],
            'state'                  => ['sometimes', 'nullable', 'string', 'max:100'],
            'website'                => ['sometimes', 'nullable', 'url', 'max:255'],
            'social_links'           => ['sometimes', 'nullable', 'array'],
            'social_links.*'         => ['string', 'url', 'max:255'],
        ];
    }
}
