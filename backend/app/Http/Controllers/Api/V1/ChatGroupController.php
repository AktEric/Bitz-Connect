<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Chat\Services\ChatGroupService;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\ChatGroupMessage;
use App\Models\Hotel;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Grupos de chat corporativo (barra lateral de chat da empresa).
 *
 * "Modo aviso": quando only_admins_can_post = true, apenas administradores
 * (do grupo ou da empresa) enviam mensagens; os demais só leem.
 */
class ChatGroupController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ChatGroupService $service,
        private readonly NotificationService $notifications,
    ) {}

    // ──────────────────────────────────────────────────────────
    // Listagem / detalhe
    // ──────────────────────────────────────────────────────────

    /** GET /chat-groups — grupos dos quais o usuário participa. */
    public function index(Request $request): JsonResponse
    {
        $user        = $request->user();
        $adminHotels = $this->service->adminHotelIds($user);

        $memberships = ChatGroupMember::with('group.hotel')
            ->where('user_id', $user->id)
            ->whereHas('group')
            ->get();

        $data = $memberships
            ->map(fn (ChatGroupMember $m) => $this->formatGroup($m->group, $m, $user, $adminHotels))
            ->sortByDesc(fn (array $g) => $g['last_message']['created_at'] ?? $g['created_at'])
            ->values();

        return $this->success('Grupos carregados com sucesso.', $data);
    }

    /** GET /chat-groups/unread-count */
    public function unreadCount(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $count = ChatGroupMessage::query()
            ->join('chat_group_members as m', 'm.chat_group_id', '=', 'chat_group_messages.chat_group_id')
            ->join('chat_groups as g', 'g.id', '=', 'chat_group_messages.chat_group_id')
            ->whereNull('g.deleted_at')
            ->where('m.user_id', $userId)
            ->where(fn ($q) => $q->whereNull('chat_group_messages.sender_id')
                ->orWhere('chat_group_messages.sender_id', '!=', $userId))
            ->whereRaw('chat_group_messages.created_at > COALESCE(m.last_read_at, m.created_at)')
            ->count();

        return $this->success('OK', ['count' => $count]);
    }

    /** GET /chat-groups/{group} — detalhes e lista de membros. */
    public function show(Request $request, ChatGroup $group): JsonResponse
    {
        $user       = $request->user();
        $membership = $group->membershipOf($user);

        if (! $membership && ! $this->service->isCompanyAdmin($user, $group->hotel_id)) {
            return $this->forbidden('Você não participa deste grupo.');
        }

        $group->load('hotel');

        $members = $group->members()->with('user')->get()->map(function (ChatGroupMember $m) use ($group) {
            $hotelUser = $m->user
                ? $m->user->hotelUsers()->where('hotel_id', $group->hotel_id)->with('department')->first()
                : null;

            return [
                'uuid'             => $m->user?->uuid,
                'name'             => $m->user?->name,
                'avatar_url'       => $this->avatarUrl($m->user),
                'role'             => $m->role,
                'department'       => $hotelUser?->department?->name,
                'is_company_admin' => $hotelUser?->role === 'admin',
            ];
        })->filter(fn ($m) => $m['uuid'])->values();

        $base = $membership ?? new ChatGroupMember(['role' => 'member', 'chat_group_id' => $group->id]);

        return $this->success('Grupo carregado com sucesso.', array_merge(
            $this->formatGroup($group, $base, $user, $this->service->adminHotelIds($user)),
            ['members' => $members],
        ));
    }

    // ──────────────────────────────────────────────────────────
    // Criação / edição
    // ──────────────────────────────────────────────────────────

    /** POST /hotels/{hotel:slug}/chat-groups */
    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        $user = $request->user();

        if (! $this->service->isCompanyAdmin($user, $hotel->id)) {
            return $this->forbidden('Somente administradores da empresa podem criar grupos.');
        }

        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:120'],
            'description'          => ['nullable', 'string', 'max:500'],
            'only_admins_can_post' => ['sometimes', 'boolean'],
            'member_uuids'         => ['sometimes', 'array'],
            'member_uuids.*'       => ['string'],
            'department_uuids'     => ['sometimes', 'array'],
            'department_uuids.*'   => ['string'],
            'all_employees'        => ['sometimes', 'boolean'],
        ]);

        $memberIds = $this->service->resolveCompanyUserIds(
            $hotel,
            $data['member_uuids'] ?? [],
            $data['department_uuids'] ?? [],
            (bool) ($data['all_employees'] ?? false),
        );

        $group = $this->service->createGroup($hotel, $user, $data, $memberIds)->load('hotel');

        return $this->created('Grupo criado com sucesso.', $this->formatGroup(
            $group,
            $group->membershipOf($user),
            $user,
            $this->service->adminHotelIds($user),
        ));
    }

    /** PATCH /chat-groups/{group} */
    public function update(Request $request, ChatGroup $group): JsonResponse
    {
        $user = $request->user();

        if (! $this->service->canManage($user, $group, $group->membershipOf($user))) {
            return $this->forbidden('Você não pode gerenciar este grupo.');
        }

        $data = $request->validate([
            'name'                 => ['sometimes', 'required', 'string', 'max:120'],
            'description'          => ['sometimes', 'nullable', 'string', 'max:500'],
            'only_admins_can_post' => ['sometimes', 'boolean'],
        ]);

        $group->update($data);
        $group->load('hotel');

        return $this->success('Grupo atualizado com sucesso.', $this->formatGroup(
            $group,
            $group->membershipOf($user) ?? new ChatGroupMember(['role' => 'member', 'chat_group_id' => $group->id]),
            $user,
            $this->service->adminHotelIds($user),
        ));
    }

    /** DELETE /chat-groups/{group} — apenas administradores da empresa. */
    public function destroy(Request $request, ChatGroup $group): JsonResponse
    {
        if (! $this->service->isCompanyAdmin($request->user(), $group->hotel_id)) {
            return $this->forbidden('Somente administradores da empresa podem excluir grupos.');
        }

        $group->delete();

        return $this->success('Grupo excluído com sucesso.');
    }

    // ──────────────────────────────────────────────────────────
    // Membros
    // ──────────────────────────────────────────────────────────

    /** POST /chat-groups/{group}/members */
    public function addMembers(Request $request, ChatGroup $group): JsonResponse
    {
        $user = $request->user();

        if (! $this->service->canManage($user, $group, $group->membershipOf($user))) {
            return $this->forbidden('Você não pode gerenciar este grupo.');
        }

        $data = $request->validate([
            'user_uuids'         => ['sometimes', 'array'],
            'user_uuids.*'       => ['string'],
            'department_uuids'   => ['sometimes', 'array'],
            'department_uuids.*' => ['string'],
            'all_employees'      => ['sometimes', 'boolean'],
        ]);

        $ids = $this->service->resolveCompanyUserIds(
            $group->hotel,
            $data['user_uuids'] ?? [],
            $data['department_uuids'] ?? [],
            (bool) ($data['all_employees'] ?? false),
        );

        return $this->success('Membros adicionados com sucesso.', [
            'added' => $this->service->addMembers($group, $ids),
        ]);
    }

    /** DELETE /chat-groups/{group}/members/{user} */
    public function removeMember(Request $request, ChatGroup $group, User $user): JsonResponse
    {
        $auth = $request->user();

        if (! $this->service->canManage($auth, $group, $group->membershipOf($auth))) {
            return $this->forbidden('Você não pode gerenciar este grupo.');
        }

        if ($auth->id === $user->id) {
            return $this->error('Use "sair do grupo" para remover a si mesmo.', [], 422);
        }

        $group->members()->where('user_id', $user->id)->delete();

        return $this->success('Membro removido com sucesso.');
    }

    /** PATCH /chat-groups/{group}/members/{user}/role */
    public function setMemberRole(Request $request, ChatGroup $group, User $user): JsonResponse
    {
        $auth = $request->user();

        if (! $this->service->canManage($auth, $group, $group->membershipOf($auth))) {
            return $this->forbidden('Você não pode gerenciar este grupo.');
        }

        $data   = $request->validate(['role' => ['required', 'in:admin,member']]);
        $target = $group->membershipOf($user);

        if (! $target) {
            return $this->notFound('Essa pessoa não está no grupo.');
        }

        $target->update(['role' => $data['role']]);

        return $this->success('Permissão atualizada com sucesso.');
    }

    /** DELETE /chat-groups/{group}/membership — sair do grupo. */
    public function leave(Request $request, ChatGroup $group): JsonResponse
    {
        $user       = $request->user();
        $membership = $group->membershipOf($user);

        if (! $membership) {
            return $this->notFound('Você não participa deste grupo.');
        }

        $otherAdmins = $group->members()->where('role', 'admin')->where('user_id', '!=', $user->id)->exists();
        $hasOthers   = $group->members()->where('user_id', '!=', $user->id)->exists();

        if ($membership->isAdmin() && ! $otherAdmins && $hasOthers) {
            return $this->error('Promova outra pessoa a administrador antes de sair do grupo.', [], 422);
        }

        $membership->delete();

        return $this->success('Você saiu do grupo.');
    }

    /** PATCH /chat-groups/{group}/mute — silenciar notificações (por membro). */
    public function mute(Request $request, ChatGroup $group): JsonResponse
    {
        $membership = $group->membershipOf($request->user());

        if (! $membership) {
            return $this->forbidden('Você não participa deste grupo.');
        }

        $data = $request->validate(['muted' => ['required', 'boolean']]);
        $membership->update(['notifications_muted' => $data['muted']]);

        return $this->success('Preferência atualizada.', ['notifications_muted' => $membership->notifications_muted]);
    }

    // ──────────────────────────────────────────────────────────
    // Mensagens
    // ──────────────────────────────────────────────────────────

    /** GET /chat-groups/{group}/messages */
    public function messages(Request $request, ChatGroup $group): JsonResponse
    {
        $user = $request->user();

        if (! $group->membershipOf($user)) {
            return $this->forbidden('Você não participa deste grupo.');
        }

        $paginator = $group->messages()->with('sender')->orderByDesc('id')->paginate(30);

        $data = $paginator->getCollection()
            ->map(fn (ChatGroupMessage $msg) => $this->formatMessage($msg, $user))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Mensagens carregadas com sucesso.',
            'data'    => $data,
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /** POST /chat-groups/{group}/messages */
    public function send(Request $request, ChatGroup $group): JsonResponse
    {
        $user       = $request->user();
        $membership = $group->membershipOf($user);

        if (! $membership) {
            return $this->forbidden('Você não participa deste grupo.');
        }

        if (! $this->service->canPost($user, $group, $membership)) {
            return $this->forbidden('Somente administradores podem enviar mensagens neste grupo.');
        }

        $data = $request->validate([
            'content'         => ['required', 'string', 'max:5000'],
            'is_announcement' => ['sometimes', 'boolean'],
        ]);

        // Apenas administradores podem marcar uma mensagem como aviso.
        $announcement = (bool) ($data['is_announcement'] ?? false)
            && $this->service->canManage($user, $group, $membership);

        $message = $group->messages()->create([
            'uuid'            => (string) Str::uuid(),
            'sender_id'       => $user->id,
            'content'         => $data['content'],
            'type'            => 'text',
            'is_announcement' => $announcement,
        ]);

        $membership->update(['last_read_at' => now()]);

        $group->members()
            ->with('user')
            ->where('notifications_muted', false)
            ->where('user_id', '!=', $user->id)
            ->get()
            ->each(function (ChatGroupMember $m) use ($user, $group, $data, $announcement) {
                if ($m->user) {
                    $this->notifications->groupMessage($m->user, $user, $group, $data['content'], $announcement);
                }
            });

        $message->load('sender');

        return $this->created('Mensagem enviada com sucesso.', $this->formatMessage($message, $user));
    }

    /** PATCH /chat-groups/{group}/read */
    public function markRead(Request $request, ChatGroup $group): JsonResponse
    {
        $membership = $group->membershipOf($request->user());

        if (! $membership) {
            return $this->forbidden('Você não participa deste grupo.');
        }

        $membership->update(['last_read_at' => now()]);

        return $this->success('Mensagens marcadas como lidas.');
    }

    // ──────────────────────────────────────────────────────────
    // Formatação
    // ──────────────────────────────────────────────────────────

    private function avatarUrl(?User $user): ?string
    {
        return $user?->avatar_path ? asset('storage/' . $user->avatar_path) : null;
    }

    private function formatGroup(ChatGroup $group, ChatGroupMember $membership, User $user, array $adminHotelIds): array
    {
        $isCompanyAdmin = in_array($group->hotel_id, $adminHotelIds, true);
        $isGroupAdmin   = $membership->role === 'admin';
        $isMember       = $membership->exists;

        $last = $group->messages()->with('sender')->latest('id')->first();

        $unread = 0;
        if ($isMember) {
            $unread = $group->messages()
                ->where(fn ($q) => $q->whereNull('sender_id')->orWhere('sender_id', '!=', $user->id))
                ->where('created_at', '>', $membership->last_read_at ?? $membership->created_at)
                ->count();
        }

        $hotel = $group->hotel;

        return [
            'uuid'                 => $group->uuid,
            'name'                 => $group->name,
            'description'          => $group->description,
            'only_admins_can_post' => $group->only_admins_can_post,
            'members_count'        => $group->members()->count(),
            'created_at'           => $group->created_at->toISOString(),
            'hotel'                => $hotel ? [
                'uuid'       => $hotel->uuid,
                'slug'       => $hotel->slug,
                'name'       => $hotel->name,
                'avatar_url' => $hotel->logo_path ? asset('storage/' . $hotel->logo_path) : null,
            ] : null,
            'is_member'            => $isMember,
            'my_role'              => $isMember ? $membership->role : null,
            'can_manage'           => $isCompanyAdmin || $isGroupAdmin,
            'can_post'             => $isMember && (! $group->only_admins_can_post || $isCompanyAdmin || $isGroupAdmin),
            'notifications_muted'  => (bool) $membership->notifications_muted,
            'unread_count'         => $unread,
            'last_message'         => $last ? [
                'content'     => $last->content,
                'sender_name' => $last->sender?->name,
                'created_at'  => $last->created_at->toISOString(),
            ] : null,
        ];
    }

    private function formatMessage(ChatGroupMessage $message, User $viewer): array
    {
        return [
            'uuid'            => $message->uuid,
            'content'         => $message->content,
            'type'            => $message->type,
            'is_announcement' => $message->is_announcement,
            'is_mine'         => $message->sender_id === $viewer->id,
            'created_at'      => $message->created_at->toISOString(),
            'sender'          => [
                'uuid'       => $message->sender?->uuid,
                'name'       => $message->sender?->name ?? 'Ex-membro',
                'avatar_url' => $this->avatarUrl($message->sender),
            ],
        ];
    }
}
