<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Services\RequestApprovals;
use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\RequestFlow;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fluxos de aprovação por tipo de solicitação. Qualquer membro lê (para saber por onde o pedido vai passar);
 * só dono e RH alteram. A mudança vale para pedidos novos: os em andamento mantêm o fluxo com que foram criados.
 */
class RequestFlowController extends WorkspaceBaseController
{
    public function __construct(WorkspaceAccess $access, NotificationService $notifications, private readonly RequestApprovals $flow)
    {
        parent::__construct($access, $notifications);
    }

    private function payload(Hotel $hotel, bool $canEdit): array
    {
        $configured = RequestFlow::where('hotel_id', $hotel->id)->pluck('steps', 'type');

        return [
            'types' => collect(RequestApprovals::TYPES)->map(fn ($label, $type) => [
                'type' => $type, 'label' => $label,
                'steps' => $this->flow->stepsFor($hotel, $type),
                'is_default' => ! $configured->has($type),
            ])->values(),
            'roles'    => collect(RequestApprovals::ROLES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'can_edit' => $canEdit,
        ];
    }

    /** GET /workspaces/{slug}/request-flows */
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        return $this->success('Fluxos carregados.', $this->payload($hotel, $this->access->isAdmin($m)));
    }

    /** PUT /workspaces/{slug}/request-flows/{type}  { steps: ["manager","finance"] } */
    public function update(Request $request, Hotel $hotel, string $type): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $this->access->isAdmin($m)) {
            return $this->forbidden('Somente o dono ou o RH configuram os fluxos de aprovação.');
        }
        if (! isset(RequestApprovals::TYPES[$type])) {
            return $this->notFound('Tipo de solicitação não encontrado.');
        }
        $data = $request->validate([
            'steps'   => ['required', 'array', 'min:1', 'max:4'],
            'steps.*' => ['string', Rule::in(array_keys(RequestApprovals::ROLES))],
        ]);

        $steps = $this->flow->sanitize($data['steps']);
        RequestFlow::updateOrCreate(['hotel_id' => $hotel->id, 'type' => $type], ['steps' => $steps, 'updated_by' => $request->user()->id]);
        AuditLog::record($request, $hotel, 'request_flow.updated', null, ['type' => $type, 'steps' => $steps]);

        return $this->success('Fluxo atualizado. Vale para as próximas solicitações.', $this->payload($hotel, true));
    }
}
