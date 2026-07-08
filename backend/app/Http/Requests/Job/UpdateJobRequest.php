<?php

namespace App\Http\Requests\Job;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates job update payload (all fields optional).
 * Requirements: 5.3
 */
class UpdateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'         => ['sometimes', 'string', 'min:3', 'max:255'],
            'description'   => ['sometimes', 'string', 'min:10'],
            'requirements'  => ['sometimes', 'nullable', 'string'],
            'benefits'      => ['sometimes', 'nullable', 'string'],
            'contract_type' => ['sometimes', 'string', 'in:clt,pj,freelance,internship'],
            'modality'      => ['sometimes', 'string', 'in:on_site,remote,hybrid'],
            'city'          => ['sometimes', 'string', 'max:100'],
            'state'         => ['sometimes', 'string', 'max:2'],
            'salary_range'  => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
