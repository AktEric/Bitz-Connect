<?php

namespace Database\Seeders;

use App\Domain\Workspace\Services\TimeClock;
use App\Domain\Workspace\Services\TimeClosingService;
use App\Domain\Workspace\Services\TimeEntryRecorder;
use App\Models\CalendarEvent;
use App\Models\Delegation;
use App\Models\Department;
use App\Models\DevelopmentPlan;
use App\Models\Goal;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\Payslip;
use App\Models\TimeAdjustment;
use App\Models\TimeBankAdjustment;
use App\Models\TimeClosing;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkspaceJob;
use App\Models\WorkspacePost;
use App\Models\WorkspaceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cenário de DEMONSTRAÇÃO do sistema de RH (Workspace): um hotel com ~35 funcionários,
 * setores, escalas, ~3 meses de ponto, fechamentos, holerites, solicitações, metas, PDIs,
 * calendário, pesquisa de clima e vagas internas. Nunca use em produção.
 *
 * Conta dona da empresa: DEMO_ADMIN_EMAIL / DEMO_ADMIN_PASSWORD (backend/.env); padrão admin@admin.com / admin.
 * Demais funcionários: senha Teste@12345 (lista em storage/app/hr-demo-credentials.txt).
 *
 * Rodar com: php artisan db:seed --class=HrDemoSeeder   (recria a empresa do zero a cada execução)
 */
class HrDemoSeeder extends Seeder
{
    private const PASSWORD = 'Teste@12345';
    private const DOMAIN   = 'serraazul.local';
    private const SLUG     = 'grand-hotel-serra-azul';

    private Hotel $hotel;

    /** @var array<string, User> chave curta => usuário */
    private array $u = [];

    /** @var array<string, HotelUser> */
    private array $m = [];

    /** @var array<string, Department> */
    private array $d = [];

    /** @var array<string, WorkSchedule> */
    private array $s = [];

