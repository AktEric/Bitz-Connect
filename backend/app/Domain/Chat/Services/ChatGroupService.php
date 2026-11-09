<?php

namespace App\Domain\Chat\Services;

use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Regras de negócio dos grupos de chat corporativo.
 *
 * Regra central: só pessoas com vínculo APROVADO com a empresa entram em
 * grupos dessa empresa. Administradores da empresa gerenciam qualquer grupo
 * dela; administradores do grupo gerenciam apenas o próprio grupo.
 */
class ChatGroupService
{
    public function isCompanyMember(User $user, int $hotelId): bool
    {
        return HotelUser::where('hotel_id', $hotelId)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->exists();
    }

    /**
     * Administra a empresa (dono/RH). Segue o MESMO critério do Workspace: o papel de acesso, quando definido, prevalece
     * sobre o `role` legado; sem papel de acesso, `role = admin` vale como dono.
     */
    private function adminScope($query)
    {
        return $query->where('status', 'approved')->where(fn ($q) => $q->whereIn('access_role', ['owner', 'hr'])
            ->orWhere(fn ($q2) => $q2->whereNull('access_role')->where('role', 'admin')));
    }

    public function isCompanyAdmin(User $user, int $hotelId): bool
    {
        return $this->adminScope(HotelUser::where('hotel_id', $hotelId)->where('user_id', $user->id))->exists();
    }

    /** @return int[] ids das empresas em que o usuário é administrador aprovado */
    public function adminHotelIds(User $user): array
    {
        return $this->adminScope(HotelUser::where('user_id', $user->id))->pluck('hotel_id')->all();
    }

    public function canManage(User $user, ChatGroup $group, ?ChatGroupMember $membership): bool
    {
        return ($membership?->isAdmin() ?? false) || $this->isCompanyAdmin($user, $group->hotel_id);
    }

    /** "Modo aviso": com only_admins_can_post, só administradores enviam. */
    public function canPost(User $user, ChatGroup $group, ?ChatGroupMember $membership): bool
    {
        if (! $membership) {
            return false;
        }

        if (! $group->only_admins_can_post) {
            return true;
        }

        return $this->canManage($user, $group, $membership);
    }

    /**
     * Resolve ids de usuários a partir de seletores (pessoas, setores ou todos),
     * sempre restritos aos membros aprovados da empresa.
     *
     * @param  string[]  $userUuids
     * @param  string[]  $departmentUuids
     * @return int[]
     */
    public function resolveCompanyUserIds(Hotel $hotel, array $userUuids, array $departmentUuids, bool $all): array
    {
        $base = fn () => HotelUser::where('hotel_id', $hotel->id)->where('status', 'approved');

        if ($all) {
            return $base()->pluck('user_id')->all();
        }

        $ids = [];

        if ($userUuids) {
            $ids = array_merge($ids, $base()
                ->whereHas('user', fn ($q) => $q->whereIn('uuid', $userUuids))
                ->pluck('user_id')->all());
        }

        if ($departmentUuids) {
            $ids = array_merge($ids, $base()
                ->whereHas('department', fn ($q) => $q->whereIn('uuid', $departmentUuids)->where('hotel_id', $hotel->id))
                ->pluck('user_id')->all());
        }

        return array_values(array_unique($ids));
    }

    /** Adiciona membros que ainda não estão no grupo. Retorna quantos entraram. */
    public function addMembers(ChatGroup $group, array $userIds): int
    {
        if (! $userIds) {
            return 0;
        }

        $existing = $group->members()->whereIn('user_id', $userIds)->pluck('user_id')->all();
        $new      = array_values(array_diff($userIds, $existing));

        foreach ($new as $userId) {
            $group->members()->create(['user_id' => $userId, 'role' => 'member']);
        }

        return count($new);
    }

    public function createGroup(Hotel $hotel, User $creator, array $data, array $memberIds): ChatGroup
    {
        return DB::transaction(function () use ($hotel, $creator, $data, $memberIds) {
            $group = ChatGroup::create([
                'uuid'                 => (string) Str::uuid(),
                'hotel_id'             => $hotel->id,
                'created_by'           => $creator->id,
                'name'                 => $data['name'],
                'description'          => $data['description'] ?? null,
                'only_admins_can_post' => (bool) ($data['only_admins_can_post'] ?? false),
            ]);

            // Quem cria é sempre administrador do grupo.
            $group->members()->create([
                'user_id'      => $creator->id,
                'role'         => 'admin',
                'last_read_at' => now(),
            ]);

            $this->addMembers($group, array_values(array_diff($memberIds, [$creator->id])));

            return $group;
        });
    }
}
