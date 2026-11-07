<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withCommands([__DIR__ . '/../app/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware) {
        // Atrás de CDN/balanceador (Cloudflare, ALB…): TRUSTED_PROXIES="*" ou lista de IPs/CIDRs separados por vírgula.
        // Sem isso o IP gravado no ponto e nos logs seria o do proxy.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(
                at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)),
                headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO,
            );
        }

        $middleware->throttleApi();

        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'auth.sanctum' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'workspace.2fa' => \App\Http\Middleware\RequireTwoFactorForPrivileged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Não autenticado.',
                'errors'  => [],
            ], 401);
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso não autorizado.',
                'errors'  => [],
            ], 403);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso não autorizado.',
                'errors'  => [],
            ], 403);
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Recurso não encontrado.',
                'errors'  => [],
            ], 404);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Recurso não encontrado.',
                'errors'  => [],
            ], 404);
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação.',
                'errors'  => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (\Illuminate\Database\QueryException $e, $request) {
            if ($e->getCode() === '23505') {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro duplicado.',
                    'errors'  => [],
                ], 409);
            }

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno. Tente novamente.',
                'errors'  => [],
            ], 500);
        });
    })->create();
