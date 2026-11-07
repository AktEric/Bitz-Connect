<?php

namespace App\Domain\Workspace\Services;

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;

/**
 * Regras de acesso do Workspace (intranet da empresa).
 *
 * - Membro: vínculo APROVADO com a empresa.
 * - Administrador: membro com role = admin (RH/gestão — enxerga tudo da empresa).
 * - Gestor: membro que é `manager_id` de alguém (enxerga a equipe direta).
 */
class WorkspaceAccess
{
    public function membership(User $user, Hotel $hotel): ?HotelUser
    {
        return HotelUser::where('hotel_id', $hotel->id)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->first();
    }

    public function isAdmin(?HotelUser $membership): bool
    {
        return $membership?->role === 'admin';
    }

    /** Papel de acesso efetivo: owner | hr | finance | manager | employee. */
    public function accessRole(?HotelUser $membership, ?Hotel $hotel = null): string
    {
        if (! $membership) {
            return 'employee';
        }
        if ($membership->access_role) {
            return $membership->access_role;
        }
        if ($membership->role === 'admin') {
            return 'owner';
        }

        return ($hotel && $this->isManager($membership->user, $hotel)) ? 'manager' : 'employee';
    }

    /** @return int[] ids dos usuários cujo gestor direto é $manager nesta empresa */
    public function reportIds(User $manager, Hotel $hotel): array
    {
        return HotelUser::where('hotel_id', $hotel->id)
            ->where('manager_id', $manager->id)
            ->where('status', 'approved')
            ->pluck('user_id')
            ->all();
    }

    public function isManager(User $user, Hotel $hotel): bool
    {
        return HotelUser::where('hotel_id', $hotel->id)
            ->where('manager_id', $user->id)
            ->where('status', 'approved')
            ->exists();
    }

    public function isManagerOf(User $manager, Hotel $hotel, User $employee): bool
    {
        return HotelUser::where('hotel_id', $hotel->id)
            ->where('user_id', $employee->id)
            ->where('manager_id', $manager->id)
            ->where('status', 'approved')
            ->exists();
    }

    /** Pode ver dados de gestão (metas, PDI, solicitações) de $target: o próprio, o gestor dele ou um admin. */
    public function canViewPerson(User $viewer, ?HotelUser $viewerMembership, Hotel $hotel, User $target): bool
    {
        return $viewer->id === $target->id
            || $this->isAdmin($viewerMembership)
            || $this->isManagerOf($viewer, $hotel, $target);
    }

    /** Pode agir como gestor/RH sobre $target (não vale para o próprio). */
    public function canManagePerson(User $actor, ?HotelUser $actorMembership, Hotel $hotel, User $target): bool
    {
        return $actor->id !== $target->id
            && ($this->isAdmin($actorMembership) || $this->isManagerOf($actor, $hotel, $target));
    }

    /** Membro aprovado da empresa a partir do uuid do usuário. */
    public function memberByUuid(Hotel $hotel, string $uuid): ?HotelUser
    {
        return HotelUser::with('user')
            ->where('hotel_id', $hotel->id)
            ->where('status', 'approved')
            ->whereHas('user', fn ($q) => $q->where('uuid', $uuid))
            ->first();
    }

    public function avatarUrl(?User $user): ?string
    {
        return $user?->avatar_path ? asset('storage/' . $user->avatar_path) : null;
    }

    /** Resumo público de uma pessoa nas respostas do Workspace. */
    public function person(?User $user, ?HotelUser $membership = null): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'uuid'       => $user->uuid,
            'name'       => $user->name,
            'avatar_url' => $this->avatarUrl($user),
            'job_title'  => $membership?->job_title,
        ];
    }
}
