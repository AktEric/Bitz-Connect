<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterHotelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Passo 1 — dados do usuário
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', 'string', \App\Support\PasswordRules::policy(), 'confirmed'],

            // Passo 2 — dados do hotel
            'hotel_name'            => ['required', 'string', 'max:255'],
            'cnpj'                  => [
                'required',
                'string',
                Rule::regex('/^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$/'),
                Rule::unique('hotels', 'cnpj'),
            ],
            'hotel_email'           => ['required', 'email'],
            'hotel_password'        => ['required', 'string', \App\Support\PasswordRules::policy()],
            'city'                  => ['nullable', 'string', 'max:255'],
            'website'               => ['nullable', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'          => 'O e-mail informado já está em uso.',
            'cnpj.regex'            => 'O CNPJ informado não é válido. Use o formato XX.XXX.XXX/XXXX-XX.',
            'cnpj.unique'           => 'Este CNPJ já está vinculado a outro hotel.',
        ];
    }
}
