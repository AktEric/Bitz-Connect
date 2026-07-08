<?php

namespace App\Http\Requests\Subscription;

use App\Enums\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Validates a subscribe (fake checkout) request.
 * `hotel_slug` is required when the chosen plan targets a hotel.
 */
class SubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan'       => ['required', new Enum(SubscriptionPlan::class), Rule::notIn([SubscriptionPlan::Free->value])],
            'hotel_slug' => ['nullable', 'string', 'exists:hotels,slug'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan.not_in' => 'O plano gratuito não requer assinatura.',
        ];
    }
}
