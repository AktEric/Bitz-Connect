<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Chat\Services\ChatGroupService;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Diretório interno da empresa: setores e membros (aprovados).
 * Visível apenas a quem tem vínculo aprovado; edição restrita a administradores.
 */
class CompanyDirectoryController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly ChatGroupService $service) {}

    // ── Setores ───────────────────────────────────────────────

    /** GET /hotels/{hotel:slug}/departments */
    public function departments(Request $request, Hotel $hotel): JsonResponse
    {
        if (! $this->service->isCompanyMember($request->user(), $hotel->id)) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        $data = $hotel->departments()
            ->withCount(['hotelUsers as members_count' => fn ($q) => $q->where('status', 'approved')])
            ->orderBy('name')
            ->get()
            ->map(fn (Department $d) => $this->formatDepartment($d));

        return $this->success('Setores carregados com sucesso.', $data);
    }

    /** GET /segment-templates — modelos de setores e cargos por segmento. */
    public function segmentTemplates(): JsonResponse
    {
        $data = collect(config('segment_templates'))
            ->map(fn ($t, $key) => ['key' => $key] + $t)
            ->values();

        return $this->success('Modelos carregados com sucesso.', $data);
    }

    /** POST /hotels/{hotel:slug}/segment-template — cria os setores do modelo que ainda não existem. */
    public function applySegmentTemplate(Request $request, Hotel $hotel): JsonResponse
    {
        if ($denied = $this->denyUnlessAdmin($request, $hotel)) {
            return $denied;
        }

        $data = $request->validate([
            'segment' => ['required', 'string', Rule::in(array_keys(config('segment_templates')))],
        ]);

        $created = [];
        foreach (config("segment_templates.{$data['segment']}.departments") as $name) {
            $department = $hotel->departments()->firstOrCreate(
                ['name' => $name],
                ['uuid' => (string) Str::uuid()]
            );
            if ($department->wasRecentlyCreated) {
                $created[] = $name;
            }
        }

        return $this->success('Modelo aplicado com sucesso.', ['created' => $created]);
    }

    /** POST /hotels/{hotel:slug}/departments */
    public function storeDepartment(Request $request, Hotel $hotel): JsonResponse
    {
        if ($denied = $this->denyUnlessAdmin($request, $hotel)) {
            return $denied;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->where('hotel_id', $hotel->id)],
        ]);

        $department = $hotel->departments()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
        ]);

        return $this->created('Setor criado com sucesso.', $this->formatDepartment($department));
    }

    /** PUT /hotels/{hotel:slug}/departments/{department} */
    public function updateDepartment(Request $request, Hotel $hotel, Department $department): JsonResponse
    {
        if ($denied = $this->denyUnlessAdmin($request, $hotel)) {
            return $denied;
        }

        if ($department->hotel_id !== $hotel->id) {
            return $this->notFound('Setor não encontrado.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->where('hotel_id', $hotel->id)->ignore($department->id)],
        ]);

        $department->update($data);

        return $this->success('Setor atualizado com sucesso.', $this->formatDepartment($department));
    }

    /** DELETE /hotels/{hotel:slug}/departments/{department} */
    public function destroyDepartment(Request $request, Hotel $hotel, Department $department): JsonResponse
    {
        if ($denied = $this->denyUnlessAdmin($request, $hotel)) {
            return $denied;
        }

        if ($department->hotel_id !== $hotel->id) {
            return $this->notFound('Setor não encontrado.');
        }

        $department->delete(); // department_id dos vínculos vira null (nullOnDelete)

        return $this->success('Setor removido com sucesso.');
    }

    // ── Membros ───────────────────────────────────────────────

    /** GET /hotels/{hotel:slug}/members — administradores e funcionários aprovados. */
    public function members(Request $request, Hotel $hotel): JsonResponse
    {
        if (! $this->service->isCompanyMember($request->user(), $hotel->id)) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        $data = HotelUser::with(['user', 'department', 'manager'])
            ->where('hotel_id', $hotel->id)
            ->where('status', 'approved')
            ->get()
            ->filter(fn (HotelUser $hu) => $hu->user)
            ->sortBy(fn (HotelUser $hu) => mb_strtolower($hu->user->name))
            ->map(fn (HotelUser $hu) => $this->formatMember($hu))
            ->values();

        return $this->success('Membros carregados com sucesso.', $data);
    }

    /** PATCH /hotels/{hotel:slug}/members/{user}/department */
    public function setMemberDepartment(Request $request, Hotel $hotel, User $user): JsonResponse
    {
        if ($denied = $this->denyUnlessAdmin($request, $hotel)) {
            return $denied;
        }

        $data = $request->validate(['department_uuid' => ['nullable', 'string']]);

        $hotelUser = HotelUser::where('hotel_id', $hotel->id)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->first();

        if (! $hotelUser) {
            return $this->notFound('Essa pessoa não faz parte da empresa.');
        }

        $departmentId = null;
        if (! empty($data['department_uuid'])) {
            $department = $hotel->departments()->where('uuid', $data['department_uuid'])->first();
            if (! $department) {
                return $this->notFound('Setor não encontrado.');
            }
            $departmentId = $department->id;
        }

        $hotelUser->update(['department_id' => $departmentId]);

        return $this->success('Setor atualizado com sucesso.');
    }

    /** GET /hotels/{hotel:slug}/members/{user} — perfil interno de um colega. */
    public function memberProfile(Request $request, Hotel $hotel, User $user): JsonResponse
    {
        if (! $this->service->isCompanyMember($request->user(), $hotel->id)) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        $hu = HotelUser::with(['user', 'department', 'manager'])
            ->where('hotel_id', $hotel->id)->where('user_id', $user->id)->where('status', 'approved')->first();

        if (! $hu) {
            return $this->notFound('Essa pessoa não faz parte da empresa.');
        }

        return $this->success('Perfil carregado com sucesso.', $this->formatMember($hu) + [
            'bio'   => $user->bio,
            'city'  => $user->city,
            'state' => $user->state,
            'reports_count' => HotelUser::where('hotel_id', $hotel->id)->where('manager_id', $user->id)->where('status', 'approved')->count(),
        ]);
    }

    /** PATCH /hotels/{hotel:slug}/members/{user} — admin edita cargo, admissão, gestor e setor. */
    public function updateMemberProfile(Request $request, Hotel $hotel, User $user): JsonResponse
    {
        if ($denied = $this->denyUnlessAdmin($request, $hotel)) {
            return $denied;
        }

        $data = $request->validate([
            'job_title'       => ['sometimes', 'nullable', 'string', 'max:120'],
            'hired_at'        => ['sometimes', 'nullable', 'date'],
            'manager_uuid'    => ['sometimes', 'nullable', 'string'],
            'department_uuid' => ['sometimes', 'nullable', 'string'],
        ]);

        $hu = HotelUser::where('hotel_id', $hotel->id)->where('user_id', $user->id)->where('status', 'approved')->first();
        if (! $hu) {
            return $this->notFound('Essa pessoa não faz parte da empresa.');
        }

        $changes = [];

        foreach (['job_title', 'hired_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $changes[$field] = $data[$field] ?: null;
            }
        }

        if (array_key_exists('department_uuid', $data)) {
            $department = $data['department_uuid'] ? $hotel->departments()->where('uuid', $data['department_uuid'])->first() : null;
            if ($data['department_uuid'] && ! $department) {
                return $this->notFound('Setor não encontrado.');
            }
            $changes['department_id'] = $department?->id;
        }

        if (array_key_exists('manager_uuid', $data)) {
            $manager = null;
            if ($data['manager_uuid']) {
                $manager = HotelUser::where('hotel_id', $hotel->id)->where('status', 'approved')
                    ->whereHas('user', fn ($q) => $q->where('uuid', $data['manager_uuid']))->first();
                if (! $manager) {
                    return $this->notFound('Gestor não encontrado na empresa.');
                }
                if ($manager->user_id === $user->id) {
                    return $this->error('Uma pessoa não pode ser gestora de si mesma.', [], 422);
                }
                // sem ciclos: o novo gestor não pode estar abaixo desta pessoa na hierarquia
                $cursor = $manager;
                for ($i = 0; $cursor && $i < 20; $i++) {
                    if ($cursor->manager_id === $user->id) {
                        return $this->error('Isso criaria um ciclo na hierarquia (o gestor escolhido já responde a esta pessoa).', [], 422);
                    }
                    $cursor = $cursor->manager_id ? HotelUser::where('hotel_id', $hotel->id)->where('user_id', $cursor->manager_id)->first() : null;
                }
            }
            $changes['manager_id'] = $manager?->user_id;
        }

        $hu->update($changes);

        return $this->success('Perfil atualizado com sucesso.', $this->formatMember($hu->fresh(['user', 'department', 'manager'])));
    }

    // ── Helpers ───────────────────────────────────────────────

    private function formatMember(HotelUser $hu): array
    {
        return [
            'uuid'       => $hu->user->uuid,
            'name'       => $hu->user->name,
            'avatar_url' => $hu->user->avatar_path ? asset('storage/' . $hu->user->avatar_path) : null,
            'role'       => $hu->role,
            'job_title'  => $hu->job_title,
            'hired_at'   => $hu->hired_at?->toDateString(),
            'department' => $hu->department ? ['uuid' => $hu->department->uuid, 'name' => $hu->department->name] : null,
            'manager'    => $hu->manager ? ['uuid' => $hu->manager->uuid, 'name' => $hu->manager->name] : null,
        ];
    }

    private function denyUnlessAdmin(Request $request, Hotel $hotel): ?JsonResponse
    {
        return $this->service->isCompanyAdmin($request->user(), $hotel->id)
            ? null
            : $this->forbidden('Somente administradores da empresa podem fazer isso.');
    }

    private function formatDepartment(Department $department): array
    {
        return [
            'uuid'          => $department->uuid,
            'name'          => $department->name,
            'members_count' => (int) ($department->members_count
                ?? $department->hotelUsers()->where('status', 'approved')->count()),
        ];
    }
}
