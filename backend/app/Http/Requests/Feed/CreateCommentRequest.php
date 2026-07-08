<?php

namespace App\Http\Requests\Feed;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates comment creation input.
 * Requirements: 4.7
 */
class CreateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content'        => ['required', 'string', 'min:1', 'max:1000'],
            'parent_uuid'    => ['nullable', 'string', 'exists:comments,uuid'],
        ];
    }
}
