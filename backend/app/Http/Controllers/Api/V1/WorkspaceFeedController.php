<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\WorkspacePost;
use App\Models\WorkspacePostAck;
use App\Models\WorkspacePostComment;
use App\Models\WorkspacePostLike;
use App\Models\WorkspacePollOption;
use App\Models\WorkspacePollVote;
use App\Models\WorkspacePostMedia;
use App\Support\ImageSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Mural interno: novidades da empresa, com fotos privadas, reações, menções e público-alvo por setor. */
class WorkspaceFeedController extends WorkspaceBaseController
{
    public const REACTIONS = ['like', 'love', 'celebrate', 'clap'];
    public const MAX_PHOTOS = 6;
    public const BADGES = ['teamwork', 'innovation', 'service', 'leadership', 'dedication', 'thanks'];
    private const KUDOS_PER_DAY = 5;
    /** Menção no texto: @[Nome](uuid). O nome exibido vem sempre do servidor. */
    private const MENTION_RE = '/@\[([^\]\n]{1,80})\]\(([0-9a-fA-F-]{36})\)/';

    private function visible(Builder $q, Hotel $hotel, HotelUser $m): Builder
    {
        $q->where('hotel_id', $hotel->id);

        if (! $this->access->isAdmin($m)) {
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
        return $this->access->isAdmin($m) || $this->access->isManager($request->user(), $hotel);
    }

    // ── Menções ───────────────────────────────────────────────

    /** @return string[] uuids (minúsculos) mencionados no texto */
    private function mentionUuids(?string $text): array
    {
        preg_match_all(self::MENTION_RE, (string) $text, $m);

        return array_values(array_unique(array_map('strtolower', $m[2])));
    }

    /** @return array<string, string> uuid => nome, só de membros aprovados da empresa */
    private function mentionNames(Hotel $hotel, array $uuids): array
    {
        if (! $uuids) {
            return [];
        }

        return HotelUser::with('user')->where('hotel_id', $hotel->id)->where('status', 'approved')
            ->whereHas('user', fn ($q) => $q->whereIn('uuid', $uuids))->get()
            ->mapWithKeys(fn (HotelUser $hu) => [$hu->user->uuid => $hu->user->name])->all();
    }

    private function plainText(string $text): string
    {
        return (string) preg_replace(self::MENTION_RE, '@$1', $text);
    }

    private function notifyMentions(Request $request, Hotel $hotel, string $text, ?int $departmentId, string $what): void
    {
        $uuids = array_slice($this->mentionUuids($text), 0, 10);
        foreach ($uuids as $uuid) {
            $member = $this->access->memberByUuid($hotel, $uuid);
            if (! $member || $member->user_id === $request->user()->id) {
                continue;
            }
            // Publicação de um setor só avisa quem pode vê-la.
            if ($departmentId && $member->department_id !== $departmentId && ! $this->access->isAdmin($member)) {
                continue;
            }
            $this->notifications->workspaceNotice(
                $member->user, $request->user(), 'workspace_mention',
                "{$request->user()->name} mencionou você {$what}", Str::limit($this->plainText($text), 100), $this->link($hotel, 'mural'),
            );
        }
    }

    // ── Formatação ────────────────────────────────────────────

    private function mediaPayload(WorkspacePostMedia $m): array
    {
        return [
            'uuid' => $m->uuid,
            'kind' => $m->kind ?? 'image',
            // URL relativa assinada (30 min): a foto não é pública nem listável; o mural gera um link novo a cada carga.
            'url'  => URL::temporarySignedRoute('workspace.media', now()->addMinutes(30), ['uuid' => $m->uuid], absolute: false),
        ];
    }

    private function format(WorkspacePost $p, int $meId, bool $isAdmin, array $reactions = [], ?string $mine = null, array $names = []): array
    {
        $counts = array_fill_keys(self::REACTIONS, 0);
        foreach ($reactions as $type => $n) {
            if (isset($counts[$type])) {
                $counts[$type] = (int) $n;
            }
        }
        $mentioned = array_intersect_key($names, array_flip($this->mentionUuids($p->body)));

        return [
            'uuid'           => $p->uuid,
            'body'           => $p->body,
            'pinned'         => $p->pinned,
            'kind'           => $p->kind ?? 'post',
            'badge'          => $p->badge,
            'poll'           => ($p->kind ?? 'post') === 'poll' ? $this->pollPayload($p) : null,
            'subject'        => $p->subject_id ? $this->access->person($p->subject) : null,
            'comments_preview' => $p->getAttribute('preview') ?? [],
            'created_at'     => $p->created_at?->toISOString(),
            'author'         => $this->access->person($p->author),
            'department'     => $p->department?->name,
            'media'          => $p->media->map(fn ($m) => $this->mediaPayload($m))->values(),
            'mentions'       => $mentioned,
            'reactions'      => $counts,
            'my_reaction'    => $mine,
            'likes_count'    => array_sum($counts),
            'comments_count' => (int) ($p->comments_count ?? 0),
            'liked_by_me'    => $mine !== null,
            'can_delete'     => $isAdmin || ($p->author_id === $meId && ($p->kind ?? 'post') === 'post'),
            'requires_ack'   => (bool) $p->requires_ack,
            'acked_at'       => $p->getAttribute('ack_mine_at'),
            'ack_count'      => $p->requires_ack && ($isAdmin || $p->author_id === $meId) ? (int) $p->getAttribute('ack_count') : null,
            'ack_total'      => $p->requires_ack && ($isAdmin || $p->author_id === $meId) ? $this->audienceQuery($p)->count() : null,
        ];
    }

    /** Pessoas que precisam confirmar a leitura: o público da publicação (empresa toda ou o setor), menos o autor. */
    private function audienceQuery(WorkspacePost $p): \Illuminate\Database\Eloquent\Builder
    {
        return HotelUser::where('hotel_id', $p->hotel_id)->where('status', 'approved')->where('user_id', '!=', $p->author_id)
            ->when($p->department_id, fn ($q) => $q->where('department_id', $p->department_id));
    }

    /** Anexa às publicações a minha confirmação e a contagem total (só serve a quem vai ver o painel). */
    private function attachAcks(\Illuminate\Support\Collection $posts, int $meId): void
    {
        $ids = $posts->where('requires_ack', true)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        $counts = WorkspacePostAck::whereIn('workspace_post_id', $ids)->select('workspace_post_id', DB::raw('count(*) as c'))->groupBy('workspace_post_id')->pluck('c', 'workspace_post_id');
        $mine   = WorkspacePostAck::whereIn('workspace_post_id', $ids)->where('user_id', $meId)->pluck('acked_at', 'workspace_post_id');
        foreach ($posts as $p) {
            $p->setAttribute('ack_count', (int) ($counts[$p->id] ?? 0));
            $p->setAttribute('ack_mine_at', isset($mine[$p->id]) ? \Illuminate\Support\Carbon::parse($mine[$p->id])->toISOString() : null);
        }
    }

    /** Anexa a cada publicação os 2 últimos comentários, para aparecerem direto no mural. */
    private function attachPreviews(\Illuminate\Support\Collection $posts, Hotel $hotel): void
    {
        $ids = $posts->where('comments_count', '>', 0)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        $rows = WorkspacePostComment::with('author')->whereIn('workspace_post_id', $ids)->orderByDesc('created_at')->orderByDesc('id')->get()
            ->groupBy('workspace_post_id')->map(fn ($g) => $g->take(2)->reverse()->values());
        $names = $this->mentionNames($hotel, $rows->flatten(1)->flatMap(fn ($c) => $this->mentionUuids($c->body))->unique()->values()->all());
        foreach ($posts as $p) {
            $p->setAttribute('preview', ($rows[$p->id] ?? collect())->map(fn ($c) => [
                'uuid' => $c->uuid, 'body' => $c->body, 'created_at' => $c->created_at?->toISOString(),
                'author' => $this->access->person($c->author),
                'mentions' => array_intersect_key($names, array_flip($this->mentionUuids($c->body))),
            ])->all());
        }
    }

    /** @return array{0: array<int, array<string,int>>, 1: array<int,string>} reações por publicação e a minha */
    private function reactionData(\Illuminate\Support\Collection $postIds, int $meId): array
    {
        $rows = WorkspacePostLike::whereIn('workspace_post_id', $postIds)
            ->select('workspace_post_id', 'reaction', DB::raw('count(*) as c'))->groupBy('workspace_post_id', 'reaction')->get();
        $byPost = [];
        foreach ($rows as $r) {
            $byPost[$r->workspace_post_id][$r->reaction] = (int) $r->c;
        }
        $mine = WorkspacePostLike::whereIn('workspace_post_id', $postIds)->where('user_id', $meId)->pluck('reaction', 'workspace_post_id')->all();

        return [$byPost, $mine];
    }

    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        $meId = $request->user()->id;
        $q = $this->visible(WorkspacePost::query(), $hotel, $m)
            ->with(['author', 'department', 'media', 'subject'])
            ->withCount(['comments'])
            ->orderByDesc('pinned')->orderByDesc('created_at')->orderByDesc('id');

        // Grupos por área: ?group=company (só avisos da empresa toda) ou ?group=<uuid do setor>.
        $group = (string) $request->query('group', '');
        if ($group === 'company') {
            $q->whereNull('department_id');
        } elseif ($group !== '') {
            $q->where('department_id', $hotel->departments()->where('uuid', $group)->value('id') ?? 0);
        }

        $page = $q->paginate(15);
        $isAdmin = $this->access->isAdmin($m);
        $posts = $page->getCollection();

        [$byPost, $mine] = $this->reactionData($posts->pluck('id'), $meId);
        $this->attachAcks($posts, $meId);
        $this->attachPreviews($posts, $hotel);
        $names = $this->mentionNames($hotel, $posts->flatMap(fn ($p) => $this->mentionUuids($p->body))->unique()->values()->all());

        return $this->success('Mural carregado com sucesso.', [
            'items'     => $posts->map(fn ($p) => $this->format($p, $meId, $isAdmin, $byPost[$p->id] ?? [], $mine[$p->id] ?? null, $names))->values(),
            'page'      => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'can_post'  => $this->canPost($hotel, $m, $request),
            // Grupos que a pessoa enxerga: RH/dono veem todos os setores; os demais, só o próprio.
            'groups'    => $hotel->departments()
                ->when(! $isAdmin, fn ($d) => $d->where('id', $m->department_id ?? 0))
                ->orderBy('name')->get(['uuid', 'name'])->map(fn ($d) => ['uuid' => $d->uuid, 'name' => $d->name])->values(),
        ]);
    }

