<?php

/*
 * CORS: a API só aceita chamadas de navegador vindas das origens listadas.
 * Em produção, defina CORS_ALLOWED_ORIGINS (separadas por vírgula) ou, no mínimo, APP_URL.
 * O app em produção é servido na mesma origem da API, então normalmente basta o próprio domínio.
 */
$origins = array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))));

if (! $origins && env('APP_URL')) {
    $origins = [rtrim((string) env('APP_URL'), '/')];
}
if (env('APP_ENV', 'production') !== 'production') {
    $origins = array_values(array_unique(array_merge($origins, [
        'http://localhost:9595', 'http://localhost:9599', 'http://localhost:3000', 'http://localhost:5173',
    ])));
}

return [
    'paths'                    => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods'          => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins'          => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers'          => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With', 'X-XSRF-TOKEN'],
    'exposed_headers'          => [],
    'max_age'                  => 600,
    'supports_credentials'     => false,
];
