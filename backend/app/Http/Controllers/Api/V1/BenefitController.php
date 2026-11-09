<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Services\Benefits;
use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Models\AuditLog;
use App\Models\BenefitPackage;
use App\Models\BenefitType;
use App\Models\EmployeeBenefit;
use App\Models\Hotel;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Benefícios. Cada colaborador vê SÓ os seus (/me/benefits, sempre pelo usuário logado); dono e RH cadastram por pessoa,
 * mantêm o catálogo e os pacotes. Valores ficam criptografados e fora de listagens, perfil e relatórios.
 */
class BenefitController extends WorkspaceBaseController
{
    public function __construct(WorkspaceAccess $access, NotificationService $notifications, private readonly Benefits $benefits)
    {
        parent::__construct($access, $notifications);
    }

    private function admin(Request $request, Hotel $hotel): bool
    {
        return $this->access->isAdmin($this->membership($request, $hotel));
    }

    private function deny(): JsonResponse
    {
        return $this->forbidden('Somente o dono ou o RH gerenciam benefícios.');
    }

    private function listFor(Hotel $hotel, int $userId): array
    {
        $items = EmployeeBenefit::with('type')->where('hotel_id', $hotel->id)->where('user_id', $userId)->get()
            ->map(fn ($b) => $this->benefits->present($b))
            ->sortBy([['active', 'desc'], ['type.name', 'asc']])->values();

        return [
            'items' => $items,
            // soma só do que está vigente, é mensal e tem valor
            'total_monthly_cents' => (int) $items->where('active', true)->where('frequency', 'monthly')->sum(fn ($i) => $i['amount_cents'] ?? 0),
        ];
    }

    /** GET /me/benefits — só os benefícios de quem está logado. */
    public function mine(Request $request, Hotel $hotel): JsonResponse
    {
        if (! $this->membership($request, $hotel)) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        return $this->success('Benefícios carregados.', $this->listFor($hotel, $request->user()->id))->header('Cache-Control', 'private, no-store');
    }

    // ── Por pessoa (dono/RH) ──────────────────────────────────

    private function target(Request $request, Hotel $hotel, string $uuid)
    {
        return $this->access->memberByUuid($hotel, $uuid);
    }

    /** GET /people/{uuid}/benefits */
    public function index(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        if (! $this->admin($request, $hotel)) {
            return $this->deny();
        }
        $t = $this->target($request, $hotel, $uuid);
        if (! $t) {
            return $this->notFound('Pessoa não encontrada.');
        }
        if ($t->user_id !== $request->user()->id) {
            AuditLog::record($request, $hotel, 'benefits.viewed', $t->user);
        }

        return $this->success('Benefícios carregados.', $this->listFor($hotel, $t->user_id))->header('Cache-Control', 'private, no-store');
    }

    private function rules(): array
    {
        return [
            'benefit_type_uuid' => ['required', 'uuid'],
            'amount'      => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'frequency'   => ['required', Rule::in(['monthly', 'one_time'])],
            'deposit_day' => ['nullable', 'integer', 'between:1,31', 'required_if:frequency,monthly'],
            'deposit_on'  => ['nullable', 'date_format:Y-m-d', 'required_if:frequency,one_time'],
            'starts_on'   => ['nullable', 'date_format:Y-m-d'],
            'ends_on'     => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'note'        => ['nullable', 'string', 'max:200'],
        ];
    }

    private function attributes(array $d, BenefitType $type): array
    {
        $monthly = $d['frequency'] === 'monthly';

        return [
            'benefit_type_id' => $type->id,
            'amount' => isset($d['amount']) ? (string) (int) round($d['amount'] * 100) : null,
            'frequency' => $d['frequency'],
            'deposit_day' => $monthly ? $d['deposit_day'] : null,
            'deposit_on' => $monthly ? null : $d['deposit_on'],
            'starts_on' => $d['starts_on'] ?? null, 'ends_on' => $d['ends_on'] ?? null,
            'note' => ($d['note'] ?? null) ? trim($d['note']) : null,
        ];
    }

    private function notifyPerson(Request $request, Hotel $hotel, $target, string $what): void
    {
        if ($target->user && $target->user_id !== $request->user()->id) {
            $this->notifications->workspaceNotice($target->user, $request->user(), 'workspace_payslip', 'Seus benefícios foram atualizados', $what, $this->link($hotel, 'beneficios'));
        }
    }

