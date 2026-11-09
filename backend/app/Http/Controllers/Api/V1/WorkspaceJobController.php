<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\CareerEntry;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\WorkspaceJob;
use App\Models\WorkspaceJobApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Vagas da empresa. Quem tem equipe (gestor) ou é RH/dono abre vagas; todos veem as abertas e podem se candidatar.
 * A candidatura é confidencial: só quem abriu a vaga e o RH leem — o gestor atual do candidato NÃO é avisado.
 */
class WorkspaceJobController extends WorkspaceBaseController
{
    private function canManageJobs(Request $request, Hotel $hotel, ?HotelUser $m): bool
    {
        return $m && ($this->access->isAdmin($m) || $this->access->isManager($request->user(), $hotel));
    }

    private function canEdit(Request $request, WorkspaceJob $job, ?HotelUser $m): bool
    {
        return $m && ($this->access->isAdmin($m) || $job->created_by === $request->user()->id);
    }

    private function find(Hotel $hotel, string $uuid): ?WorkspaceJob
    {
        return WorkspaceJob::with(['department', 'creator'])->where('hotel_id', $hotel->id)->where('uuid', $uuid)->first();
    }

    private function format(WorkspaceJob $j, Request $request, ?HotelUser $m, ?bool $applied = null): array
    {
        $manage = $this->canEdit($request, $j, $m);

        return [
            'uuid' => $j->uuid, 'title' => $j->title, 'description' => $j->description, 'requirements' => $j->requirements,
            'location' => $j->location, 'contract_type' => $j->contract_type, 'contact' => $j->contact,
            'department' => $j->department?->name, 'department_uuid' => $j->department?->uuid,
            'status' => $j->isOpen() ? 'open' : 'closed', 'public' => $j->public, 'closes_on' => $j->closes_on?->toDateString(),
            'created_at' => $j->created_at?->toIso8601String(),
            'author' => $this->access->person($j->creator),
            'applied' => $applied ?? WorkspaceJobApplication::where('workspace_job_id', $j->id)->where('user_id', $request->user()->id)->exists(),
            'can_edit' => $manage,
            'applications_count' => $manage ? $j->applications()->count() : null,
            'share_url' => $j->public ? "/vagas/{$j->share_token}" : null,
        ];
    }

    /** GET /workspaces/{slug}/jobs */
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $mine = WorkspaceJobApplication::where('user_id', $request->user()->id)->pluck('workspace_job_id')->flip();
        $jobs = WorkspaceJob::with(['department', 'creator'])->where('hotel_id', $hotel->id)->orderByDesc('created_at')->limit(100)->get()
            ->filter(fn (WorkspaceJob $j) => $j->isOpen() || $this->canEdit($request, $j, $m))
            ->map(fn (WorkspaceJob $j) => $this->format($j, $request, $m, $mine->has($j->id)))->values();

