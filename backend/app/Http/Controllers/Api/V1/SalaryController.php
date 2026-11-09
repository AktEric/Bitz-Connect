<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Salário: privado. Só a PRÓPRIA pessoa lê, e só quando pede (reveal). O RH/dono apenas grava,
 * sem conseguir ler de volta por aqui. O valor fica criptografado e nunca entra em listagens, fichas ou logs.
 */
class SalaryController extends WorkspaceBaseController
{
    /** GET /workspaces/{slug}/me/salary — só informa se há salário cadastrado (não devolve o valor). */
    public function status(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        return $this->success('Salário consultado.', ['configured' => $m->salary !== null]);
    }

    /** POST /workspaces/{slug}/me/salary/reveal — devolve o valor, a pedido da própria pessoa. */
    public function reveal(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        return $this->success('Salário carregado.', ['salary' => $m->salary === null ? null : (float) $m->salary, 'currency' => 'BRL'])
            ->header('Cache-Control', 'private, no-store');
    }

    /** PUT /workspaces/{slug}/people/{uuid}/salary  { salary: número|null } — só RH/dono; a resposta não traz o valor. */
    public function set(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        if (! $this->access->isAdmin($this->membership($request, $hotel))) {
            return $this->forbidden('Somente o dono ou o RH cadastram salários.');
        }
        $target = $this->access->memberByUuid($hotel, $uuid);
        if (! $target) {
            return $this->notFound('Pessoa não encontrada.');
        }
        $data = $request->validate(['salary' => ['present', 'nullable', 'numeric', 'min:0', 'max:99999999.99']]);

        $target->forceFill(['salary' => $data['salary'] === null ? null : number_format((float) $data['salary'], 2, '.', '')])->save();
        // o valor nunca vai para o log
        AuditLog::record($request, $hotel, 'salary.updated', $target->user);

        return $this->success('Salário atualizado.', ['configured' => $target->salary !== null]);
    }
}
