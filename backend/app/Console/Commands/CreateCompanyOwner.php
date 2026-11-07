<?php

namespace App\Console\Commands;

use App\Domain\Hotel\DTOs\CreateHotelDTO;
use App\Domain\Hotel\Services\HotelService;
use App\Domain\Subscription\Services\SubscriptionService;
use App\Enums\SubscriptionPlan;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cria (ou reaproveita) um usuário dono de empresa: usuário + empresa (Hotel)
 * + vínculo de administrador aprovado + setores padrão. Idempotente.
 *
 * Exemplo:
 *   php artisan company:create-owner --name="Eric" --email=eric@minhaempresa.com --company="Minha Empresa"
 */
class CreateCompanyOwner extends Command
{
    protected $signature = 'company:create-owner
        {--name=Eric : Nome do usuário}
        {--email=eric@inovacao.local : E-mail de login}
        {--password= : Senha (se omitida em um usuário novo, uma senha forte é gerada e exibida)}
        {--company=Inovação : Nome da empresa}
        {--cnpj= : CNPJ com 14 dígitos (se omitido, um CNPJ válido de desenvolvimento é gerado)}
        {--pro : Ativa o plano PRO Empresa na empresa}';

    protected $description = 'Cria um usuário administrador com uma empresa vinculada (ambiente de desenvolvimento).';

    /** Setores criados por padrão — base para grupos por setor, RH e financeiro. */
    private const DEFAULT_DEPARTMENTS = ['Administração', 'Recursos Humanos', 'Financeiro', 'Operações'];

    public function handle(HotelService $hotels, SubscriptionService $subscriptions): int
    {
        $email    = Str::lower(trim((string) $this->option('email')));
        $password = (string) $this->option('password');
        $generated = false;

        $user = User::withTrashed()->where('email', $email)->first();
        $isNew = ! $user;

        if ($isNew && $password === '') {
            $password  = Str::password(14, symbols: false);
            $generated = true;
        }

        $result = DB::transaction(function () use ($user, $isNew, $email, $password, $hotels, $subscriptions) {
            if ($isNew) {
                $user = new User([
                    'uuid'     => (string) Str::uuid(),
                    'name'     => (string) $this->option('name'),
                    'email'    => $email,
                    'password' => $password,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
            } else {
                if ($user->trashed()) {
                    $user->restore();
                }
                if ($password !== '') {
                    $user->forceFill(['password' => $password])->save();
                }
            }

            $companyName = (string) $this->option('company');

            $hotel = Hotel::whereHas('hotelUsers', fn ($q) => $q->where('user_id', $user->id))
                ->where('name', $companyName)
                ->first();

            if (! $hotel) {
                $hotel = $hotels->createHotel($user, CreateHotelDTO::fromArray([
                    'name'          => $companyName,
                    'description'   => 'Empresa criada para o ambiente de gestão.',
                    'contact_email' => $user->email,
                    'segment'       => 'Gestão empresarial',
                    'cnpj'          => $this->option('cnpj') ?: $this->generateCnpj(),
                    'country'       => 'Brasil',
                ]));
            }

            // O repositório de hotéis não grava o CNPJ na criação: garante aqui (usado no login da empresa).
            if (! $hotel->cnpj) {
                $hotel->forceFill(['cnpj' => $this->option('cnpj') ?: $this->generateCnpj()])->save();
            }

            // Garante o vínculo como administrador aprovado.
            $hotel->hotelUsers()->updateOrCreate(
                ['user_id' => $user->id],
                ['role' => 'admin', 'status' => 'approved'],
            );

            foreach (self::DEFAULT_DEPARTMENTS as $name) {
                $hotel->departments()->firstOrCreate(
                    ['name' => $name],
                    ['uuid' => (string) Str::uuid()],
                );
            }

            if ($this->option('pro')) {
                $subscriptions->setPlan($hotel, SubscriptionPlan::ProEmpresa);
            }

            return [$user, $hotel];
        });

        [$user, $hotel] = $result;

        $this->info($isNew ? 'Usuário e empresa criados.' : 'Usuário já existia — empresa e vínculo garantidos.');
        $this->table(['Campo', 'Valor'], [
            ['Nome', $user->name],
            ['E-mail (login)', $user->email],
            ['Senha', $generated || $password !== '' ? $password : '(inalterada)'],
            ['Empresa', $hotel->name],
            ['CNPJ (login da empresa)', $hotel->cnpj],
            ['Slug', $hotel->slug],
            ['Plano PRO Empresa', $this->option('pro') ? 'ativado' : 'não'],
        ]);

        if ($generated) {
            $this->warn('Guarde a senha acima: ela foi gerada agora e não será exibida novamente.');
        }

        return self::SUCCESS;
    }

    /** Gera um CNPJ com dígitos verificadores válidos (somente para desenvolvimento). */
    private function generateCnpj(): string
    {
        do {
            $base = '';
            for ($i = 0; $i < 8; $i++) {
                $base .= random_int(0, 9);
            }
            $base .= '0001';
            $cnpj = $base . $this->cnpjDigit($base) . $this->cnpjDigit($base . $this->cnpjDigit($base));
        } while (Hotel::withTrashed()->where('cnpj', $cnpj)->exists());

        return $cnpj;
    }

    private function cnpjDigit(string $digits): int
    {
        $len     = strlen($digits);
        $weights = $len === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum     = 0;
        foreach ($weights as $i => $w) {
            $sum += (int) $digits[$i] * $w;
        }
        $mod = $sum % 11;

        return $mod < 2 ? 0 : 11 - $mod;
    }
}
