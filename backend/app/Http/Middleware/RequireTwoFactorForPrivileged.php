<?php

namespace App\Http\Middleware;

use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Models\Hotel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quando a empresa exige verificação em duas etapas para perfis privilegiados (dono, RH e financeiro),
 * quem ainda não ativou só consegue abrir o Workspace (para ver o aviso) e a tela de Segurança.
 */
class RequireTwoFactorForPrivileged
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $hotel = $request->route('hotel');
        $hotel = $hotel instanceof Hotel ? $hotel : (is_string($hotel) ? Hotel::where('slug', $hotel)->first() : null);

        if (! $user || ! $hotel || ! ($hotel->security_settings['require_2fa'] ?? false) || $user->hasTwoFactor()) {
            return $next($request);
        }

        $member = $this->access->membership($user, $hotel);
        if (! $member || ! in_array($this->access->accessRole($member, $hotel), ['owner', 'hr', 'finance', 'director'], true)) {
            return $next($request);
        }

        $action = $request->route()?->getActionName();
        if (in_array($action, [WorkspaceController::class . '@show', WorkspaceController::class . '@security'], true)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Sua empresa exige verificação em duas etapas para o seu perfil. Ative em Segurança para continuar.',
            'errors'  => [],
            'code'    => 'two_factor_required',
        ], 403);
    }
}
