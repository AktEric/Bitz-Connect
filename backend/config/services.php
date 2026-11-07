<?php

return [
    // Login com Google (Google Identity Services). Crie um "OAuth client ID" do tipo Web em
    // console.cloud.google.com e informe em GOOGLE_CLIENT_ID. Sem ele o botão fica oculto.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],
];