    /** Enquete: opções com totais e o meu voto (quem votou em quê nunca é exposto). */
    private function pollPayload(WorkspacePost $p): array
    {
        $counts = WorkspacePollVote::where('workspace_post_id', $p->id)->select('workspace_poll_option_id', DB::raw('count(*) as c'))->groupBy('workspace_poll_option_id')->pluck('c', 'workspace_poll_option_id');
        $mine   = WorkspacePollVote::where('workspace_post_id', $p->id)->where('user_id', request()->user()->id)->value('workspace_poll_option_id');
        $options = $p->pollOptions()->get();

        return [
            'options'   => $options->map(fn ($o) => ['uuid' => $o->uuid, 'label' => $o->label, 'votes' => (int) ($counts[$o->id] ?? 0)])->values(),
            'total'     => (int) $counts->sum(),
            'my_vote'   => $options->firstWhere('id', $mine)?->uuid,
            'closes_on' => $p->poll_closes_on?->toDateString(),
            'closed'    => $p->poll_closes_on !== null && $p->poll_closes_on->endOfDay()->isPast(),
        ];
    }

    /** POST /workspaces/{slug}/polls  { question, options[], closes_on?, department_uuid? } — RH e gestores. */
    public function createPoll(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m || ! $this->canPost($hotel, $m, $request)) {
            return $this->forbidden('Somente RH/gestores criam enquetes.');
        }
        $data = $request->validate([
            'question'        => ['required', 'string', 'min:5', 'max:300'],
            'options'         => ['required', 'array', 'min:2', 'max:6'],
            'options.*'       => ['required', 'string', 'max:120', 'distinct'],
            'closes_on'       => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'department_uuid' => ['nullable', 'uuid'],
        ]);
        $deptId = null;
        if (! empty($data['department_uuid'])) {
            $deptId = $hotel->departments()->where('uuid', $data['department_uuid'])->value('id');
            if (! $deptId) {
                return $this->error('Setor inválido.', ['department_uuid' => ['Setor não encontrado.']], 422);
            }
        }
        $post = DB::transaction(function () use ($request, $hotel, $data, $deptId) {
            $post = WorkspacePost::create([
                'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'author_id' => $request->user()->id, 'department_id' => $deptId,
                'kind' => 'poll', 'body' => trim($data['question']), 'pinned' => false, 'poll_closes_on' => $data['closes_on'] ?? null,
            ]);
            foreach (array_values($data['options']) as $i => $label) {
                WorkspacePollOption::create(['uuid' => (string) Str::uuid(), 'workspace_post_id' => $post->id, 'label' => trim($label), 'position' => $i]);
            }

            return $post;
        });
        $post->load(['author', 'department', 'media', 'subject']);

