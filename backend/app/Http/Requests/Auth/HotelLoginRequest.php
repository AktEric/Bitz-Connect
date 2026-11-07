<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class HotelLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'code'          => ['nullable', 'string', 'max:12'],
            'recovery_code' => ['nullable', 'string', 'max:20'],
            'cnpj'     => ['required', 'string', 'min:14', 'max:18'],
        ];
    }

    public function messages(): array
    {
        return [
            'cnpj.required' => 'O CNPJ é obrigatório.',
            'cnpj.min'      => 'O CNPJ informado não é válido.',
            'cnpj.max'      => 'O CNPJ informado não é válido.',
        ];
    }
}
