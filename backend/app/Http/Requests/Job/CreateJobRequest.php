<?php

namespace App\Http\Requests\Job;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates job creation payload.
 * Requirements: 5.1, 5.2
 */
class CreateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Policy check is handled in the controller via $this->authorize()
        return true;
    }

    public function rules(): array
    {
        return [
            'hotel_uuid'    => ['required', 'string', 'exists:hotels,uuid'],
            'title'         => ['required', 'string', 'min:3', 'max:255'],
            'description'   => ['required', 'string', 'min:10'],
            'requirements'  => ['nullable', 'string'],
            'benefits'      => ['nullable', 'string'],
            'contract_type' => ['required', 'string', 'in:clt,pj,freelance,internship'],
            'modality'      => ['required', 'string', 'in:on_site,remote,hybrid'],
            'city'          => ['required', 'string', 'max:100'],
            'state'         => ['required', 'string', 'max:2'],
            'salary_range'  => ['nullable', 'string', 'max:100'],
        ];
    }
}
