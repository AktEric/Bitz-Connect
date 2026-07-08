<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the search query parameter.
 * Requirements: 6.4
 */
class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q'        => ['required', 'string', 'min:1', 'regex:/\S/'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.required' => 'O parâmetro de busca é obrigatório.',
            'q.min'      => 'A query de busca não pode ser vazia.',
            'q.regex'    => 'A query de busca não pode conter apenas espaços.',
        ];
    }
}
