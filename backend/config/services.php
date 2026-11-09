<?php

return [
    // Login com Google (Google Identity Services). Crie um "OAuth client ID" do tipo Web em
    // console.cloud.google.com e informe em GOOGLE_CLIENT_ID. Sem ele o botão fica oculto.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    // Bianca (central de ajuda): 'local' = motor gratuito, sem IA externa (padrão); 'claude' = usa a Claude API (gasta tokens).
    'help' => ['driver' => env('HELP_DRIVER', 'local')],

    // Bianca, a assistente da central de ajuda (Claude API). Sem ANTHROPIC_API_KEY o chat avisa que não está configurado.
    'anthropic' => [
        'key'     => env('ANTHROPIC_API_KEY'),
        'model'   => env('ANTHROPIC_MODEL', 'claude-haiku-5-5'),
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 30),
    ],
];