    /** POST /people/{uuid}/benefits */
    public function store(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        if (! $this->admin($request, $hotel)) {
            return $this->deny();
        }
        $t = $this->target($request, $hotel, $uuid);
        if (! $t) {
            return $this->notFound('Pessoa não encontrada.');
        }
        $d = $request->validate($this->rules());
        $type = BenefitType::where('hotel_id', $hotel->id)->where('uuid', $d['benefit_type_uuid'])->where('active', true)->first();
        if (! $type) {
            return $this->error('Tipo de benefício inválido.', ['benefit_type_uuid' => ['Escolha um tipo ativo.']], 422);
        }
        if (EmployeeBenefit::where('hotel_id', $hotel->id)->where('user_id', $t->user_id)->where('benefit_type_id', $type->id)->whereNull('ends_on')->exists()) {
            return $this->error('Esta pessoa já tem esse benefício vigente. Edite o existente ou defina uma data de término.', [], 422);
        }

        EmployeeBenefit::create($this->attributes($d, $type) + ['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'user_id' => $t->user_id, 'created_by' => $request->user()->id]);
        AuditLog::record($request, $hotel, 'benefits.added', $t->user, ['type' => $type->name]);
        $this->notifyPerson($request, $hotel, $t, "Novo benefício: {$type->name}");

        return $this->created('Benefício adicionado.', $this->listFor($hotel, $t->user_id));
    }

    /** PUT /people/{uuid}/benefits/{id} */
    public function update(Request $request, Hotel $hotel, string $uuid, string $id): JsonResponse
    {
        if (! $this->admin($request, $hotel)) {
            return $this->deny();
        }
        $t = $this->target($request, $hotel, $uuid);
        $b = $t ? EmployeeBenefit::where('hotel_id', $hotel->id)->where('user_id', $t->user_id)->where('uuid', $id)->first() : null;
        if (! $b) {
            return $this->notFound('Benefício não encontrado.');
        }
        $d = $request->validate($this->rules());
        $type = BenefitType::where('hotel_id', $hotel->id)->where('uuid', $d['benefit_type_uuid'])->first();
        if (! $type) {
            return $this->error('Tipo de benefício inválido.', ['benefit_type_uuid' => ['Tipo não encontrado.']], 422);
        }
        $b->update($this->attributes($d, $type));
        AuditLog::record($request, $hotel, 'benefits.updated', $t->user, ['type' => $type->name]);
        $this->notifyPerson($request, $hotel, $t, "Benefício alterado: {$type->name}");

        return $this->success('Benefício atualizado.', $this->listFor($hotel, $t->user_id));
    }

    /** DELETE /people/{uuid}/benefits/{id} */
    public function destroy(Request $request, Hotel $hotel, string $uuid, string $id): JsonResponse
    {
        if (! $this->admin($request, $hotel)) {
            return $this->deny();
        }
        $t = $this->target($request, $hotel, $uuid);
        $b = $t ? EmployeeBenefit::with('type')->where('hotel_id', $hotel->id)->where('user_id', $t->user_id)->where('uuid', $id)->first() : null;
        if (! $b) {
            return $this->notFound('Benefício não encontrado.');
        }
        $name = $b->type?->name;
        $b->delete();
        AuditLog::record($request, $hotel, 'benefits.removed', $t->user, ['type' => $name]);
        $this->notifyPerson($request, $hotel, $t, "Benefício removido: {$name}");

        return $this->success('Benefício removido.', $this->listFor($hotel, $t->user_id));
    }

    /** POST /people/{uuid}/benefits/apply-package { package_uuid } */
    public function applyPackage(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        if (! $this->admin($request, $hotel)) {
            return $this->deny();
        }
        $t = $this->target($request, $hotel, $uuid);
        $d = $request->validate(['package_uuid' => ['required', 'uuid']]);
        $pkg = BenefitPackage::where('hotel_id', $hotel->id)->where('uuid', $d['package_uuid'])->first();
        if (! $t || ! $pkg) {
            return $this->notFound('Pessoa ou pacote não encontrado.');
        }
        $n = $this->benefits->applyPackage($pkg, $hotel, $t->user_id, $request->user()->id);
        AuditLog::record($request, $hotel, 'benefits.package_applied', $t->user, ['package' => $pkg->name, 'created' => $n]);
        if ($n) {
            $this->notifyPerson($request, $hotel, $t, "Pacote aplicado: {$pkg->name}");
        }

        return $this->success($n ? "Pacote aplicado ({$n} benefício(s) novo(s))." : 'A pessoa já tinha todos os benefícios deste pacote.', $this->listFor($hotel, $t->user_id));
    }

    // ── Catálogo ──────────────────────────────────────────────

    private function typesPayload(Hotel $hotel): array
    {
        $this->benefits->ensureCatalog($hotel);

        return [
            'types' => BenefitType::where('hotel_id', $hotel->id)->orderBy('sort')->orderBy('name')->get()
                ->map(fn ($t) => ['uuid' => $t->uuid, 'name' => $t->name, 'color' => $t->color, 'active' => $t->active])->values(),
            'colors' => BenefitType::COLORS,
        ];
    }

    /** GET /benefit-types */
    public function types(Request $request, Hotel $hotel): JsonResponse
    {
        return $this->admin($request, $hotel) ? $this->success('Tipos carregados.', $this->typesPayload($hotel)) : $this->deny();
    }

    /** POST /benefit-types */
    public function storeType(Request $request, Hotel $hotel): JsonResponse
    {
        if (! $this->admin($request, $hotel)) {
            return $this->deny();
        }
        $d = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60', Rule::unique('benefit_types', 'name')->where('hotel_id', $hotel->id)],
            'color' => ['required', Rule::in(BenefitType::COLORS)],
        ]);
        BenefitType::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'name' => trim($d['name']), 'color' => $d['color'], 'sort' => 100]);

        return $this->created('Tipo criado.', $this->typesPayload($hotel));
    }

    /** PATCH /benefit-types/{uuid} — renomear, trocar a cor, ativar/desativar (nunca apaga: benefícios antigos mantêm o histórico). */
    public function updateType(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        if (! $this->admin($request, $hotel)) {
            return $this->deny();
        }
        $type = BenefitType::where('hotel_id', $hotel->id)->where('uuid', $uuid)->first();
        if (! $type) {
            return $this->notFound('Tipo não encontrado.');
        }
        $d = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:60', Rule::unique('benefit_types', 'name')->where('hotel_id', $hotel->id)->ignore($type->id)],
            'color' => ['sometimes', Rule::in(BenefitType::COLORS)],
            'active' => ['sometimes', 'boolean'],
        ]);
        $type->update($d);

        return $this->success('Tipo atualizado.', $this->typesPayload($hotel));
    }

    // ── Pacotes ───────────────────────────────────────────────

    private function packagesPayload(Hotel $hotel): array
    {
        $types = BenefitType::where('hotel_id', $hotel->id)->get()->keyBy('uuid');

        return ['packages' => BenefitPackage::where('hotel_id', $hotel->id)->orderBy('name')->get()->map(fn ($p) => [
            'uuid' => $p->uuid, 'name' => $p->name,
            'items' => collect($p->items)->map(fn ($i) => [
                'benefit_type_uuid' => $i['benefit_type_uuid'], 'name' => $types[$i['benefit_type_uuid']]->name ?? '—', 'color' => $types[$i['benefit_type_uuid']]->color ?? 'gray',
                'amount_cents' => $i['amount_cents'] ?? null, 'deposit_day' => $i['deposit_day'] ?? null, 'note' => $i['note'] ?? null,
            ])->values(),
        ])->values()];
    }

    private function packageData(Request $request, Hotel $hotel, ?BenefitPackage $current = null): array|JsonResponse
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', Rule::unique('benefit_packages', 'name')->where('hotel_id', $hotel->id)->ignore($current?->id)],
            'items' => ['required', 'array', 'min:1', 'max:12'],
            'items.*.benefit_type_uuid' => ['required', 'uuid'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'items.*.deposit_day' => ['nullable', 'integer', 'between:1,31'],
            'items.*.note' => ['nullable', 'string', 'max:200'],
        ]);
        $valid = BenefitType::where('hotel_id', $hotel->id)->pluck('uuid')->all();
        $items = [];
        foreach ($d['items'] as $i) {
            if (! in_array($i['benefit_type_uuid'], $valid, true)) {
                return $this->error('Tipo de benefício inválido no pacote.', [], 422);
            }
            $items[] = ['benefit_type_uuid' => $i['benefit_type_uuid'], 'amount_cents' => isset($i['amount']) ? (int) round($i['amount'] * 100) : null,
                'deposit_day' => $i['deposit_day'] ?? null, 'note' => $i['note'] ?? null];
        }

        return ['name' => trim($d['name']), 'items' => $items];
    }

    /** GET /benefit-packages */
    public function packages(Request $request, Hotel $hotel): JsonResponse
    {
        return $this->admin($request, $hotel) ? $this->success('Pacotes carregados.', $this->packagesPayload($hotel)) : $this->deny();
    }

    /** POST /benefit-packages */
    public function storePackage(Request $request, Hotel $hotel): JsonResponse
    {
        if (! $this->admin($request, $hotel)) {
            return $this->deny();
        }
        $data = $this->packageData($request, $hotel);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        BenefitPackage::create($data + ['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id]);

        return $this->created('Pacote criado.', $this->packagesPayload($hotel));
    }

    /** PUT /benefit-packages/{uuid} */
    public function updatePackage(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        if (! $this->admin($request, $hotel)) {
            return $this->deny();
        }
        $pkg = BenefitPackage::where('hotel_id', $hotel->id)->where('uuid', $uuid)->first();
        if (! $pkg) {
            return $this->notFound('Pacote não encontrado.');
        }
        $data = $this->packageData($request, $hotel, $pkg);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $pkg->update($data);

        return $this->success('Pacote atualizado.', $this->packagesPayload($hotel));
    }

    /** DELETE /benefit-packages/{uuid} — convites que usavam o pacote seguem valendo, sem pacote. */
    public function destroyPackage(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        if (! $this->admin($request, $hotel)) {
            return $this->deny();
        }
        $pkg = BenefitPackage::where('hotel_id', $hotel->id)->where('uuid', $uuid)->first();
        if (! $pkg) {
            return $this->notFound('Pacote não encontrado.');
        }
        $pkg->delete();

        return $this->success('Pacote removido.', $this->packagesPayload($hotel));
    }
}