        return $this->success('Vagas carregadas.', ['items' => $jobs, 'can_create' => $this->canManageJobs($request, $hotel, $m)]);
    }

    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'], 'description' => ['required', 'string', 'max:5000'],
            'requirements' => ['nullable', 'string', 'max:3000'], 'location' => ['nullable', 'string', 'max:120'],
            'contract_type' => ['nullable', 'string', 'max:30'], 'contact' => ['nullable', 'string', 'max:120'],
            'department_uuid' => ['nullable', 'uuid'], 'public' => ['sometimes', 'boolean'],
            'closes_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    /** POST /workspaces/{slug}/jobs */
    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $this->canManageJobs($request, $hotel, $m)) {
            return $this->forbidden('Somente gestores e o RH abrem vagas.');
        }
        $data = $request->validate($this->rules());
        $dept = ! empty($data['department_uuid']) ? $hotel->departments()->where('uuid', $data['department_uuid'])->first() : null;
        if (! empty($data['department_uuid']) && ! $dept) {
            return $this->error('Setor inválido.', ['department_uuid' => ['Setor não encontrado.']], 422);
        }
        unset($data['department_uuid']);

        $job = WorkspaceJob::create($data + [
            'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'department_id' => $dept?->id ?? $m->department_id,
            'created_by' => $request->user()->id, 'share_token' => Str::random(32), 'status' => 'open',
        ]);
        AuditLog::record($request, $hotel, 'job.created', null, ['job' => $job->uuid, 'public' => $job->public]);

        return $this->created('Vaga publicada.', $this->format($job->load(['department', 'creator']), $request, $m, false));
    }

    /** PATCH /workspaces/{slug}/jobs/{uuid} — edita, encerra ou reabre (quem abriu ou o RH). */
    public function update(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $job = $m ? $this->find($hotel, $uuid) : null;
        if (! $job || ! $this->canEdit($request, $job, $m)) {
            return $this->notFound('Vaga não encontrada.');
        }
        $rules = array_map(fn ($r) => array_merge(['sometimes'], array_diff($r, ['required'])), $this->rules());
        $rules['status'] = ['sometimes', 'in:open,closed'];
        $data = $request->validate($rules);
        if (array_key_exists('department_uuid', $data)) {
            $data['department_id'] = $data['department_uuid'] ? $hotel->departments()->where('uuid', $data['department_uuid'])->value('id') : null;
            unset($data['department_uuid']);
        }
        $job->update($data);
        AuditLog::record($request, $hotel, 'job.updated', null, ['job' => $job->uuid, 'fields' => array_keys($data)]);

        return $this->success('Vaga atualizada.', $this->format($job->refresh()->load(['department', 'creator']), $request, $m));
    }

    /** DELETE /workspaces/{slug}/jobs/{uuid} */
    public function destroy(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $job = $m ? $this->find($hotel, $uuid) : null;
        if (! $job || ! $this->canEdit($request, $job, $m)) {
            return $this->notFound('Vaga não encontrada.');
        }
        $job->delete();
        AuditLog::record($request, $hotel, 'job.deleted', null, ['job' => $uuid]);

        return $this->success('Vaga removida.', null);
    }

    /** POST /workspaces/{slug}/jobs/{uuid}/apply  { message? } */
    public function apply(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $job = $m ? $this->find($hotel, $uuid) : null;
        if (! $job || ! $job->isOpen()) {
            return $this->notFound('Vaga não encontrada ou encerrada.');
        }
        if ($job->created_by === $request->user()->id) {
            return $this->error('Você abriu esta vaga.', [], 422);
        }
        $data = $request->validate(['message' => ['nullable', 'string', 'max:1000']]);

        $app = WorkspaceJobApplication::firstOrCreate(
            ['workspace_job_id' => $job->id, 'user_id' => $request->user()->id],
            ['uuid' => (string) Str::uuid(), 'message' => $data['message'] ?? null, 'status' => 'new'],
        );
        if ($app->wasRecentlyCreated && $job->creator) {
            $this->notifications->workspaceNotice($job->creator, $request->user(), 'workspace_job_application', "Nova candidatura: {$job->title}", $request->user()->name, $this->link($hotel, 'vagas'));
        }

        return $this->success('Candidatura enviada.', ['applied' => true]);
    }

    /** DELETE /workspaces/{slug}/jobs/{uuid}/apply — retira a candidatura. */
    public function withdraw(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $job = $m ? $this->find($hotel, $uuid) : null;
        if (! $job) {
            return $this->notFound('Vaga não encontrada.');
        }
        WorkspaceJobApplication::where('workspace_job_id', $job->id)->where('user_id', $request->user()->id)->delete();

        return $this->success('Candidatura retirada.', ['applied' => false]);
    }

    /** GET /workspaces/{slug}/jobs/{uuid}/applications — só quem abriu a vaga e o RH. */
    public function applications(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $job = $m ? $this->find($hotel, $uuid) : null;
        if (! $job || ! $this->canEdit($request, $job, $m)) {
            return $this->notFound('Vaga não encontrada.');
        }
        $rows = $job->applications()->with('user')->orderBy('created_at')->get()->map(function ($a) use ($hotel) {
            $hu = HotelUser::with('department')->where('hotel_id', $hotel->id)->where('user_id', $a->user_id)->first();

            return [
                'uuid' => $a->uuid, 'status' => $a->status, 'message' => $a->message, 'created_at' => $a->created_at?->toIso8601String(),
                'person' => $this->access->person($a->user, $hu), 'department' => $hu?->department?->name,
            ];
        });
        AuditLog::record($request, $hotel, 'job.applications_viewed', null, ['job' => $job->uuid]);

        return $this->success('Candidaturas carregadas.', $rows);
    }

    /** PATCH /workspaces/{slug}/jobs/{uuid}/applications/{app}  { status } */
    public function setApplicationStatus(Request $request, Hotel $hotel, string $uuid, string $app): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $job = $m ? $this->find($hotel, $uuid) : null;
        if (! $job || ! $this->canEdit($request, $job, $m)) {
            return $this->notFound('Vaga não encontrada.');
        }
        $data = $request->validate(['status' => ['required', 'in:new,reviewing,rejected,hired']]);
        $a = $job->applications()->where('uuid', $app)->first();
        if (! $a) {
            return $this->notFound('Candidatura não encontrada.');
        }
        $becameHired = $data['status'] === 'hired' && $a->status !== 'hired';
        $a->update(['status' => $data['status']]);
        if ($becameHired) {
            $this->moveToNewRole($request, $hotel, $job, $a);
        }

        return $this->success('Candidatura atualizada.', ['status' => $a->status]);
    }

    /** Contratação interna: encerra o cargo atual no histórico de carreira, abre o novo e atualiza cargo/setor da pessoa. */
    private function moveToNewRole(Request $request, Hotel $hotel, WorkspaceJob $job, WorkspaceJobApplication $app): void
    {
        $member = HotelUser::with('department')->where('hotel_id', $hotel->id)->where('user_id', $app->user_id)->where('status', 'approved')->first();
        if (! $member) {
            return;
        }
        $today = now('America/Sao_Paulo')->toDateString();

        DB::transaction(function () use ($hotel, $job, $member, $today) {
            $open = CareerEntry::where('hotel_id', $hotel->id)->where('user_id', $member->user_id)->whereNull('ended_on')->get();
            if ($open->isEmpty() && $member->job_title) {
                // pessoa sem histórico: registra o cargo que ela ocupava até aqui
                CareerEntry::create([
                    'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'user_id' => $member->user_id, 'title' => $member->job_title,
                    'department' => $member->department?->name, 'started_on' => $member->hired_at?->toDateString() ?? $today, 'ended_on' => $today, 'source' => 'manual',
                ]);
            }
            $open->each(fn (CareerEntry $e) => $e->update(['ended_on' => $e->started_on->toDateString() > $today ? $e->started_on->toDateString() : $today]));

            CareerEntry::create([
                'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'user_id' => $member->user_id, 'title' => $job->title,
                'department' => $job->department?->name ?? $member->department?->name, 'started_on' => $today, 'source' => 'job',
                'note' => 'Contratação interna pela vaga publicada no Workspace.',
            ]);
            $member->forceFill(['job_title' => $job->title] + ($job->department_id ? ['department_id' => $job->department_id] : []))->save();
        });

        AuditLog::record($request, $hotel, 'career.promoted', $member->user, ['job' => $job->uuid]);
        if ($member->user) {
            $this->notifications->workspaceNotice($member->user, $request->user(), 'workspace_job_application', "Você foi selecionado(a): {$job->title}", 'Seu histórico de carreira foi atualizado.', $this->link($hotel, 'perfil'));
        }
    }

    /** GET /public/jobs/{token} — página de divulgação (sem login), só de vagas marcadas como públicas e abertas. */
    public function publicShow(string $token): JsonResponse
    {
        $j = WorkspaceJob::with(['hotel', 'department'])->where('share_token', $token)->where('public', true)->first();
        if (! $j || ! $j->isOpen()) {
            return $this->notFound('Esta vaga não está mais disponível.');
        }

        return $this->success('Vaga carregada.', [
            'title' => $j->title, 'description' => $j->description, 'requirements' => $j->requirements, 'location' => $j->location,
            'contract_type' => $j->contract_type, 'contact' => $j->contact, 'department' => $j->department?->name,
            'company' => $j->hotel?->name, 'closes_on' => $j->closes_on?->toDateString(),
        ]);
    }
}
