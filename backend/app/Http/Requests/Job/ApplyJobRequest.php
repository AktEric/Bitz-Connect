<?php

namespace App\Http\Requests\Job;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates job application payload.
 * Requirements: 5.5
 */
class ApplyJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cover_letter' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
