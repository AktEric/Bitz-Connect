<?php

namespace App\Http\Requests\Video;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida o upload de um vídeo. A autorização por plano (publish_videos)
 * é verificada no controller para permitir uma resposta 403 clara.
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
class CreateVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:5000'],
            'category'         => ['nullable', 'string', 'max:50'],
            'tags'             => ['nullable', 'array', 'max:10'],
            'tags.*'           => ['string', 'max:30'],
            // Vídeos são reels de divulgação: até 30 segundos.
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:30'],
            'video'            => ['required', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:204800'],
            'thumbnail'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'video.required'          => 'Selecione um arquivo de vídeo.',
            'video.mimetypes'         => 'O vídeo deve estar em formato MP4, WebM ou MOV.',
            'video.max'               => 'O vídeo pode ter no máximo 200 MB.',
            'duration_seconds.required' => 'Não foi possível ler a duração do vídeo.',
            'duration_seconds.max'    => 'Os vídeos podem ter no máximo 30 segundos.',
        ];
    }
}