        return $this->created('Enquete publicada.', $this->format($post, $request->user()->id, $this->access->isAdmin($m)));
    }

    /** POST /workspaces/{slug}/feed/{post}/vote  { option_uuid } — um voto por pessoa; dá para trocar até encerrar. */
    public function vote(Request $request, Hotel $hotel, string $post): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $p = $m ? $this->visible(WorkspacePost::query(), $hotel, $m)->where('uuid', $post)->where('kind', 'poll')->first() : null;
        if (! $p) {
            return $this->notFound('Enquete não encontrada.');
        }
        if ($p->poll_closes_on && $p->poll_closes_on->endOfDay()->isPast()) {
            return $this->error('Esta enquete já foi encerrada.', [], 409);
        }
        $data = $request->validate(['option_uuid' => ['required', 'uuid']]);
        $option = $p->pollOptions()->where('uuid', $data['option_uuid'])->first();
        if (! $option) {
            return $this->error('Opção inválida.', ['option_uuid' => ['Opção não encontrada.']], 422);
        }
        WorkspacePollVote::updateOrCreate(['workspace_post_id' => $p->id, 'user_id' => $request->user()->id], ['workspace_poll_option_id' => $option->id]);

        return $this->success('Voto registrado.', $this->pollPayload($p));
    }

    /** POST /workspaces/{slug}/kudos  { to_uuid, badge, message } — qualquer pessoa reconhece um colega; vira publicação no mural. */
    public function kudos(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $data = $request->validate([
            'to_uuid' => ['required', 'uuid'],
            'badge'   => ['required', 'in:' . implode(',', self::BADGES)],
            'message' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $to = $this->access->memberByUuid($hotel, $data['to_uuid']);
        if (! $to || $to->user_id === $request->user()->id) {
            return $this->error('Escolha um colega da empresa (não vale você mesmo).', ['to_uuid' => ['Colega inválido.']], 422);
        }
        $today = WorkspacePost::where('hotel_id', $hotel->id)->where('author_id', $request->user()->id)->where('kind', 'kudos')->where('created_at', '>=', now()->startOfDay())->count();
        if ($today >= self::KUDOS_PER_DAY) {
            return $this->error('Você já fez ' . self::KUDOS_PER_DAY . ' reconhecimentos hoje. Volte amanhã!', [], 429);
        }

        $post = WorkspacePost::create([
            'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'author_id' => $request->user()->id,
            'kind' => 'kudos', 'subject_id' => $to->user_id, 'badge' => $data['badge'], 'body' => trim($data['message']), 'pinned' => false,
        ]);
        $this->notifications->workspaceNotice($to->user, $request->user(), 'workspace_kudos', "{$request->user()->name} reconheceu você", Str::limit(trim($data['message']), 100), $this->link($hotel, 'mural'));
        AuditLog::record($request, $hotel, 'kudos.given', $to->user, ['badge' => $data['badge']]);
        $post->load(['author', 'department', 'media', 'subject']);

        return $this->created('Reconhecimento publicado.', $this->format($post, $request->user()->id, $this->access->isAdmin($m)));
    }

    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m || ! $this->canPost($hotel, $m, $request)) {
            return $this->forbidden('Somente RH/gestores podem publicar no mural.');
        }

        $data = $request->validate([
            'body'            => ['nullable', 'string', 'max:3000'],
            'pinned'          => ['sometimes', 'boolean'],
            'department_uuid' => ['nullable', 'uuid'],
            'requires_ack'    => ['sometimes', 'boolean'],
            'photos'          => ['nullable', 'array', 'max:' . self::MAX_PHOTOS],
            'photos.*'        => ['file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'video'           => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:204800'],
        ]);
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '' && ! $request->hasFile('photos') && ! $request->hasFile('video')) {
            return $this->error('Escreva algo ou anexe uma foto ou vídeo.', ['body' => ['Escreva algo ou anexe uma foto ou vídeo.']], 422);
        }

        $deptId = null;
        if (! empty($data['department_uuid'])) {
            $dept = $hotel->departments()->where('uuid', $data['department_uuid'])->first();
            if (! $dept) {
                return $this->error('Setor inválido.', ['department_uuid' => ['Setor não encontrado.']], 422);
            }
            $deptId = $dept->id;
        }

        $post = DB::transaction(function () use ($request, $hotel, $m, $body, $data, $deptId) {
            $post = WorkspacePost::create([
                'uuid'          => (string) Str::uuid(),
                'hotel_id'      => $hotel->id,
                'author_id'     => $request->user()->id,
                'department_id' => $deptId,
                'body'          => $body,
                'pinned'        => $this->access->isAdmin($m) && ($data['pinned'] ?? false),
                'requires_ack'  => (bool) ($data['requires_ack'] ?? false),
            ]);
            foreach (array_values($request->file('photos', [])) as $i => $file) {
                WorkspacePostMedia::create([
                    'uuid' => (string) Str::uuid(), 'workspace_post_id' => $post->id,
                    // EXIF/GPS removidos; disco privado.
                    'path' => ImageSanitizer::store($file, "workspace-posts/{$hotel->id}", 'local'),
                    'mime' => $file->getMimeType() ?: 'image/jpeg', 'size' => $file->getSize(), 'position' => $i,
                ]);
            }

            if ($request->hasFile('video')) {
                $video = $request->file('video');
                WorkspacePostMedia::create([
                    'uuid' => (string) Str::uuid(), 'workspace_post_id' => $post->id, 'kind' => 'video',
                    'path' => $video->store("workspace-posts/{$hotel->id}", 'local'),
                    'mime' => $video->getMimeType() ?: 'video/mp4', 'size' => $video->getSize(), 'position' => 99,
                ]);
            }

            return $post;
        });
        $post->load(['author', 'department', 'media', 'subject']);

        // Comunicado com confirmação de leitura: avisa todo o público (fila de notificações; poucos registros por comunicado).
        if ($post->requires_ack) {
            foreach ($this->audienceQuery($post)->with('user')->get() as $hu) {
                if (! $hu->user) {
                    continue;
                }
                $this->notifications->workspaceNotice(
                    $hu->user, $request->user(), 'workspace_ack_required', 'Comunicado: confirme a leitura',
                    Str::limit($this->plainText($body) ?: 'Comunicado com fotos', 100), $this->link($hotel, 'mural'),
                );
            }
        }

        $this->notifyMentions($request, $hotel, $body, $deptId, 'em uma publicação');
        $names = $this->mentionNames($hotel, $this->mentionUuids($body));

        return $this->created('Publicação criada com sucesso.', $this->format($post, $request->user()->id, $this->access->isAdmin($m), [], null, $names));
    }

    public function destroy(Request $request, Hotel $hotel, string $post): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $p = $m ? $this->visible(WorkspacePost::query(), $hotel, $m)->where('uuid', $post)->first() : null;
        if (! $p) {
            return $this->notFound('Publicação não encontrada.');
        }
        if (! $this->access->isAdmin($m) && $p->author_id !== $request->user()->id) {
            return $this->forbidden('Você não pode remover esta publicação.');
        }

        // As fotos saem do disco junto com a publicação.
        foreach ($p->media as $media) {
            Storage::disk('local')->delete($media->path);
        }
        $p->media()->delete();
        $p->delete();

        return $this->success('Publicação removida com sucesso.');
    }

    public function pin(Request $request, Hotel $hotel, string $post): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m || ! $this->access->isAdmin($m)) {
            return $this->forbidden('Somente o RH pode fixar publicações.');
        }
        $p = WorkspacePost::where('hotel_id', $hotel->id)->where('uuid', $post)->first();
        if (! $p) {
            return $this->notFound('Publicação não encontrada.');
        }
        $p->update(['pinned' => ! $p->pinned]);

        return $this->success('Publicação atualizada com sucesso.', ['pinned' => $p->pinned]);
    }

    /** POST /feed/{post}/ack — "li e estou ciente" (idempotente; guarda data e IP). */
    public function ack(Request $request, Hotel $hotel, string $post): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $p = $m ? $this->visible(WorkspacePost::query(), $hotel, $m)->where('uuid', $post)->where('requires_ack', true)->first() : null;
        if (! $p) {
            return $this->notFound('Comunicado não encontrado.');
        }

        $ack = WorkspacePostAck::firstOrCreate(
            ['workspace_post_id' => $p->id, 'user_id' => $request->user()->id],
            ['acked_at' => now(), 'ip' => $request->ip()],
        );

        return $this->success('Leitura confirmada.', ['acked_at' => $ack->acked_at?->toISOString()]);
    }

    /** GET /feed/{post}/acks — quem já confirmou e quem falta (autor ou RH). */
    public function acks(Request $request, Hotel $hotel, string $post): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $p = $m ? WorkspacePost::where('hotel_id', $hotel->id)->where('uuid', $post)->where('requires_ack', true)->first() : null;
        if (! $p || ($p->author_id !== $request->user()->id && ! $this->access->isAdmin($m))) {
            return $this->notFound('Comunicado não encontrado.');
        }

        $acked   = WorkspacePostAck::with('user')->where('workspace_post_id', $p->id)->orderBy('acked_at')->get()->keyBy('user_id');
        $members = $this->audienceQuery($p)->with(['user', 'department'])->get();

        return $this->success('Confirmações carregadas.', [
            'total'   => $members->count(),
            'readers' => $members->filter(fn ($hu) => $acked->has($hu->user_id))->map(fn ($hu) => [
                'person' => $this->access->person($hu->user, $hu), 'acked_at' => $acked[$hu->user_id]->acked_at?->toISOString(),
            ])->sortBy('acked_at')->values(),
            'pending' => $members->reject(fn ($hu) => $acked->has($hu->user_id))->map(fn ($hu) => $this->access->person($hu->user, $hu) + ['department' => $hu->department?->name])->values(),
        ]);
    }

    /** GET /workspace-media/{uuid}?expires=&signature= — entrega a foto (rota pública, mas só com URL assinada e válida). */
    public function media(string $uuid): StreamedResponse|JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $media = WorkspacePostMedia::with('post')->where('uuid', $uuid)->first();
        if (! $media || ! $media->post || ! Storage::disk('local')->exists($media->path)) {
            return $this->notFound('Foto não encontrada.');
        }

        if (($media->kind ?? 'image') === 'video') {
            // BinaryFileResponse entende Range: o vídeo toca e permite avançar sem baixar tudo.
            return response()->file(Storage::disk('local')->path($media->path), [
                'Content-Type' => $media->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=1500',
            ]);
        }

        return Storage::disk('local')->response($media->path, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=1500',
        ]);
    }

    private function reactionPayload(WorkspacePost $p, int $meId): array
    {
        [$byPost, $mine] = $this->reactionData(collect([$p->id]), $meId);
        $counts = array_merge(array_fill_keys(self::REACTIONS, 0), $byPost[$p->id] ?? []);

        return ['reactions' => $counts, 'my_reaction' => $mine[$p->id] ?? null, 'likes_count' => array_sum($counts)];
    }

    /** POST /feed/{post}/react  { reaction: like|love|celebrate|clap|null } — null remove a reação. */
    public function react(Request $request, Hotel $hotel, string $post): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $p = $m ? $this->visible(WorkspacePost::query(), $hotel, $m)->where('uuid', $post)->first() : null;
        if (! $p) {
            return $this->notFound('Publicação não encontrada.');
        }
        $data = $request->validate(['reaction' => ['nullable', 'string', 'in:' . implode(',', self::REACTIONS)]]);
        $meId = $request->user()->id;

        $existing = WorkspacePostLike::where('workspace_post_id', $p->id)->where('user_id', $meId)->first();
        if (empty($data['reaction'])) {
            $existing?->delete();
        } elseif ($existing) {
            $existing->update(['reaction' => $data['reaction']]);
        } else {
            WorkspacePostLike::create(['workspace_post_id' => $p->id, 'user_id' => $meId, 'reaction' => $data['reaction']]);
            if ($p->author && $p->author_id !== $meId) {
                $this->notifications->workspaceNotice(
                    $p->author, $request->user(), 'workspace_reaction',
                    "{$request->user()->name} reagiu à sua publicação", Str::limit($this->plainText((string) $p->body), 100), $this->link($hotel, 'mural'),
                );
            }
        }

        return $this->success('Reação atualizada.', $this->reactionPayload($p, $meId));
    }

    /** Compatibilidade: alterna a reação "like". */
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
            WorkspacePostLike::create(['workspace_post_id' => $p->id, 'user_id' => $request->user()->id, 'reaction' => 'like']);
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

        $comments = $p->comments()->with('author')->orderBy('created_at')->limit(200)->get();
        $names = $this->mentionNames($hotel, $comments->flatMap(fn ($c) => $this->mentionUuids($c->body))->unique()->values()->all());
        $items = $comments->map(fn ($c) => [
            'uuid' => $c->uuid, 'body' => $c->body, 'created_at' => $c->created_at?->toISOString(),
            'author' => $this->access->person($c->author),
            'mentions' => array_intersect_key($names, array_flip($this->mentionUuids($c->body))),
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
                Str::limit($this->plainText($data['body']), 100), $this->link($hotel, 'mural'),
            );
        }
        $this->notifyMentions($request, $hotel, $data['body'], $p->department_id, 'em um comentário');
        $names = $this->mentionNames($hotel, $this->mentionUuids($data['body']));

        return $this->created('Comentário criado com sucesso.', [
            'uuid' => $c->uuid, 'body' => $c->body, 'created_at' => $c->created_at?->toISOString(),
            'author' => $this->access->person($c->author), 'mentions' => $names,
        ]);
    }
}
