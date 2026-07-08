<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Requirements: 2.3, 2.4
 */
class UploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.image'  => 'O arquivo enviado não é uma imagem válida.',
            'image.mimes'  => 'Apenas imagens nos formatos jpg, png ou webp são aceitas.',
            'image.max'    => 'A imagem não pode exceder 5MB.',
        ];
    }
}
