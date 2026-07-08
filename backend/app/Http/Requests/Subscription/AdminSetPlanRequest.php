<?php

namespace App\Http\Requests\Subscription;

use App\Enums\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Validates the platform-admin plan override.
 * Authorization (platform admin) is enforced in the controller.
 */
class AdminSetPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_type' => ['required', 'in:user,hotel'],
            'subject_id'   => ['required', 'string'], // user uuid or hotel slug
            'plan'         => ['required', new Enum(SubscriptionPlan::class)],
        ];
    }
}
