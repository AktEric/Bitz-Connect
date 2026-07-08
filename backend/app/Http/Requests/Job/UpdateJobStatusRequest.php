<?php

namespace App\Http\Requests\Job;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates job status update payload.
 * Requirements: 5.4
 */
class UpdateJobStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:active,paused,closed'],
        ];
    }
}
