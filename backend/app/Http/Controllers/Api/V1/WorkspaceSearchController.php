<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CalendarEvent;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\WorkspacePost;
use App\Models\WorkspaceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Busca global do Workspace (Ctrl+K): pessoas, publicações, eventos e solicitações — sempre respeitando quem pode ver o quê. */
class WorkspaceSearchController extends WorkspaceBaseController
{
    private const LIMIT = 5;

    /** Sem acento e sem diferenciar maiúsculas; curingas do LIKE são escapados. */
    private function like(Builder $q, string $column, string $term): Builder
    {
        return $q->whereRaw("immutable_unaccent({$column}) ILIKE immutable_unaccent(?)", ['%' . addcslashes($term, '%_\\') . '%']);
    }

    /** GET /workspaces/{slug}/search?q= */
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:60']]);
        $term = trim($data['q']);
        $isAdmin = $this->access->isAdmin($m);
        $meId = $request->user()->id;

        // Pessoas
        $people = HotelUser::with(['user', 'department'])->where('hotel_id', $hotel->id)->where('status', 'approved')
            ->where(function (Builder $q) use ($term) {
                $q->whereHas('user', fn (Builder $u) => $this->like($u, 'name', $term))
                    ->orWhere(fn (Builder $b) => $this->like($b, 'job_title', $term))
                    ->orWhereHas('department', fn (Builder $d) => $this->like($d, 'name', $term));
            })->limit(self::LIMIT)->get()
            ->map(fn (HotelUser $hu) => $this->access->person($hu->user, $hu) + ['department' => $hu->department?->name])->values();

        // Publicações do mural (com o mesmo filtro de público do mural)
        $posts = $this->like(WorkspacePost::with('author')->where('hotel_id', $hotel->id), 'body', $term)
            ->when(! $isAdmin, fn (Builder $q) => $q->where(function (Builder $w) use ($m) {
                $w->whereNull('department_id')->orWhere('author_id', $m->user_id);
                if ($m->department_id) {
                    $w->orWhere('department_id', $m->department_id);
                }
            }))->orderByDesc('created_at')->limit(self::LIMIT)->get()
            ->map(fn ($p) => [
                'uuid' => $p->uuid, 'excerpt' => Str::limit(preg_replace('/@\[([^\]\n]{1,80})\]\([0-9a-fA-F-]{36}\)/', '@$1', (string) $p->body) ?: 'Publicação com fotos', 110),
                'author' => $p->author?->name, 'created_at' => $p->created_at?->toISOString(),
            ])->values();

        // Eventos e feriados do calendário
        $events = $this->like(CalendarEvent::where('hotel_id', $hotel->id), 'title', $term)
            ->when(! $isAdmin, fn (Builder $q) => $q->where(function (Builder $w) use ($m, $meId) {
                $w->whereNull('department_id')->orWhere('created_by', $meId);
                if ($m->department_id) {
                    $w->orWhere('department_id', $m->department_id);
                }
            }))->orderByDesc('starts_on')->limit(self::LIMIT)->get()
            ->map(fn ($e) => ['uuid' => $e->uuid, 'title' => $e->title, 'kind' => $e->kind, 'date' => $e->starts_on->toDateString()])->values();

        // Solicitações: as minhas, as da minha equipe (gestor/chefe) ou todas (RH)
        $requests = $this->like(WorkspaceRequest::with('requester')->where('hotel_id', $hotel->id), 'title', $term);
        if (! $isAdmin) {
            $requests->whereIn('requester_id', array_merge([$meId], $this->access->reportIds($request->user(), $hotel)));
        }
        $requests = $requests->orderByDesc('created_at')->limit(self::LIMIT)->get()
            ->map(fn ($r) => ['uuid' => $r->uuid, 'title' => $r->title, 'status' => $r->status, 'type' => $r->type, 'requester' => $r->requester?->name])->values();

        return $this->success('Busca concluída.', compact('people', 'posts', 'events', 'requests'));
    }
}
