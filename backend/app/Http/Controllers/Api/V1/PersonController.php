<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Perfil e ficha da pessoa.
 * - Perfil social: a própria pessoa preenche (bio, livro e comida favoritos…); todos da empresa veem.
 * - Ficha cadastral: só o RH/dono preenche (nascimento, endereço, contato de emergência, dados bancários);
 *   fica criptografada e só o RH e a própria pessoa leem. Toda leitura por outra pessoa é auditada.
 */
class PersonController extends WorkspaceBaseController
{
    private const PROFILE_FIELDS = ['bio' => 400, 'favorite_book' => 120, 'favorite_food' => 120, 'favorite_movie' => 120, 'hobbies' => 200, 'fun_fact' => 200, 'hometown' => 80];

    private function profileOf(HotelUser $m): array
    {
        $p = (array) ($m->profile ?? []);

        return array_map(fn ($k) => $p[$k] ?? null, array_combine(array_keys(self::PROFILE_FIELDS), array_keys(self::PROFILE_FIELDS)));
    }

    private function publicCard(Hotel $hotel, HotelUser $m): array
    {
        $m->loadMissing(['user', 'department']);
        $b = $m->birthday_visible && $m->birth_date ? $m->birth_date : null;

        return [
            'person'     => $this->access->person($m->user, $m),
            'department' => $m->department?->name,
            'hired_at'   => $m->hired_at?->toDateString(),
            'birthday'   => $b ? ['day' => $b->day, 'month' => $b->month] : null,
            'profile'    => $this->profileOf($m),
        ];
    }

    /** GET /workspaces/{slug}/me/profile */
    public function mine(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        return $this->success('Perfil carregado.', $this->publicCard($hotel, $m) + [
            'birthday_visible' => (bool) $m->birthday_visible,
            'has_birth_date'   => $m->birth_date !== null,
            'email'            => $request->user()->email,
        ]);
    }

    /** PUT /workspaces/{slug}/me/profile — só o perfil social e a visibilidade do aniversário (a data é do RH). */
    public function updateMine(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $rules = ['birthday_visible' => ['sometimes', 'boolean']];
        foreach (self::PROFILE_FIELDS as $k => $max) {
            $rules[$k] = ['nullable', 'string', "max:{$max}"];
        }
        $data = $request->validate($rules);

        $profile = $this->profileOf($m);
        foreach (array_keys(self::PROFILE_FIELDS) as $k) {
            if (array_key_exists($k, $data)) {
                $profile[$k] = ($v = trim((string) $data[$k])) === '' ? null : $v;
            }
        }
        $m->forceFill(['profile' => $profile] + (isset($data['birthday_visible']) ? ['birthday_visible' => $data['birthday_visible']] : []))->save();

        return $this->success('Perfil atualizado.', $this->publicCard($hotel, $m->refresh()) + ['birthday_visible' => (bool) $m->birthday_visible]);
    }

    /** GET /workspaces/{slug}/people/{uuid}/profile — cartão público de qualquer colega. */
    public function show(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        if (! $this->membership($request, $hotel)) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $target = $this->access->memberByUuid($hotel, $uuid);
        if (! $target) {
            return $this->notFound('Pessoa não encontrada.');
        }

        return $this->success('Perfil carregado.', $this->publicCard($hotel, $target));
    }

    private const RECORD_FIELDS = [
        'phone' => 30, 'zip' => 12, 'street' => 120, 'number' => 12, 'complement' => 60, 'district' => 60, 'city' => 60, 'state' => 2,
        'emergency_name' => 80, 'emergency_relation' => 40, 'emergency_phone' => 30,
        'bank' => 60, 'agency' => 12, 'account' => 20, 'account_type' => 20, 'pix' => 80,
    ];

    private function recordOf(HotelUser $m): array
    {
        $d = (array) ($m->private_data ?? []);
        $out = ['birth_date' => $m->birth_date?->toDateString(), 'hired_at' => $m->hired_at?->toDateString()];
        foreach (array_keys(self::RECORD_FIELDS) as $k) {
            $out[$k] = $d[$k] ?? null;
        }

        return $out;
    }

    /** GET /workspaces/{slug}/people/{uuid}/record — ficha cadastral (RH/dono, ou a própria pessoa). */
    public function record(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $me = $this->membership($request, $hotel);
        $target = $me ? $this->access->memberByUuid($hotel, $uuid) : null;
        if (! $target || ($target->user_id !== $request->user()->id && ! $this->access->isAdmin($me))) {
            return $this->notFound('Pessoa não encontrada.');
        }
        if ($target->user_id !== $request->user()->id) {
            AuditLog::record($request, $hotel, 'person.record_viewed', $target->user);
        }

        return $this->success('Ficha carregada.', $this->recordOf($target) + ['can_edit' => $this->access->isAdmin($me)]);
    }

    /** PUT /workspaces/{slug}/people/{uuid}/record — só RH/dono preenchem. */
    public function updateRecord(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        if (! $this->access->isAdmin($this->membership($request, $hotel))) {
            return $this->forbidden('Somente o dono ou o RH preenchem a ficha cadastral.');
        }
        $target = $this->access->memberByUuid($hotel, $uuid);
        if (! $target) {
            return $this->notFound('Pessoa não encontrada.');
        }
        $rules = ['birth_date' => ['nullable', 'date_format:Y-m-d', 'after:1920-01-01', 'before:today']];
        foreach (self::RECORD_FIELDS as $k => $max) {
            $rules[$k] = ['nullable', 'string', "max:{$max}"];
        }
        $data = $request->validate($rules);

        $private = (array) ($target->private_data ?? []);
        foreach (array_keys(self::RECORD_FIELDS) as $k) {
            if (array_key_exists($k, $data)) {
                $private[$k] = ($v = trim((string) $data[$k])) === '' ? null : $v;
            }
        }
        $changes = ['private_data' => $private];
        if (array_key_exists('birth_date', $data)) {
            $changes['birth_date'] = $data['birth_date'];
        }
        $target->forceFill($changes)->save();
        // nunca grava os valores no log: só quais campos mudaram
        AuditLog::record($request, $hotel, 'person.record_updated', $target->user, ['fields' => array_keys($data)]);

        return $this->success('Ficha salva.', $this->recordOf($target->refresh()) + ['can_edit' => true]);
    }
}
