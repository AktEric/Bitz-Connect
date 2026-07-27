<?php

namespace App\Http\Requests\Story;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida a criação de um story. A autorização (dono do job/mentorship +
 * entitlement publish_stories) é verificada no controller para uma resposta
 * 403 clara.
 * Sprint: monetization-hotelconnect-pro (Stories)
 */
class StoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'story_type'      => ['required', 'in:job,mentorship'],
            'job_uuid'        => ['required_if:story_type,job', 'uuid', 'exists:jobs,uuid'],
            'mentorship_uuid' => ['required_if:story_type,mentorship', 'uuid', 'exists:mentorships,uuid'],
            'caption'         => ['nullable', 'string', 'max:280'],
            'image'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ];
    }

    public function messages(): array
    {
        return [
            'story_type.required'      => 'Selecione o tipo de story.',
            'job_uuid.required_if'     => 'Selecione a vaga a destacar.',
            'mentorship_uuid.required_if' => 'Selecione a mentoria a destacar.',
            'image.image'                 => 'O arquivo precisa ser uma imagem.',
            'image.mimes'                 => 'A imagem deve estar em formato JPG, PNG ou WEBP.',
            'image.max'                   => 'A imagem pode ter no máximo 8 MB.',
        ];
    }
}
