<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Subscription\Services\EntitlementService;
use App\Domain\Subscription\Services\SubscriptionService;
use App\Domain\Subscription\Support\PlanCatalog;
use App\Enums\SubscriptionPlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\AdminSetPlanRequest;
use App\Http\Requests\Subscription\SubscribeRequest;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Subscription & entitlement endpoints (Sprint: monetization-hotelconnect-pro).
 */
class SubscriptionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly EntitlementService $entitlementService,
    ) {}

    /**
     * GET /plans
     * Public plan catalog for the pricing page.
     */
    public function plans(): JsonResponse
    {
        $plans = collect(PlanCatalog::all())
            ->map(fn (array $def, string $id) => array_merge($def, ['id' => $id]))
            ->values();

        return $this->success('Planos carregados com sucesso.', [
            'currency' => PlanCatalog::currency(),
            'plans'    => PlanResource::collection($plans),
        ]);
    }

    /**
     * GET /me/subscription
     * The authenticated user's own subscription + entitlement map.
     */
    public function mySubscription(Request $request): JsonResponse
    {
        return $this->success(
            'Assinatura carregada com sucesso.',
            $this->subjectPayload($request->user()),
        );
    }

    /**
     * POST /subscriptions
     * Fake checkout: activate a plan for the user, or for a hotel the user
     * administers (when the plan targets companies).
     */
    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $plan = SubscriptionPlan::from($request->validated('plan'));
        $user = $request->user();

        $subject = $this->resolveSubject($request, $plan, $user);
        if ($subject instanceof JsonResponse) {
            return $subject; // authorization / validation failure
        }

        try {
            $this->subscriptionService->subscribe($subject, $plan);
        } catch (\RuntimeException $e) {
            return $this->mapDomainError($e);
        }

        return $this->success('Assinatura ativada com sucesso.', $this->subjectPayload($subject));
    }

    /**
     * DELETE /subscriptions
     * Cancel the active subscription of the user or an administered hotel.
     */
    public function cancel(Request $request): JsonResponse
    {
        $hotelSlug = $request->input('hotel_slug');

        if ($hotelSlug) {
            $hotel = Hotel::where('slug', $hotelSlug)->firstOrFail();
            if (! $request->user()->isHotelAdminOf($hotel->id)) {
                return $this->forbidden('Você não administra este hotel.');
            }
            $this->subscriptionService->cancel($hotel);

            return $this->success('Assinatura cancelada com sucesso.', $this->subjectPayload($hotel));
        }

        $this->subscriptionService->cancel($request->user());

        return $this->success('Assinatura cancelada com sucesso.', $this->subjectPayload($request->user()));
    }

    /**
     * GET /hotels/{hotel:slug}/subscription
     * A hotel's subscription + entitlements (hotel admins only).
     */
    public function hotelSubscription(Request $request, Hotel $hotel): JsonResponse
    {
        if (! $request->user()->isHotelAdminOf($hotel->id)) {
            return $this->forbidden('Você não administra este hotel.');
        }

        return $this->success('Assinatura carregada com sucesso.', $this->subjectPayload($hotel));
    }

    /**
     * PATCH /admin/subscriptions
     * Platform-admin override to set any subject's plan (no checkout).
     */
    public function adminSetPlan(AdminSetPlanRequest $request): JsonResponse
    {
        if (! $request->user()->isPlatformAdmin()) {
            return $this->forbidden('Ação restrita a administradores da plataforma.');
        }

        $plan = SubscriptionPlan::from($request->validated('plan'));

        $subject = $request->validated('subject_type') === 'hotel'
            ? Hotel::where('slug', $request->validated('subject_id'))->firstOrFail()
            : User::where('uuid', $request->validated('subject_id'))->firstOrFail();

        try {
            $this->subscriptionService->setPlan($subject, $plan);
        } catch (\RuntimeException $e) {
            return $this->mapDomainError($e);
        }

        return $this->success('Plano atualizado com sucesso.', $this->subjectPayload($subject));
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    /**
     * Resolve which subject a subscribe request applies to, enforcing that the
     * caller may act on it. Returns a JsonResponse on failure.
     */
    private function resolveSubject(SubscribeRequest $request, SubscriptionPlan $plan, User $user): mixed
    {
        if (PlanCatalog::audienceFor($plan) === 'hotel') {
            $slug = $request->validated('hotel_slug');
            if (! $slug) {
                return $this->validationError('Selecione o hotel para este plano.', [
                    'hotel_slug' => ['O hotel é obrigatório para planos empresariais.'],
                ]);
            }
            $hotel = Hotel::where('slug', $slug)->firstOrFail();
            if (! $user->isHotelAdminOf($hotel->id)) {
                return $this->forbidden('Você não administra este hotel.');
            }

            return $hotel;
        }

        return $user;
    }

    /**
     * Build the standard subject payload: active subscription + resolved plan +
     * full entitlement map.
     */
    private function subjectPayload(\App\Domain\Subscription\Contracts\Subscribable $subject): array
    {
        $active = $this->subscriptionService->activeSubscription($subject);

        return [
            'plan'         => $this->entitlementService->activePlan($subject)->value,
            'subscription' => $active ? new SubscriptionResource($active) : null,
            'entitlements' => $this->entitlementService->entitlements($subject),
        ];
    }

    private function mapDomainError(\RuntimeException $e): JsonResponse
    {
        return match ($e->getMessage()) {
            'plan_audience_mismatch' => $this->error('Este plano não está disponível para este tipo de conta.', [], 422),
            'cannot_subscribe_free'  => $this->error('O plano gratuito não requer assinatura.', [], 422),
            default                  => $this->error('Não foi possível processar a assinatura.', [], 422),
        };
    }
}
