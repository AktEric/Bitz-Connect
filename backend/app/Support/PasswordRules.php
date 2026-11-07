<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/** Política de senha única do produto: 10+ caracteres, maiúscula, minúscula e número; em produção, fora de vazamentos conhecidos. */
class PasswordRules
{
    public static function policy(): Password
    {
        $rule = Password::min(10)->letters()->mixedCase()->numbers();

        // Consulta o serviço de senhas vazadas (k-anonimato: só um pedaço do hash sai do servidor).
        return app()->isProduction() ? $rule->uncompromised(3) : $rule;
    }
}
