<?php

namespace App\Http\Requests\Feed;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates post creation input.
 * Requirements: 4.1
 */
class CreatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content'          => ['required', 'string', 'min:1', 'max:3000'],
            'hotel_uuid'       => ['nullable', 'string', 'exists:hotels,uuid'],
            'image'            => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
            'type'             => ['nullable', 'string', 'in:post,job_share', 'max:30'],
            'metadata'         => ['nullable', 'array'],
            'metadata.job_uuid'     => ['nullable', 'string'],
            'metadata.title'        => ['nullable', 'string'],
            'metadata.hotel_name'   => ['nullable', 'string'],
            'metadata.hotel_logo'   => ['nullable', 'string'],
            'metadata.location'     => ['nullable', 'string'],
            'metadata.contract_type'=> ['nullable', 'string'],
            'metadata.is_active'    => ['nullable', 'boolean'],
        ];
    }
}
