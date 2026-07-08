<?php

namespace App\Http\Requests\Feed;

use Illuminate\Foundation\Http\FormRequest;

class UploadPostImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
        ];
    }
}
