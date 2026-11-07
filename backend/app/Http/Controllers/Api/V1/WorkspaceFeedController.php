<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\WorkspacePost;
use App\Models\WorkspacePostComment;
use App\Models\WorkspacePostLike;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Mural interno: novidades da empresa, com público-alvo por setor. */
class WorkspaceFeedController extends WorkspaceBaseController
{
    private function visible(Builder $q, Hotel $hotel, HotelUser $m): Builder
    {
        $q->where('hotel_id', $hotel->id);

        if ($m->role !== 'admin') {
            $q->where(function (Builder $w) use ($m) {
                $w->whereNull('department_id')->orWhere('author_id', $m->user_id);
                if ($m->department_id) {
                    $w->orWhere('department_id', $m->department_id);
                }
            });
        }

        return $q;
    }

    private function canPost(Hotel $hotel, HotelUser $m, Request $request): bool
    {
        return $m->role === 'admin' || $this->access->isManager($request->user(), $hotel);
    }

    private function format(WorkspacePost $p, int $meId, bool $isAdmin): array
    {
        return [
            'uuid'           => $p->uuid,
            'body'           => $p->body,
            'pinned'         => $p->pinned,
            'created_at'     => $p->created_at?->toISOString(),
            'author'         => $this->access->person($p->author),
            'department'     => $p->department?->name,
            'likes_count'    => (int) ($p->likes_count ?? 0),
            'comments_count' => (int) ($p->comments_count ?? 0),
            'liked_by_me'    => (bool) ($p->liked_by_me ?? false),
            'can_delete'     => $isAdmin || $p->author_id === $meId,
        ];
    }

    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        $meId = $request->user()->id;
        $q = $this->visible(WorkspacePost::query(), $hotel, $m)
            ->with(['author', 'department'])
            ->withCount(['likes', 'comments'])
            ->withExists(['likes as liked_by_me' => fn ($l) => $l->where('user_id', $meId)])
            ->orderByDesc('pinned')->orderByDesc('created_at')->orderByDesc('id');

        $page = $q->paginate(15);
        $isAdmin = $m->role === 'admin';

        return $this->success('Mural carregado com sucesso.', [
            'items'     => $page->getCollection()->map(fn ($p) => $this->format($p, $meId, $isAdmin))->values(),
            'page'      => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'can_post'  => $this->canPost($hotel, $m, $request),
        ]);
    }

    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m || ! $this->canPost($hotel, $m, $request)) {
            return $this->forbidden('Somente RH/gestores podem publicar no mural.');
        }

        $data = $request->validate([
            'body'            => ['required', 'string', 'max:3000'],
            'pinned'          => ['sometimes', 'boolean'],
            'department_uuid' => ['nullable', 'uuid'],
        ]);

        $deptId = null;
        if (! empty($data['department_uuid'])) {
            $dept = $hotel->departments()->where('uuid', $data['department_uuid'])->first();
            if (! $dept) {
                return $this->error('Setor inválido.', ['department_uuid' => ['Setor não encontrado.']], 422);
            }
            $deptId = $dept->id;
        }

        $post = WorkspacePost::create([
            'uuid'          => (string) Str::uuid(),
            'hotel_id'      => $hotel->id,
            'author_id'     => $request->user()->id,
            'department_id' => $deptId,
            'body'          => $data['body'],
            'pinned'        => $m->role === 'admin' && ($data['pinned'] ?? false),
        ]);
        $post->load(['author', 'department']);

        return $this->created('Publicação criada com sucesso.', $this->format($post, $request->user()->id, $m->role === 'admin'));
    }

    public function destroy(Request $request, Hotel $hotel, string $post): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $p = $m ? $this->visible(WorkspacePost::query(), $hotel, $m)->where('uuid', $post)->first() : null;
        if (! $p) {
            return $this->notFound('Publicação não encontrada.');
        }
        if ($m->role !== 'admin' && $p->author_id !== $request->user()->id) {
            return $this->forbidden('Você não pode remover esta publicação.');
        }
        $p->delete();

        return $this->success('Publicação removida com sucesso.');
    }

    public function pin(Request $request, Hotel $hotel, string $post): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m || $m->role !== 'admin') {
            return $this->forbidden('Somente o RH pode fixar publicações.');
        }
        $p = WorkspacePost::where('hotel_id', $hotel->id)->where('uuid', $post)->first();
        if (! $p) {
            return $this->notFound('Publicação não encontrada.');
        }
        $p->update(['pinned' => ! $p->pinned]);

        return $this->success('Publicação atualizada com sucesso.', ['pinned' => $p->pinned]);
    }

    public function like(Request $request, Hotel $hotel, string $post): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $p = $m ? $this->visible(WorkspacePost::query(), $hotel, $m)->where('uuid', $post)->first() : null;
        if (! $p) {
            return $this->notFound('Publicação não encontrada.');
        }

        $existing = WorkspacePostLike::where('workspace_post_id', $p->id)->where('user_id', $request->user()->id)->first();
        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            WorkspacePostLike::create(['workspace_post_id' => $p->id, 'user_id' => $request->user()->id]);
            $liked = true;
        }

        return $this->success('Curtida atualizada.', [
            'liked' => $liked,
            'likes_count' => WorkspacePostLike::where('workspace_post_id', $p->id)->count(),
        ]);
    }

    public function comments(Request $request, Hotel $hotel, string $post): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $p = $m ? $this->visible(WorkspacePost::query(), $hotel, $m)->where('uuid', $post)->first() : null;
        if (! $p) {
            return $this->notFound('Publicação não encontrada.');
        }

        $items = $p->comments()->with('author')->orderBy('created_at')->limit(200)->get()
            ->map(fn ($c) => [
                'uuid' => $c->uuid, 'body' => $c->body, 'created_at' => $c->created_at?->toISOString(),
                'author' => $this->access->person($c->author),
            ])->values();

        return $this->success('Comentários carregados com sucesso.', $items);
    }

    public function comment(Request $request, Hotel $hotel, string $post): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $p = $m ? $this->visible(WorkspacePost::query(), $hotel, $m)->where('uuid', $post)->first() : null;
        if (! $p) {
            return $this->notFound('Publicação não encontrada.');
        }

        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);
        $c = WorkspacePostComment::create([
            'uuid' => (string) Str::uuid(), 'workspace_post_id' => $p->id,
            'author_id' => $request->user()->id, 'body' => $data['body'],
        ]);
        $c->load('author');

        if ($p->author && $p->author_id !== $request->user()->id) {
            $this->notifications->workspaceNotice(
                $p->author, $request->user(), 'workspace_comment',
                "{$request->user()->name} comentou na sua publicação",
                Str::limit($data['body'], 100), $this->link($hotel, 'mural'),
            );
        }

        return $this->created('Comentário criado com sucesso.', [
            'uuid' => $c->uuid, 'body' => $c->body, 'created_at' => $c->created_at?->toISOString(),
            'author' => $this->access->person($c->author),
        ]);
    }
}