    /** @var array<string, array> chave => especificação da pessoa */
    private array $spec = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Seeder de demonstração não pode rodar em produção.');
        }

        mt_srand(2026);

        Model::unguarded(function () {
            DB::transaction(function () {
                $this->company();
                $this->people();
            });
            $this->requests();       // antes do ponto: férias aprovadas zeram a jornada esperada
            $this->timeClock();
            DB::transaction(function () {
                $this->closings();
                $this->payslips();
                $this->goals();
                $this->development();
                $this->calendar();
                $this->climate();
                $this->jobs();
                $this->feed();
            });
        });

        $this->credentials();
    }

    // ──────────────────────────────────────────────────────────
    // Empresa, setores e escalas
    // ──────────────────────────────────────────────────────────

    private function company(): void
    {
        // Recria do zero: apaga a empresa anterior (cascade leva ponto, holerites etc.) e os funcionários fictícios.
        Hotel::withTrashed()->where('slug', self::SLUG)->get()->each->forceDelete();
        User::withTrashed()->where('email', 'like', '%@' . self::DOMAIN)->forceDelete();
        Storage::disk('local')->deleteDirectory('payslips/demo-hr');

        $this->hotel = Hotel::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Grand Hotel Serra Azul', 'slug' => self::SLUG,
            'cnpj' => '12.345.678/0001-90', 'segment' => 'Hotel',
            'description' => 'Hotel 4 estrelas com 120 apartamentos, centro de eventos e restaurante, na Serra Gaúcha.',
            'city' => 'Gramado', 'state' => 'RS', 'country' => 'Brasil',
            'phone' => '(54) 3286-4100', 'contact_email' => 'contato@' . self::DOMAIN,
            'onboarding_dismissed_at' => now(),
        ]);

        foreach (['Diretoria', 'Recursos Humanos', 'Financeiro', 'Recepção', 'Governança', 'Alimentos & Bebidas', 'Manutenção', 'Comercial & Eventos'] as $name) {
            $this->d[$name] = Department::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => $name]);
        }

        // minutos esperados por dia: 0 = domingo … 6 = sábado
        $schedules = [
            'adm'   => ['Administrativo 44h (seg–sex)', [0, 528, 528, 528, 528, 528, 0]],
            'six'   => ['Escala 6x1 — 44h', [0, 440, 440, 440, 440, 440, 440]],
            'half'  => ['Meio período 30h', [0, 360, 360, 360, 360, 360, 0]],
            'night' => ['Noturno 6x1 — 42h', [420, 420, 420, 420, 420, 420, 0]],
        ];
        foreach ($schedules as $k => [$name, $week]) {
            $this->s[$k] = WorkSchedule::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'name' => $name, 'week_minutes' => $week]);
        }
    }

    // ──────────────────────────────────────────────────────────
    // Pessoas
    // ──────────────────────────────────────────────────────────

    private function people(): void
    {
        $email    = (string) (env('DEMO_ADMIN_EMAIL') ?: AdminUserSeeder::EMAIL);
        $password = (string) (env('DEMO_ADMIN_PASSWORD') ?: AdminUserSeeder::PASSWORD);

        $owner = User::withTrashed()->firstOrNew(['email' => $email]);
        $owner->fill(['name' => $owner->exists ? $owner->name : 'Eric Ribeiro', 'password' => $password]);
        $owner->uuid ??= (string) Str::uuid();
        $owner->forceFill(['role' => 'platform_admin', 'email_verified_at' => now(), 'deleted_at' => null, 'city' => 'Gramado', 'state' => 'RS'])->save();
        $this->u['owner'] = $owner;

        // chave => [nome, cargo, setor, gestor, admissão, nascimento, escala, salário (R$), papel de acesso]
        $this->spec = [
            'owner'     => [$owner->name,           'Diretor Geral',                    'Diretoria',           null,        '2018-02-01', '1986-04-12', 'adm',   28000, 'owner'],
            'helena'    => ['Helena Duarte',        'Gerente Geral',                    'Diretoria',           'owner',     '2019-03-11', '1980-09-23', 'adm',   18500, 'director'],
            'marina'    => ['Marina Costa',         'Gerente de RH',                    'Recursos Humanos',    'owner',     '2020-06-01', '1988-01-30', 'adm',   11200, 'hr'],
            'ana'       => ['Ana Paula Ribeiro',    'Analista de Departamento Pessoal', 'Recursos Humanos',    'marina',    '2022-08-15', '1994-11-05', 'adm',    5400, 'hr'],
            'lucas'     => ['Lucas Fernandes',      'Assistente de RH',                 'Recursos Humanos',    'marina',    '2024-02-19', '1999-07-17', 'adm',    3300, null],
            'carlos'    => ['Carlos Mendes',        'Coordenador Financeiro',           'Financeiro',          'helena',    '2019-10-07', '1983-03-02', 'adm',    9800, 'finance'],
            'beatriz'   => ['Beatriz Lima',         'Analista Financeiro',              'Financeiro',          'carlos',    '2023-05-08', '1996-12-14', 'adm',    5200, null],
            'diego'     => ['Diego Martins',        'Auxiliar Administrativo',          'Financeiro',          'carlos',    '2025-01-13', '2001-05-28', 'adm',    2600, null],
            'rafael'    => ['Rafael Souza',         'Gerente de Hospedagem',            'Recepção',            'helena',    '2020-01-20', '1985-08-09', 'adm',   10400, null],
            'juliana'   => ['Juliana Alves',        'Supervisora de Recepção',          'Recepção',            'rafael',    '2021-04-05', '1992-02-21', 'six',    4600, null],
            'gabriel'   => ['Gabriel Rocha',        'Recepcionista',                    'Recepção',            'juliana',   '2023-03-13', '1998-10-03', 'six',    2850, null],
            'larissa'   => ['Larissa Monteiro',     'Recepcionista Bilíngue',           'Recepção',            'juliana',   '2024-06-03', '2000-06-11', 'six',    3200, null],
            'thiago'    => ['Thiago Pereira',       'Recepcionista Noturno',            'Recepção',            'juliana',   '2022-11-21', '1995-01-25', 'night',  3150, null],
            'vanessa'   => ['Vanessa Cardoso',      'Mensageira',                       'Recepção',            'juliana',   '2025-07-01', '2003-03-19', 'six',    2200, null],
            'patricia'  => ['Patrícia Gomes',       'Governanta',                       'Governança',          'rafael',    '2019-08-12', '1979-12-01', 'six',    5600, null],
            'rosangela' => ['Rosângela Silva',      'Supervisora de Andares',           'Governança',          'patricia',  '2020-09-14', '1977-05-30', 'six',    3400, null],
            'maria'     => ['Maria do Carmo Souza', 'Camareira',                        'Governança',          'rosangela', '2018-11-05', '1975-07-08', 'six',    2150, null],
            'jessica'   => ['Jéssica Nunes',        'Camareira',                        'Governança',          'rosangela', '2023-09-04', '1997-09-15', 'six',    2050, null],
            'claudia'   => ['Cláudia Ramos',        'Camareira',                        'Governança',          'rosangela', '2024-10-14', '1990-04-04', 'six',    2050, null],
            'edson'     => ['Edson Batista',        'Auxiliar de Lavanderia',           'Governança',          'patricia',  '2021-02-08', '1984-10-22', 'six',    2000, null],
            'renato'    => ['Renato Oliveira',      'Gerente de A&B',                   'Alimentos & Bebidas', 'helena',    '2021-05-17', '1982-06-16', 'adm',    9200, null],
            'fernando'  => ['Fernando Castro',      'Chef de Cozinha',                  'Alimentos & Bebidas', 'renato',    '2020-03-02', '1981-11-29', 'six',    7800, null],
            'aline'     => ['Aline Teixeira',       'Sous Chef',                        'Alimentos & Bebidas', 'fernando',  '2022-07-11', '1991-08-07', 'six',    4900, null],
            'joao'      => ['João Victor Lopes',    'Cozinheiro',                       'Alimentos & Bebidas', 'fernando',  '2023-11-06', '1997-02-13', 'six',    2900, null],
            'bruna'     => ['Bruna Azevedo',        'Auxiliar de Cozinha',              'Alimentos & Bebidas', 'fernando',  '2025-03-10', '2002-12-24', 'six',    2100, null],
            'marcelo'   => ['Marcelo Freitas',      'Maître',                           'Alimentos & Bebidas', 'renato',    '2019-12-02', '1978-03-27', 'six',    4300, null],
            'camila'    => ['Camila Barros',        'Garçonete',                        'Alimentos & Bebidas', 'marcelo',   '2024-04-15', '1999-05-06', 'six',    2300, null],
            'felipe'    => ['Felipe Moreira',       'Barman',                           'Alimentos & Bebidas', 'marcelo',   '2023-01-23', '1996-08-31', 'six',    2600, null],
            'roberto'   => ['Roberto Pinto',        'Supervisor de Manutenção',         'Manutenção',          'helena',    '2019-05-06', '1976-01-18', 'adm',    5100, null],
            'sergio'    => ['Sérgio Antunes',       'Eletricista',                      'Manutenção',          'roberto',   '2021-10-04', '1987-04-09', 'six',    3600, null],
            'wagner'    => ['Wagner Dias',          'Auxiliar de Manutenção',           'Manutenção',          'roberto',   '2024-08-05', '1993-09-02', 'six',    2400, null],
            'daniela'   => ['Daniela Vieira',       'Gerente Comercial e de Eventos',   'Comercial & Eventos', 'helena',    '2020-11-09', '1987-07-21', 'adm',   10100, null],
            'priscila'  => ['Priscila Carvalho',    'Executiva de Vendas',              'Comercial & Eventos', 'daniela',   '2022-03-21', '1993-10-10', 'adm',    4800, null],
            'igor'      => ['Igor Santana',         'Coordenador de Eventos',           'Comercial & Eventos', 'daniela',   '2023-06-12', '1992-03-15', 'adm',    4500, null],
            'leticia'   => ['Letícia Prado',        'Estagiária de Marketing',          'Comercial & Eventos', 'daniela',   '2025-08-04', '2004-01-09', 'half',   1400, null],
        ];

        // Datas relativas a hoje, para a Início sempre ter aniversariantes do dia e admissões recentes
        $today = CarbonImmutable::now(TimeClock::TZ);
        $this->spec['camila'][5] = '2000-' . $today->format('m-d');
        $this->spec['felipe'][5] = '1996-' . $today->format('m-d');
        foreach (['leticia' => 6, 'bruna' => 19, 'vanessa' => 38] as $k => $days) {
            $this->spec[$k][4] = $today->subDays($days)->toDateString();
        }

        $cities = [['Gramado', '95670-000'], ['Canela', '95680-000'], ['Gramado', '95670-000'], ['Nova Petrópolis', '95150-000']];
        $streets = ['Rua Garibaldi', 'Av. Borges de Medeiros', 'Rua São Pedro', 'Rua Madre Verônica', 'Av. das Hortênsias', 'Rua Emílio Sörensen', 'Rua Pedro Benetti', 'Rua Leopoldo Rosenfeldt'];
        $districts = ['Centro', 'Planalto', 'Floresta', 'Carniel', 'Piratini', 'Avenida Central'];
        $banks = [['Banco do Brasil', 'Conta corrente'], ['Caixa Econômica', 'Conta poupança'], ['Itaú', 'Conta corrente'], ['Bradesco', 'Conta corrente'], ['Sicredi', 'Conta corrente'], ['Nubank', 'Conta de pagamento']];
        $relations = ['Mãe', 'Pai', 'Cônjuge', 'Irmã', 'Irmão', 'Companheira', 'Companheiro'];
        $emergencyNames = ['Sandra', 'José', 'Márcia', 'Paulo', 'Luciana', 'Antônio', 'Cristina', 'Ricardo'];

        $i = 0;
        foreach ($this->spec as $k => [$name, $title, $dept, $manager, $hired, $birth, $sched, $salary, $access]) {
            if ($k !== 'owner') {
                $user = User::create([
                    'uuid' => (string) Str::uuid(), 'name' => $name, 'password' => self::PASSWORD,
                    'email' => Str::slug(Str::before($name, ' ') . '.' . Str::afterLast($name, ' '), '.') . '@' . self::DOMAIN,
                    'email_verified_at' => now(), 'city' => 'Gramado', 'state' => 'RS',
                ]);
                $this->u[$k] = $user;
            }
            [$city, $zip] = $cities[$i % count($cities)];
            [$bank, $accType] = $banks[$i % count($banks)];
            $phone = sprintf('(54) 9%04d-%04d', mt_rand(8100, 9999), mt_rand(0, 9999));

            $this->m[$k] = HotelUser::create([
                'hotel_id' => $this->hotel->id, 'user_id' => $this->u[$k]->id,
                'role' => in_array($access, ['owner', 'hr'], true) ? 'admin' : 'employee', 'access_role' => $access,
                'status' => 'approved', 'department_id' => $this->d[$dept]->id, 'job_title' => $title, 'hired_at' => $hired,
                'manager_id' => $manager ? $this->u[$manager]->id : null, 'work_schedule_id' => $this->s[$sched]->id,
                'birth_date' => $birth, 'birthday_visible' => $i % 7 !== 3, 'punch_consent_at' => CarbonImmutable::parse($hired)->addDay(),
                'private_data' => [
                    'phone' => $phone, 'zip' => $zip, 'street' => $streets[$i % count($streets)], 'number' => (string) mt_rand(12, 1890),
                    'complement' => $i % 3 === 0 ? 'Apto ' . mt_rand(101, 408) : null, 'district' => $districts[$i % count($districts)],
                    'city' => $city, 'state' => 'RS',
                    'emergency_name' => $emergencyNames[$i % count($emergencyNames)] . ' ' . Str::afterLast($name, ' '),
                    'emergency_relation' => $relations[$i % count($relations)],
                    'emergency_phone' => sprintf('(54) 9%04d-%04d', mt_rand(8100, 9999), mt_rand(0, 9999)),
                    'bank' => $bank, 'agency' => sprintf('%04d', mt_rand(100, 4999)),
                    'account' => sprintf('%05d-%d', mt_rand(10000, 99999), mt_rand(0, 9)), 'account_type' => $accType,
                    'pix' => $i % 2 ? $this->u[$k]->email : $phone,
                ],
            ]);
            $i++;
        }

        $heads = ['Diretoria' => 'owner', 'Recursos Humanos' => 'marina', 'Financeiro' => 'carlos', 'Recepção' => 'rafael', 'Governança' => 'patricia',
            'Alimentos & Bebidas' => 'renato', 'Manutenção' => 'roberto', 'Comercial & Eventos' => 'daniela'];
        foreach ($heads as $dept => $k) {
            $this->d[$dept]->forceFill(['head_id' => $this->u[$k]->id])->save();
        }

        // Rafael cobre a Helena durante as férias dela
        Delegation::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'from_user_id' => $this->u['helena']->id,
            'to_user_id' => $this->u['rafael']->id, 'starts_on' => '2026-11-16', 'ends_on' => '2026-11-30', 'created_by' => $this->u['marina']->id]);
    }

    // ──────────────────────────────────────────────────────────
    // Solicitações / chamados ao RH
    // ──────────────────────────────────────────────────────────

    private function requests(): void
    {
        $hr = $this->u['marina'];
        $dp = $this->u['ana'];
        $req = function (string $who, array $data, array $comments = []) {
            $r = WorkspaceRequest::create($data + ['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'requester_id' => $this->u[$who]->id]);
            foreach ($comments as [$author, $body, $at]) {
                $r->comments()->create(['author_id' => $this->u[$author]->id, 'body' => $body, 'created_at' => $at, 'updated_at' => $at]);
            }

            return $r;
        };
        $at = fn (string $d) => CarbonImmutable::parse($d, TimeClock::TZ)->utc();

        // Férias já gozadas (aprovadas) — abatem a jornada no período
        $req('gabriel', ['type' => 'vacation', 'category' => 'other', 'title' => 'Férias — 1º período aquisitivo', 'description' => 'Quero tirar 20 dias e vender 10 (abono pecuniário).',
            'starts_on' => '2026-08-10', 'ends_on' => '2026-08-29', 'status' => 'approved', 'decided_by' => $hr->id, 'decided_at' => $at('2026-07-01 10:12'),
            'decision_note' => 'Aprovado. Aviso de férias e recibo serão enviados em Recebimentos.', 'assigned_to' => $hr->id, 'first_response_at' => $at('2026-06-30 16:40'),
            'created_at' => $at('2026-06-29 09:15')]);
        $req('jessica', ['type' => 'vacation', 'title' => 'Férias de setembro', 'description' => 'Viagem para visitar a família em Recife.',
            'starts_on' => '2026-09-14', 'ends_on' => '2026-10-03', 'status' => 'approved', 'decided_by' => $hr->id, 'decided_at' => $at('2026-08-05 14:30'),
            'decision_note' => 'Aprovado com a Rosângela; escala ajustada.', 'assigned_to' => $hr->id, 'created_at' => $at('2026-08-01 08:02')]);
        $req('carlos', ['type' => 'vacation', 'title' => 'Férias — outubro', 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-28', 'status' => 'approved',
            'decided_by' => $this->u['helena']->id, 'decided_at' => $at('2026-09-10 11:00'), 'created_at' => $at('2026-09-08 17:45')]);
        // Férias em andamento e próximas, relativas a hoje (aparecem em "Ausências" na Início)
        $now = CarbonImmutable::now(TimeClock::TZ)->startOfDay();
        $req('edson', ['type' => 'vacation', 'title' => 'Férias', 'starts_on' => $now->subDays(5)->toDateString(), 'ends_on' => $now->addDays(9)->toDateString(),
            'status' => 'approved', 'decided_by' => $hr->id, 'decided_at' => $now->subDays(40)->utc(), 'created_at' => $now->subDays(45)->utc()]);
        $req('sergio', ['type' => 'vacation', 'title' => 'Férias', 'starts_on' => $now->addDays(3)->toDateString(), 'ends_on' => $now->addDays(12)->toDateString(),
            'status' => 'approved', 'decided_by' => $hr->id, 'decided_at' => $now->subDays(20)->utc(), 'created_at' => $now->subDays(22)->utc()]);
        // Férias futuras
        $req('helena', ['type' => 'vacation', 'title' => 'Férias de novembro', 'description' => 'Rafael fica como substituto no período.',
            'starts_on' => '2026-11-16', 'ends_on' => '2026-11-30', 'status' => 'approved', 'decided_by' => $this->u['owner']->id, 'decided_at' => $at('2026-10-01 09:00'),
            'created_at' => $at('2026-09-28 10:20')]);
        $req('larissa', ['type' => 'vacation', 'title' => 'Férias em janeiro', 'description' => 'Gostaria de 30 corridos a partir de 05/01.',
            'starts_on' => '2027-01-05', 'ends_on' => '2027-02-03', 'status' => 'pending', 'assigned_to' => $hr->id, 'due_at' => $at('2026-11-14 18:00'),
            'created_at' => $at('2026-11-04 13:22')], [['juliana', 'De acordo pela recepção — Thiago cobre parte das tardes.', $at('2026-11-05 09:10')]]);
        $req('felipe', ['type' => 'vacation', 'title' => 'Férias — dezembro', 'starts_on' => '2026-12-14', 'ends_on' => '2026-12-28', 'status' => 'rejected',
            'decided_by' => $hr->id, 'decided_at' => $at('2026-10-22 15:05'), 'decision_note' => 'Alta temporada (Natal Luz). Podemos remarcar para março?',
            'created_at' => $at('2026-10-20 11:48')], [['felipe', 'Entendi, vou ver março com a Marina.', $at('2026-10-22 18:30')]]);
        $req('aline', ['type' => 'vacation', 'title' => 'Férias em março', 'starts_on' => '2027-03-08', 'ends_on' => '2027-03-27', 'status' => 'pending',
            'assigned_to' => $hr->id, 'created_at' => $at('2026-11-07 10:05')]);

        // Documentos
        $req('maria', ['type' => 'document', 'category' => 'documents', 'title' => 'Declaração de vínculo empregatício', 'description' => 'Para abertura de crediário.',
            'status' => 'approved', 'decided_by' => $dp->id, 'decided_at' => $at('2026-10-15 10:30'), 'decision_note' => 'Enviada em Recebimentos.',
            'assigned_to' => $dp->id, 'first_response_at' => $at('2026-10-15 09:02'), 'created_at' => $at('2026-10-14 16:20')]);
        $req('joao', ['type' => 'document', 'category' => 'documents', 'title' => 'Informe de rendimentos 2025', 'status' => 'approved',
            'decided_by' => $dp->id, 'decided_at' => $at('2026-09-03 11:00'), 'assigned_to' => $dp->id, 'created_at' => $at('2026-09-02 08:41')]);
        $req('wagner', ['type' => 'document', 'category' => 'documents', 'title' => 'Cópia do contrato de trabalho', 'status' => 'pending',
            'assigned_to' => $dp->id, 'due_at' => $at('2026-11-11 18:00'), 'first_response_at' => $at('2026-11-07 14:00'), 'created_at' => $at('2026-11-06 19:12')],
            [['ana', 'Oi Wagner! Vou buscar o contrato no arquivo e te envio até terça.', $at('2026-11-07 14:00')]]);

        // Reembolsos
        $req('sergio', ['type' => 'reimbursement', 'category' => 'benefits', 'title' => 'Reembolso — curso NR-10 reciclagem', 'description' => 'Comprovante anexado ao e-mail do RH.',
            'amount_cents' => 48000, 'status' => 'approved', 'decided_by' => $this->u['carlos']->id, 'decided_at' => $at('2026-10-09 15:00'),
            'decision_note' => 'Pago na folha de outubro.', 'created_at' => $at('2026-10-02 10:00')]);
        $req('priscila', ['type' => 'reimbursement', 'category' => 'benefits', 'title' => 'Quilometragem — visitas a agências em Caxias', 'amount_cents' => 31260,
            'status' => 'pending', 'assigned_to' => $this->u['carlos']->id, 'created_at' => $at('2026-11-05 17:30')]);
        $req('igor', ['type' => 'reimbursement', 'title' => 'Almoço com cliente (evento Unimed)', 'amount_cents' => 18990, 'status' => 'rejected',
            'decided_by' => $this->u['carlos']->id, 'decided_at' => $at('2026-10-28 09:40'), 'decision_note' => 'Sem nota fiscal. Reenvie com a NF, por favor.',
            'created_at' => $at('2026-10-27 13:10')]);

        // Escala / ponto / folha / benefícios
        $req('camila', ['type' => 'schedule', 'category' => 'time', 'title' => 'Troca de folga com o Felipe (15/11)', 'starts_on' => '2026-11-15', 'ends_on' => '2026-11-15',
            'status' => 'pending', 'assigned_to' => $this->u['marcelo']->id, 'created_at' => $at('2026-11-08 21:04')]);
        $req('thiago', ['type' => 'other', 'category' => 'payroll', 'title' => 'Adicional noturno de setembro veio a menor', 'description' => 'Acho que faltaram 3 noites no cálculo.',
            'status' => 'approved', 'decided_by' => $dp->id, 'decided_at' => $at('2026-10-08 16:20'), 'decision_note' => 'Corrigido: diferença paga na folha de outubro.',
            'assigned_to' => $dp->id, 'first_response_at' => $at('2026-10-06 10:15'), 'created_at' => $at('2026-10-05 23:50')],
            [['ana', 'Conferi o espelho: você tem razão, faltaram 3 noites. Lanço a diferença em outubro.', $at('2026-10-06 10:15')]]);
        $req('bruna', ['type' => 'other', 'category' => 'benefits', 'title' => 'Incluir dependente no plano de saúde', 'description' => 'Meu filho nasceu em 28/10.',
            'status' => 'pending', 'assigned_to' => $hr->id, 'due_at' => $at('2026-11-10 18:00'), 'created_at' => $at('2026-11-03 08:30')]);
        $req('edson', ['type' => 'other', 'category' => 'benefits', 'title' => 'Vale-transporte: mudança de endereço', 'status' => 'approved',
            'decided_by' => $this->u['lucas']->id, 'decided_at' => $at('2026-10-20 11:00'), 'assigned_to' => $this->u['lucas']->id, 'created_at' => $at('2026-10-18 07:55')]);
        $req('vanessa', ['type' => 'other', 'category' => 'payroll', 'title' => 'Adiantamento salarial (vale)', 'amount_cents' => 60000, 'status' => 'cancelled',
            'created_at' => $at('2026-10-12 12:12')]);
    }

    // ──────────────────────────────────────────────────────────
    // Ponto: ~3 meses de marcações, ajustes e banco de horas
    // ──────────────────────────────────────────────────────────

    private function timeClock(): void
    {
        $recorder = app(TimeEntryRecorder::class);
        $tz = TimeClock::TZ;
        $now = CarbonImmutable::now($tz);
        $from = CarbonImmutable::parse('2026-08-01', $tz);
        $holidays = ['2026-09-07', '2026-09-20', '2026-10-12', '2026-11-02'];

        $vacations = WorkspaceRequest::where('hotel_id', $this->hotel->id)->where('type', 'vacation')->where('status', 'approved')->get()
            ->groupBy('requester_id');

        // [hora de entrada, minutos de intervalo] por escala
        $shifts = ['adm' => [8 * 60, 72], 'six' => [7 * 60, 60], 'half' => [13 * 60, 0], 'night' => [22 * 60 + 40, 60]];
        $devices = ['Recepção — PC 01', 'Portaria de serviço — Tablet', 'Celular'];

        foreach ($this->spec as $k => $p) {
            $sched = $p[6];
            $schedule = $this->s[$sched];
            $user = $this->u[$k];
            $hired = CarbonImmutable::parse($p[4], $tz);
            $late = in_array($k, ['gabriel', 'camila', 'wagner'], true);      // atrasa com frequência
            $extra = in_array($k, ['fernando', 'patricia', 'rafael', 'igor'], true); // faz hora extra

            for ($day = $from->max($hired); $day->toDateString() <= $now->toDateString(); $day = $day->addDay()) {
                $expected = WorkSchedule::minutesFor($schedule, $day->dayOfWeek);
                if ($expected === 0 || in_array($day->toDateString(), $holidays, true)) {
                    continue;
                }
                $onVacation = ($vacations[$user->id] ?? collect())->contains(fn ($r) => $day->between($r->starts_on->startOfDay(), $r->ends_on->endOfDay()));
                if ($onVacation || mt_rand(1, 100) <= 2) { // ~2% de faltas
                    continue;
                }

                [$startMin, $break] = $shifts[$sched];
                $in = $day->startOfDay()->addMinutes($startMin + mt_rand(-8, $late && mt_rand(1, 3) === 1 ? 35 : 9));
                $over = $extra && mt_rand(1, 3) === 1 ? mt_rand(30, 150) : mt_rand(-10, 18);
                $out = $in->addMinutes($expected + $break + $over);
                $punches = $break > 0
                    ? ['in' => $in, 'break_start' => $in->addMinutes(intdiv($expected, 2) + mt_rand(-20, 20)), 'out' => $out]
                    : ['in' => $in, 'out' => $out];
                if ($break > 0) {
                    $punches = ['in' => $in, 'break_start' => $punches['break_start'], 'break_end' => $punches['break_start']->addMinutes($break + mt_rand(-3, 6)), 'out' => $out];
                }

                $device = $sched === 'adm' || $sched === 'half' ? 'Celular' : $devices[mt_rand(0, 1)];
                foreach ($punches as $type => $at) {
                    if ($at > $now) {
                        break; // dia de hoje: só o que já passou
                    }
                    // esquecimentos: alguém não bateu a saída (gera pedido de ajuste abaixo)
                    if ($type === 'out' && mt_rand(1, 160) === 1) {
                        TimeAdjustment::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $user->id, 'type' => 'out',
                            'requested_at' => $at->utc(), 'reason' => 'Esqueci de bater a saída.', 'status' => $day->month < $now->month ? 'approved' : 'pending',
                            'reviewed_by' => $day->month < $now->month ? $this->u['ana']->id : null, 'reviewed_at' => $day->month < $now->month ? $at->addDays(2)->utc() : null,
                            'created_at' => $at->addDay()->utc(), 'updated_at' => $at->addDay()->utc()]);
                        continue;
                    }
                    $recorder->record([
                        'uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $user->id, 'type' => $type, 'punched_at' => $at->utc(),
                        'source' => $device === 'Celular' ? 'mobile' : 'web', 'device_name' => $device,
                        'device_label' => $device === 'Celular' ? 'Chrome · Android · celular' : 'Chrome · Windows 10/11 · desktop',
                        'network_name' => $device === 'Celular' ? null : 'Hotel — Rede interna', 'ip' => $device === 'Celular' ? '177.' . mt_rand(10, 200) . '.' . mt_rand(1, 250) . '.' . mt_rand(1, 250) : '10.0.1.' . mt_rand(20, 80),
                        'lat' => -29.3790 + mt_rand(-30, 30) / 100000, 'lng' => -50.8740 + mt_rand(-30, 30) / 100000, 'accuracy_m' => mt_rand(8, 40),
                        'created_at' => $at->utc(), 'updated_at' => $at->utc(),
                    ]);
                }
            }
        }

        // Ajustes avulsos
        $adj = [
            ['camila', 'in', '2026-11-04 07:02', 'Celular sem bateria na chegada; entrei às 7h.', 'pending', null],
            ['gabriel', 'break_end', '2026-11-03 13:05', 'O tablet travou na volta do almoço.', 'pending', null],
            ['joao', 'in', '2026-10-21 06:58', 'Sistema fora do ar pela manhã.', 'approved', 'ana'],
            ['wagner', 'out', '2026-10-15 17:30', 'Saí às 17h30 para consulta médica (atestado entregue).', 'approved', 'ana'],
            ['felipe', 'out', '2026-10-30 23:59', 'Fiquei até o fim do evento.', 'rejected', 'ana'],
        ];
        foreach ($adj as [$k, $type, $when, $reason, $status, $rev]) {
            $at = CarbonImmutable::parse($when, $tz)->utc();
            TimeAdjustment::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $this->u[$k]->id, 'type' => $type,
                'requested_at' => $at, 'reason' => $reason, 'status' => $status, 'reviewed_by' => $rev ? $this->u[$rev]->id : null,
                'reviewed_at' => $rev ? $at->addDay() : null, 'review_note' => $status === 'rejected' ? 'O evento terminou às 22h; ajuste para 22h30.' : null]);
        }

        // Banco de horas: folgas compensadas e horas pagas
        foreach ([['fernando', -480, 'Folga compensada em 24/10'], ['patricia', -240, 'Saída antecipada compensada (banco)'],
            ['rafael', -600, 'Pagamento de 10h extras na folha de setembro'], ['igor', 300, 'Crédito: montagem do evento Expogramado (sábado)'],
            ['thiago', 120, 'Crédito: cobertura de turno em 12/10']] as [$k, $min, $reason]) {
            TimeBankAdjustment::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $this->u[$k]->id,
                'minutes' => $min, 'reason' => $reason, 'created_by' => $this->u['ana']->id]);
        }
    }

    private function closings(): void
    {
        $svc = app(TimeClosingService::class);
        foreach (['2026-08' => '2026-09-03 17:20', '2026-09' => '2026-10-02 16:05'] as $month => $at) {
            TimeClosing::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'month' => $month,
                'rows' => $svc->rows($this->hotel, $month), 'closed_by' => $this->u['marina']->id, 'closed_at' => CarbonImmutable::parse($at, TimeClock::TZ)->utc()]);
        }
        // outubro fica aberto para o RH fechar na demonstração
    }

    // ──────────────────────────────────────────────────────────
    // Holerites
    // ──────────────────────────────────────────────────────────

    private function payslips(): void
    {
        $disk = Storage::disk('local');
        $months = ['2026-08', '2026-09', '2026-10'];
        $names = ['01' => 'janeiro', '02' => 'fevereiro', '03' => 'março', '04' => 'abril', '05' => 'maio', '06' => 'junho',
            '07' => 'julho', '08' => 'agosto', '09' => 'setembro', '10' => 'outubro', '11' => 'novembro', '12' => 'dezembro'];

        foreach ($this->spec as $k => [$name, $title, $dept, , $hired, , , $salary]) {
            foreach ($months as $idx => $month) {
                if ($hired > $month . '-28') {
                    continue;
                }
                [$y, $mo] = explode('-', $month);
                $gross = $salary * 100;
                $inss = (int) round(min($gross, 815741) * 0.11);
                $irrf = (int) max(0, round(($gross - $inss) * 0.15 - 38182));
                $net = $gross - $inss - $irrf;
                $path = 'payslips/demo-hr/' . Str::uuid() . '.pdf';
                $pdf = $this->pdf([
                    'GRAND HOTEL SERRA AZUL LTDA - CNPJ 12.345.678/0001-90',
                    'Demonstrativo de Pagamento - ' . ucfirst($names[$mo]) . "/$y",
                    '',
                    "Funcionario: $name",
                    "Cargo: $title   Setor: $dept",
                    '',
                    'Salario base ........................ R$ ' . number_format($gross / 100, 2, ',', '.'),
                    'INSS ................................ R$ -' . number_format($inss / 100, 2, ',', '.'),
                    'IRRF ................................ R$ -' . number_format($irrf / 100, 2, ',', '.'),
                    '',
                    'Liquido a receber ................... R$ ' . number_format($net / 100, 2, ',', '.'),
                    '',
                    'Documento ficticio gerado para demonstracao.',
                ]);
                $disk->put($path, $pdf);
                $created = CarbonImmutable::parse("$month-01", TimeClock::TZ)->addMonth()->addDays(4)->setTime(10, 0)->utc();
                Payslip::create([
                    'uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $this->u[$k]->id, 'uploaded_by' => $this->u['ana']->id,
                    'kind' => 'payslip', 'title' => 'Holerite ' . $names[$mo] . "/$y", 'reference_month' => "$month-01", 'amount_cents' => $net,
                    'file_path' => $path, 'original_name' => "holerite-$month.pdf", 'mime' => 'application/pdf', 'size' => strlen($pdf),
                    'first_viewed_at' => $idx < 2 || mt_rand(0, 1) ? $created->addDays(mt_rand(0, 3))->addHours(mt_rand(1, 10)) : null,
                    'created_at' => $created, 'updated_at' => $created,
                ]);
            }
        }

        // Extras: PLR, recibo de férias e reembolso
        foreach ([['sergio', 'receipt', 'Reembolso curso NR-10', '2026-10', 48000], ['gabriel', 'receipt', 'Recibo de férias', '2026-08', 412500],
            ['jessica', 'receipt', 'Recibo de férias', '2026-09', 289000], ['fernando', 'bonus', 'Bônus — meta de CMV do trimestre', '2026-09', 150000]] as [$k, $kind, $title, $month, $cents]) {
            $path = 'payslips/demo-hr/' . Str::uuid() . '.pdf';
            $pdf = $this->pdf(['GRAND HOTEL SERRA AZUL LTDA', $title, $this->spec[$k][0], 'Valor: R$ ' . number_format($cents / 100, 2, ',', '.'), '', 'Documento ficticio gerado para demonstracao.']);
            $disk->put($path, $pdf);
            Payslip::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $this->u[$k]->id, 'uploaded_by' => $this->u['ana']->id,
                'kind' => $kind, 'title' => $title, 'reference_month' => "$month-01", 'amount_cents' => $cents, 'file_path' => $path,
                'original_name' => Str::slug($title) . '.pdf', 'mime' => 'application/pdf', 'size' => strlen($pdf)]);
        }
    }

    /** PDF mínimo (uma página, Helvetica) com as linhas informadas. */
    private function pdf(array $lines): string
    {
        $text = "BT /F1 11 Tf 50 790 Td 15 TL\n";
        foreach ($lines as $line) {
            $text .= '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], Str::ascii($line)) . ") '\n";
        }
        $text .= 'ET';
        $objs = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length ' . strlen($text) . " >>\nstream\n$text\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>',
        ];
        $out = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $n => $body) {
            $offsets[] = strlen($out);
            $out .= ($n + 1) . " 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($out);
        $out .= 'xref' . "\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $out .= sprintf("%010d 00000 n \n", $o);
        }

        return $out . 'trailer << /Size ' . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }

    // ──────────────────────────────────────────────────────────
    // Metas e PDI
    // ──────────────────────────────────────────────────────────

    private function goals(): void
    {
        $q4 = ['period_start' => '2026-10-01', 'period_end' => '2026-12-31'];
        $q3 = ['period_start' => '2026-07-01', 'period_end' => '2026-09-30'];
        $goals = [
            ['juliana', 'rafael', 'Nota de check-in no Booking', 'Média das avaliações do item "check-in".', 'nota', 9.2, [8.6, 8.8, 8.9], $q4, 'active'],
            ['gabriel', 'juliana', 'Upsell de categoria no balcão', null, 'upgrades', 40, [6, 14, 19], $q4, 'active'],
            ['larissa', 'juliana', 'Upsell de categoria no balcão', null, 'upgrades', 40, [9, 18, 27], $q4, 'active'],
            ['patricia', 'rafael', 'Tempo médio de arrumação por UH', 'Reduzir sem perder qualidade na inspeção.', 'min', 32, [38, 36, 35], $q4, 'active'],
            ['rosangela', 'patricia', 'Quartos aprovados na 1ª inspeção', null, '%', 95, [88, 91, 96], $q3, 'achieved'],
            ['fernando', 'renato', 'CMV do restaurante', 'Custo da mercadoria vendida sobre a receita de A&B.', '%', 28, [33, 31, 29.5], $q3, 'missed'],
            ['fernando', 'renato', 'CMV do restaurante', null, '%', 29, [30.2, 29.6], $q4, 'active'],
            ['marcelo', 'renato', 'Ticket médio do jantar', null, 'R$', 185, [162, 171, 176], $q4, 'active'],
            ['priscila', 'daniela', 'Receita de grupos e eventos', null, 'R$ mil', 450, [120, 260, 318], $q4, 'active'],
            ['igor', 'daniela', 'Eventos realizados sem não conformidade', null, 'eventos', 12, [3, 7, 12], $q3, 'achieved'],
            ['roberto', 'helena', 'Preventivas executadas no prazo', null, '%', 100, [82, 90, 94], $q4, 'active'],
            ['beatriz', 'carlos', 'Conciliações bancárias em dia', null, 'contas', 8, [5, 7, 8], $q3, 'achieved'],
            ['marina', 'owner', 'Turnover anual abaixo de', 'Rotatividade acumulada nos últimos 12 meses.', '%', 18, [24, 22, 21], $q4, 'active'],
            ['ana', 'marina', 'Admissões concluídas em até 5 dias úteis', null, '%', 100, [80, 100], $q4, 'active'],
            ['camila', 'marcelo', 'Vendas de vinho por mesa', null, 'garrafas', 90, [20, 41], $q4, 'cancelled'],
        ];
        foreach ($goals as [$owner, $setBy, $title, $desc, $unit, $target, $updates, $period, $status]) {
            $g = Goal::create($period + ['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'owner_id' => $this->u[$owner]->id,
                'set_by' => $this->u[$setBy]->id, 'title' => $title, 'description' => $desc, 'unit' => $unit, 'target_value' => $target,
                'current_value' => end($updates), 'status' => $status, 'created_at' => CarbonImmutable::parse($period['period_start'] . ' 09:00', TimeClock::TZ)->utc()]);
            $start = CarbonImmutable::parse($period['period_start'], TimeClock::TZ);
            foreach ($updates as $i => $v) {
                $at = $start->addWeeks(3 * ($i + 1))->setTime(17, 0)->utc();
                $g->updates()->create(['user_id' => $this->u[$owner]->id, 'value' => $v, 'note' => $i === 0 ? 'Primeira medição.' : null,
                    'created_at' => $at->min(now()), 'updated_at' => $at->min(now())]);
            }
        }
    }

    private function development(): void
    {
        $plans = [
            ['juliana', 'rafael', 'Preparação para Gerente de Hospedagem', 'Assumir a gestão da hospedagem em até 18 meses: indicadores, orçamento e liderança.', 'active',
                [['Curso de Revenue Management (Senac)', 'course', '2026-12-15', 'doing'], ['Acompanhar o fechamento de orçamento com o Rafael', 'mentoring', '2027-02-28', 'todo'],
                 ['Projeto: novo fluxo de check-in expresso', 'project', '2026-11-30', 'doing'], ['Livro: Hospitalidade Irracional (Will Guidara)', 'reading', '2026-10-31', 'done']],
                ['Juliana já conduz a reunião diária da recepção. Próximo passo: participar do comitê de orçamento.']],
            ['aline', 'fernando', 'Sous Chef → Chef Executiva', 'Ganhar autonomia em ficha técnica, custos e gestão de brigada.', 'active',
                [['Formação em gestão de custos de A&B', 'course', '2027-01-31', 'todo'], ['Montar o cardápio de inverno com o Chef', 'project', '2027-04-30', 'todo'],
                 ['Boas Práticas e APPCC — atualização', 'course', '2026-09-30', 'done']], ['Boa evolução em ficha técnica; precisa delegar mais na praça quente.']],
            ['gabriel', 'juliana', 'Recepcionista bilíngue', 'Atingir inglês intermediário para atender grupos internacionais.', 'active',
                [['Inglês para hotelaria (online, 6 meses)', 'course', '2027-03-31', 'doing'], ['Atender 2 check-ins por semana em inglês com apoio da Larissa', 'mentoring', '2026-12-31', 'doing']],
                ['Combinado de feedback quinzenal. Pontualidade precisa melhorar (atrasos em outubro).']],
            ['beatriz', 'carlos', 'Analista Financeiro Pleno', null, 'completed',
                [['Excel avançado', 'course', '2026-06-30', 'done'], ['Assumir o contas a pagar', 'project', '2026-08-31', 'done']], ['Plano concluído — promoção avaliada para janeiro.']],
            ['lucas', 'marina', 'Formação em Departamento Pessoal', 'Dominar admissão, férias e eSocial.', 'active',
                [['Curso eSocial na prática', 'course', '2026-12-10', 'doing'], ['Fazer 5 admissões com a Ana como revisora', 'mentoring', '2026-11-30', 'doing'],
                 ['Ler a CLT comentada — capítulo de férias', 'reading', '2026-11-20', 'todo']], []],
        ];
        foreach ($plans as [$who, $by, $title, $objective, $status, $items, $notes]) {
            $plan = DevelopmentPlan::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'user_id' => $this->u[$who]->id,
                'created_by' => $this->u[$by]->id, 'title' => $title, 'objective' => $objective, 'status' => $status,
                'period_start' => '2026-07-01', 'period_end' => '2027-06-30']);
            foreach ($items as [$t, $type, $due, $st]) {
                $plan->items()->create(['uuid' => (string) Str::uuid(), 'title' => $t, 'type' => $type, 'due_on' => $due, 'status' => $st]);
            }
            foreach ($notes as $body) {
                $plan->notes()->create(['author_id' => $this->u[$by]->id, 'body' => $body]);
            }
        }
    }

    // ──────────────────────────────────────────────────────────
    // Calendário, clima, vagas e comunicados
    // ──────────────────────────────────────────────────────────

    private function calendar(): void
    {
        // feriados nacionais já vêm do CalendarService; aqui só o que é da empresa
        $events = [
            ['Aniversário de Gramado (feriado municipal)', 'holiday', '2026-12-15', null, null, null, null, null],
            ['Integração de novos colaboradores', 'event', '2026-11-12', '09:00', '12:00', 'Sala Araucária', null, 'Apresentação da empresa, políticas, segurança e tour pelo hotel.'],
            ['Treinamento NR-10 (reciclagem)', 'event', '2026-11-18', '08:00', '17:00', 'Centro de eventos', 'Manutenção', null],
            ['Treinamento de Boas Práticas na cozinha', 'event', '2026-11-24', '14:00', '16:00', 'Cozinha central', 'Alimentos & Bebidas', 'Obrigatório para toda a brigada.'],
            ['Reunião de abertura do Natal Luz', 'event', '2026-11-26', '15:00', '16:30', 'Sala Araucária', null, 'Escalas, ocupação prevista e eventos de dezembro.'],
            ['Festa de fim de ano da equipe', 'event', '2026-12-19', '20:00', '23:59', 'Restaurante Hortênsia', null, 'Amigo secreto, premiação "Destaques do ano" e jantar.'],
            ['SIPAT 2026', 'event', '2026-10-20', '09:00', '11:00', 'Centro de eventos', null, 'Semana de prevenção de acidentes.'],
        ];
        foreach ($events as [$title, $kind, $date, $st, $et, $loc, $dept, $desc]) {
            $e = CalendarEvent::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'title' => $title, 'kind' => $kind,
                'starts_on' => $date, 'starts_time' => $st, 'ends_time' => $et, 'location' => $loc, 'description' => $desc,
                'department_id' => $dept ? $this->d[$dept]->id : null, 'created_by' => $this->u['marina']->id]);
            if ($kind === 'event') {
                foreach (array_rand($this->u, 14) as $k) {
                    DB::table('calendar_event_rsvps')->insert(['calendar_event_id' => $e->id, 'user_id' => $this->u[$k]->id,
                        'status' => ['going', 'going', 'going', 'maybe', 'no'][mt_rand(0, 4)], 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    private function climate(): void
    {
        $comments = [
            'Gosto muito da equipe, mas a escala de fim de semana pesa.', 'Falta comunicação entre recepção e governança no horário de check-out.',
            'O refeitório melhorou bastante este ano!', 'Gostaria de mais treinamentos e plano de carreira claro.', 'Meu gestor é muito parceiro.',
            'Salário abaixo do mercado para a função.', 'Ambiente muito bom, me sinto valorizada.', 'Precisamos de mais gente na alta temporada.',
        ];
        $surveys = [
            ['Pesquisa de clima — 1º semestre 2026', 'closed', '2026-06-30', 29, 7],
            ['Pulso de clima — novembro', 'open', '2026-11-20', 11, 7.6],
        ];
        $keys = array_keys($this->u);
        foreach ($surveys as [$title, $status, $closes, $n, $mood]) {
            $id = DB::table('climate_surveys')->insertGetId(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'created_by' => $this->u['marina']->id,
                'title' => $title, 'status' => $status, 'closes_on' => $closes, 'created_at' => CarbonImmutable::parse($closes)->subDays(15), 'updated_at' => now()]);
            shuffle($keys);
            foreach (array_slice($keys, 0, $n) as $k) {
                DB::table('climate_participations')->insert(['climate_survey_id' => $id, 'user_id' => $this->u[$k]->id,
                    'answered_on' => CarbonImmutable::parse($closes)->subDays(mt_rand(1, 14))->min(now())->toDateString()]);
                $nps = max(0, min(10, (int) round($mood + mt_rand(-35, 25) / 10)));
                $scale = fn () => max(1, min(5, (int) round($mood / 2 + mt_rand(-15, 12) / 10)));
                DB::table('climate_answers')->insert(['climate_survey_id' => $id, 'department_id' => $this->m[$k]->department_id,
                    'scores' => json_encode(['nps' => $nps, 'recognition' => $scale(), 'clarity' => $scale(), 'manager' => $scale()]),
                    'comment' => mt_rand(1, 3) === 1 ? $comments[array_rand($comments)] : null]);
            }
        }
    }

    private function jobs(): void
    {
        $jobs = [
            ['Recepcionista (escala 6x1)', 'Recepção', 'Atendimento no check-in/out, reservas e concierge.', 'Ensino médio completo; inglês básico é diferencial.', 'CLT', 'open', true, '2026-11-30',
                [['lucas', 'Tenho experiência de 1 ano em recepção de pousada.', 'reviewing']]],
            ['Camareira(o)', 'Governança', 'Arrumação de apartamentos e áreas sociais.', 'Experiência desejável.', 'CLT', 'open', true, '2026-11-25', []],
            ['Cozinheiro(a) — temporada Natal Luz', 'Alimentos & Bebidas', 'Reforço da brigada de 01/12 a 31/01.', 'Experiência em cozinha de hotel ou restaurante.', 'Temporário', 'open', true, '2026-11-20',
                [['bruna', 'Gostaria de ser promovida a cozinheira, já cubro a praça fria.', 'new']]],
            ['Supervisor(a) de Recepção — noturno', 'Recepção', 'Liderar o turno da noite e a auditoria noturna.', 'Experiência em liderança e no PMS.', 'CLT', 'closed', false, '2026-10-15',
                [['thiago', 'Faço a auditoria noturna há 3 anos.', 'hired'], ['gabriel', null, 'rejected']]],
        ];
        foreach ($jobs as [$title, $dept, $desc, $reqs, $contract, $status, $public, $closes, $apps]) {
            $j = WorkspaceJob::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'department_id' => $this->d[$dept]->id,
                'created_by' => $this->u['marina']->id, 'title' => $title, 'description' => $desc, 'requirements' => $reqs, 'location' => 'Gramado/RS',
                'contract_type' => $contract, 'contact' => 'rh@' . self::DOMAIN, 'status' => $status, 'public' => $public,
                'share_token' => Str::random(40), 'closes_on' => $closes]);
            foreach ($apps as [$k, $msg, $st]) {
                DB::table('workspace_job_applications')->insert(['uuid' => (string) Str::uuid(), 'workspace_job_id' => $j->id, 'user_id' => $this->u[$k]->id,
                    'message' => $msg, 'status' => $st, 'created_at' => now()->subDays(mt_rand(2, 20)), 'updated_at' => now()]);
            }
        }
    }

    private function feed(): void
    {
        $posts = [
            ['owner', true, 'Bem-vindos ao nosso espaço interno! Aqui ficam comunicados, ponto, holerites e solicitações. Qualquer dúvida, falem com o RH.', '2026-08-01 09:00'],
            ['marina', false, 'Holerites de outubro já estão disponíveis em Recebimentos. Confiram e, se algo estiver diferente, abram um chamado na aba Solicitações.', '2026-11-05 10:05'],
            ['marina', false, 'Pesquisa de clima de novembro no ar! São 4 perguntas, 100% anônimas. Prazo: 20/11.', '2026-11-06 08:30'],
            ['rafael', false, 'Parabéns ao time da recepção pela nota 9,1 no Booking em outubro — melhor resultado do ano! 👏', '2026-11-03 18:20'],
            ['ana', false, 'Lembrete: pedidos de férias para janeiro e fevereiro até 15/11, por causa da alta temporada.', '2026-10-28 14:00'],
            ['marina', false, 'Damos as boas-vindas à Letícia Prado, nossa nova estagiária de Marketing no Comercial & Eventos!', $this->spec['leticia'][4] . ' 11:15'],
        ];
        foreach ($posts as [$k, $pinned, $body, $when]) {
            $at = CarbonImmutable::parse($when, TimeClock::TZ)->utc();
            WorkspacePost::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $this->hotel->id, 'author_id' => $this->u[$k]->id,
                'body' => $body, 'pinned' => $pinned, 'created_at' => $at, 'updated_at' => $at]);
        }
    }

    private function credentials(): void
    {
        $lines = ['Grand Hotel Serra Azul — usuários de demonstração (senha dos funcionários: ' . self::PASSWORD . ')',
            '- ' . $this->u['owner']->email . ' — dono da empresa / admin da plataforma (senha definida em DEMO_ADMIN_PASSWORD)'];
        foreach ($this->spec as $k => [$name, $title, , , , , , , $access]) {
            if ($k !== 'owner') {
                $lines[] = "- {$this->u[$k]->email} — $name ($title)" . ($access ? " [acesso: $access]" : '');
            }
        }
        file_put_contents(storage_path('app/hr-demo-credentials.txt'), implode("\n", $lines) . "\n");
    }
}
