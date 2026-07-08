<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Requirements: 2.5
 */
class ExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'title'       => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'company'     => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'started_at'  => [$isUpdate ? 'sometimes' : 'required', 'date'],
            'ended_at'    => ['sometimes', 'nullable', 'date', 'after_or_equal:started_at'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'hotel_uuid'  => ['nullable', 'string', 'exists:hotels,uuid'],
        ];
    }
}
