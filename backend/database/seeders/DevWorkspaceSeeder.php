<?php

namespace Database\Seeders;

use App\Models\ChatGroup;
use App\Models\DevelopmentPlan;
use App\Models\Goal;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use App\Models\WorkspacePost;
use App\Models\WorkspaceRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Dados de DEMONSTRAÇÃO do Workspace para o ambiente local (equipe fictícia na empresa "Inovação").
 * Idempotente. Rodado automaticamente pelo DevBootstrap; nunca use em produção.
 * Senha de todos os usuários fictícios: ver storage/app/dev-seed-credentials.txt.
 */
class DevWorkspaceSeeder extends Seeder
{
    /** Aumente para o DevBootstrap rodar o seeder de novo. */
    public const VERSION = '2';

    private const PASSWORD = 'Teste@12345';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Seeder de demonstração não pode rodar em produção.');
        }

        $owner = User::where('email', 'eric@inovacao.local')->first();
        $hotel = $owner
            ? Hotel::whereHas('hotelUsers', fn ($q) => $q->where('user_id', $owner->id))->where('name', 'Inovação')->first()
            : null;

        if (! $owner || ! $hotel) {
            return; // empresa de demonstração ainda não existe
        }

        $dept = fn (string $name) => $hotel->departments()->firstOrCreate(['name' => $name], ['uuid' => (string) Str::uuid()]);

        // email => [nome, cargo, setor, email do gestor, admissão, role]
        $people = [
            'marina@inovacao.local'  => ['Marina Costa',   'Gerente de RH',            'Recursos Humanos', 'eric@inovacao.local',    '2022-03-14', 'admin'],
            'rafael@inovacao.local'  => ['Rafael Souza',   'Gerente de Operações',     'Operações',        'eric@inovacao.local',    '2021-08-02', 'employee'],
            'carlos@inovacao.local'  => ['Carlos Mendes',  'Coordenador Financeiro',   'Financeiro',       'eric@inovacao.local',    '2020-11-23', 'employee'],
            'beatriz@inovacao.local' => ['Beatriz Lima',   'Analista Financeiro',      'Financeiro',       'carlos@inovacao.local',  '2023-05-08', 'employee'],
            'juliana@inovacao.local' => ['Juliana Alves',  'Recepcionista',            'Operações',        'rafael@inovacao.local',  '2024-01-15', 'employee'],
            'pedro@inovacao.local'   => ['Pedro Santos',   'Auxiliar de Operações',    'Operações',        'rafael@inovacao.local',  '2024-06-03', 'employee'],
            'ana@inovacao.local'     => ['Ana Ribeiro',    'Analista de RH',           'Recursos Humanos', 'marina@inovacao.local',  '2023-09-18', 'employee'],
        ];

        $users = ['eric@inovacao.local' => $owner];

        foreach ($people as $email => [$name]) {
            $user = User::where('email', $email)->first() ?? new User([
                'uuid' => (string) Str::uuid(), 'name' => $name, 'email' => $email, 'password' => self::PASSWORD,
            ]);
            if (! $user->exists) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            $users[$email] = $user;
        }

        // Dono: cargo e setor
        HotelUser::where('hotel_id', $hotel->id)->where('user_id', $owner->id)->update([
            'job_title' => 'Diretor Geral', 'department_id' => $dept('Administração')->id, 'hired_at' => '2019-01-07',
        ]);

        foreach ($people as $email => [$name, $title, $deptName, $managerEmail, $hiredAt, $role]) {
            HotelUser::updateOrCreate(
                ['hotel_id' => $hotel->id, 'user_id' => $users[$email]->id],
                [
                    'role' => $role, 'status' => 'approved', 'job_title' => $title, 'hired_at' => $hiredAt,
                    'department_id' => $dept($deptName)->id, 'manager_id' => $users[$managerEmail]->id,
                ],
            );
        }

        // Grupo de chat "Operações" (modo normal) e "Comunicados" (modo aviso)
        if (! ChatGroup::where('hotel_id', $hotel->id)->where('name', 'Operações')->exists()) {
            $g = ChatGroup::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'created_by' => $owner->id,
                'name' => 'Operações', 'description' => 'Equipe de operações do dia a dia', 'only_admins_can_post' => false]);
            $g->members()->create(['user_id' => $owner->id, 'role' => 'admin', 'last_read_at' => now()]);
            foreach (['rafael', 'juliana', 'pedro'] as $k) {
                $g->members()->create(['user_id' => $users["$k@inovacao.local"]->id, 'role' => $k === 'rafael' ? 'admin' : 'member']);
            }
            $g->messages()->create(['uuid' => (string) Str::uuid(), 'sender_id' => $users['rafael@inovacao.local']->id,
                'content' => 'Bom dia, time! Escala da semana já está no quadro.', 'type' => 'text']);
        }

        // Feed interno
        if (! WorkspacePost::where('hotel_id', $hotel->id)->exists()) {
            WorkspacePost::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'author_id' => $owner->id, 'pinned' => true,
                'body' => "Bem-vindos ao nosso espaço interno! Aqui vamos compartilhar novidades, metas e reconhecimentos. Qualquer dúvida, falem com o RH."]);
            WorkspacePost::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'author_id' => $users['marina@inovacao.local']->id,
                'body' => "Lembrete: o prazo para solicitar férias do primeiro semestre vai até o dia 30. Use a aba Solicitações."]);
        }

        // Solicitações
        if (! WorkspaceRequest::where('hotel_id', $hotel->id)->exists()) {
            WorkspaceRequest::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'requester_id' => $users['juliana@inovacao.local']->id,
                'type' => 'vacation', 'title' => 'Férias de janeiro', 'description' => 'Viagem em família.',
                'starts_on' => now()->addDays(60)->toDateString(), 'ends_on' => now()->addDays(74)->toDateString(), 'status' => 'pending']);
            WorkspaceRequest::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'requester_id' => $users['pedro@inovacao.local']->id,
                'type' => 'document', 'title' => 'Declaração de vínculo', 'description' => 'Para financiamento.', 'status' => 'approved',
                'decided_by' => $users['marina@inovacao.local']->id, 'decided_at' => now()->subDay(), 'decision_note' => 'Enviada por e-mail.']);
        }

        // Metas
        if (! Goal::where('hotel_id', $hotel->id)->exists()) {
            $period = ['period_start' => now()->startOfMonth()->toDateString(), 'period_end' => now()->endOfMonth()->toDateString()];
            Goal::create($period + ['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'owner_id' => $users['juliana@inovacao.local']->id,
                'set_by' => $users['rafael@inovacao.local']->id, 'title' => 'Satisfação do hóspede no check-in', 'unit' => '%',
                'target_value' => 95, 'current_value' => 88, 'status' => 'active']);
            Goal::create($period + ['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'owner_id' => $users['pedro@inovacao.local']->id,
                'set_by' => $users['rafael@inovacao.local']->id, 'title' => 'Quartos conferidos sem retrabalho', 'unit' => 'quartos',
                'target_value' => 120, 'current_value' => 120, 'status' => 'achieved']);
            Goal::create($period + ['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'owner_id' => $users['beatriz@inovacao.local']->id,
                'set_by' => $users['carlos@inovacao.local']->id, 'title' => 'Conciliações bancárias fechadas', 'unit' => 'conciliações',
                'target_value' => 30, 'current_value' => 12, 'status' => 'active']);
        }

        // PDI
        if (! DevelopmentPlan::where('hotel_id', $hotel->id)->exists()) {
            $plan = DevelopmentPlan::create(['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'user_id' => $users['juliana@inovacao.local']->id,
                'created_by' => $users['rafael@inovacao.local']->id, 'title' => 'Crescer para Líder de Recepção',
                'objective' => 'Desenvolver liderança e domínio do sistema de reservas em 12 meses.',
                'period_start' => now()->toDateString(), 'period_end' => now()->addYear()->toDateString()]);
            $plan->items()->create(['uuid' => (string) Str::uuid(), 'title' => 'Curso de liderança de equipes', 'type' => 'course',
                'due_on' => now()->addMonths(3)->toDateString(), 'status' => 'doing']);
            $plan->items()->create(['uuid' => (string) Str::uuid(), 'title' => 'Mentoria com o gerente de operações', 'type' => 'mentoring',
                'due_on' => now()->addMonths(6)->toDateString(), 'status' => 'todo']);
            $plan->notes()->create(['author_id' => $users['rafael@inovacao.local']->id, 'body' => 'Conversa inicial: ótimo desempenho no atendimento; foco agora em gestão de conflitos.']);
        }

        $this->call(CareerDemoSeeder::class);

        $lines = ["Usuários de demonstração (senha de todos: " . self::PASSWORD . ")"];
        foreach ($people as $email => [$name, $title]) {
            $lines[] = "- $email — $name ($title)";
        }
        file_put_contents(storage_path('app/dev-seed-credentials.txt'), implode("\n", $lines) . "\n");
    }
}
