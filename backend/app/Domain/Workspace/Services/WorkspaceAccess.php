<?php

namespace App\Domain\Workspace\Services;

use App\Models\Delegation;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Carbon\CarbonImmutable;

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

    /** Administra pessoas (RH): dono ou RH. O papel de acesso, quando definido, prevalece sobre `role`. */
    public function isAdmin(?HotelUser $membership): bool
    {
        return in_array($this->accessRole($membership), ['owner', 'hr'], true);
    }

    public function isOwner(?HotelUser $membership): bool
    {
        return $this->accessRole($membership) === 'owner';
    }

    /** Painel da diretoria (números agregados): dono, RH e diretoria. */
    public function canSeeDashboard(?HotelUser $membership): bool
    {
        return in_array($this->accessRole($membership), ['owner', 'hr', 'director'], true);
    }

    /** Pode anexar/consultar holerites de todos: dono, RH e financeiro. */
    public function canHandlePayslips(?HotelUser $membership): bool
    {
        return in_array($this->accessRole($membership), ['owner', 'hr', 'finance'], true);
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

    /** Usuários que administram pessoas (dono/RH) na empresa — destino de avisos de RH. */
    public function adminUsers(Hotel $hotel)
    {
        return HotelUser::with('user')->where('hotel_id', $hotel->id)->where('status', 'approved')
            ->where(fn ($q) => $q->whereIn('access_role', ['owner', 'hr'])
                ->orWhere(fn ($q2) => $q2->whereNull('access_role')->where('role', 'admin')))
            ->get()->pluck('user')->filter();
    }

    /** Substituições ativas hoje em que $user é o substituto: ids de quem ele está cobrindo. */
    private function coveredIds(User $user, Hotel $hotel): array
    {
        $today = CarbonImmutable::now('America/Sao_Paulo')->toDateString();

        return Delegation::where('hotel_id', $hotel->id)->where('to_user_id', $user->id)
            ->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)
            ->pluck('from_user_id')->all();
    }

    /**
     * Ids das pessoas que $manager enxerga e pode gerir nesta empresa (nunca inclui ele mesmo):
     *  - equipe direta e TODA a árvore abaixo (gestor de gestores);
     *  - todos os membros das áreas (setores) que ele chefia, e as equipes de cada um;
     *  - o alcance de quem ele está substituindo hoje.
     *
     * @return int[]
     */
    public function reportIds(User $manager, Hotel $hotel): array
    {
        // Memoizado na REQUISIÇÃO atual (não no serviço): controllers podem sobreviver a várias requisições (testes, Octane).
        $bag = request()->attributes;
        $key = 'ws_scope:' . $hotel->id . ':' . $manager->id;
        if ($bag->has($key)) {
            return $bag->get($key);
        }

        $rows = HotelUser::where('hotel_id', $hotel->id)->where('status', 'approved')
            ->get(['user_id', 'manager_id', 'department_id']);
        $childrenOf = $rows->filter(fn ($r) => $r->manager_id)->groupBy('manager_id')->map(fn ($g) => $g->pluck('user_id')->all());

        $roots   = array_merge([$manager->id], $this->coveredIds($manager, $hotel));
        $headed  = Department::where('hotel_id', $hotel->id)->whereIn('head_id', $roots)->pluck('id')->all();
        $seeds   = $roots;
        foreach ($rows as $r) {
            if ($r->department_id && in_array($r->department_id, $headed, true)) {
                $seeds[] = $r->user_id;
            }
        }

        $seen  = [];
        $queue = array_unique($seeds);
        $depth = 0;
        while ($queue && $depth++ < 12) {
            $next = [];
            foreach ($queue as $id) {
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                foreach ($childrenOf->get($id, []) as $child) {
                    $next[] = $child;
                }
            }
            $queue = $next;
        }

        $result = array_values(array_diff(array_keys($seen), $roots));

        $bag->set($key, $result);

        return $result;
    }

    public function isManager(User $user, Hotel $hotel): bool
    {
        return $this->reportIds($user, $hotel) !== [];
    }

    public function isManagerOf(User $manager, Hotel $hotel, User $employee): bool
    {
        return in_array($employee->id, $this->reportIds($manager, $hotel), true);
    }

    /**
     * Quem analisa pedidos de $member: gestor direto (e quem o substitui hoje); sem gestor, o chefe da área
     * (e substitutos); sem nenhum dos dois, dono/RH. Nunca a própria pessoa.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function reviewersFor(HotelUser $member, Hotel $hotel)
    {
        $base = $member->manager_id ? [$member->manager_id] : [];
        if (! $base && $member->department_id) {
            $head = Department::where('id', $member->department_id)->value('head_id');
            $base = $head ? [$head] : [];
        }

        if ($base) {
            $today   = CarbonImmutable::now('America/Sao_Paulo')->toDateString();
            $delegates = Delegation::where('hotel_id', $hotel->id)->whereIn('from_user_id', $base)
                ->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)->pluck('to_user_id')->all();
            $users = User::whereIn('id', array_unique(array_merge($base, $delegates)))->where('id', '!=', $member->user_id)->get();
            if ($users->isNotEmpty()) {
                return $users;
            }
        }

        return $this->adminUsers($hotel)->reject(fn ($u) => $u->id === $member->user_id)->values();
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
