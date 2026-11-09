<?php

namespace Database\Seeders;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Comment;
use App\Models\Connection;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\Experience;
use App\Models\Hotel;
use App\Models\HotelFollower;
use App\Models\HotelUser;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\Like;
use App\Models\Mentorship;
use App\Models\MentorshipBooking;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Review;
use App\Models\Story;
use App\Models\StoryLike;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserFollower;
use App\Models\Video;
use App\Models\VideoComment;
use App\Models\VideoLike;
use Database\Seeders\Demo\DemoCatalog;
use Database\Seeders\Demo\DemoContent;
use Database\Seeders\Demo\DemoMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Cenário completo de demonstração do Bitz Connect: 12 hotéis, 30
 * profissionais (10 consultores PRO), vagas, candidaturas, feed, stories,
 * vídeos, cursos, mentorias, avaliações e conversas — tudo com conteúdo de
 * hotelaria escrito à mão e fotos/vídeos reais do tema.
 *
 * Todos os usuários criados aqui usam o domínio @demo.connecthotel.com.br e a
 * senha "password".
 *
 * Rodar com: php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    private const PASSWORD = 'password';
    private const EMAIL_DOMAIN = 'demo.connecthotel.com.br';

    private DemoMedia $media;

    /** @var array<int, Hotel> */
    private array $hotels = [];

    /** @var array<int, array<string, mixed>> catálogo bruto dos hotéis */
    private array $hotelSpecs = [];

    /** @var array<int, User> */
    private array $users = [];

    /** @var array<int, array<string, mixed>> catálogo bruto das pessoas, mesmo índice de $users */
    private array $personSpecs = [];

    /** @var array<int, User> consultores com plano PRO */
    private array $creators = [];

    /** @var array<int, Job> */
    private array $jobs = [];

    /** @var array<int, Mentorship> */
    private array $mentorships = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Seeder de demonstração não pode rodar em produção.');
        }

        $this->media = new DemoMedia();

        if (! $this->media->ffmpegAvailable()) {
            $this->command?->warn('ffmpeg não encontrado: os vídeos usarão apenas thumbnail (sem arquivo de vídeo gerado).');
        }

        DB::transaction(function () {
            $this->step('Hotéis', fn () => $this->createHotels());
            $this->step('Profissionais', fn () => $this->createUsers());
            $this->step('Experiências e vínculos', fn () => $this->createExperiences());
            $this->step('Conexões e seguidores', fn () => $this->createNetwork());
            $this->step('Vagas e candidaturas', fn () => $this->createJobs());
            $this->step('Feed (posts, curtidas e comentários)', fn () => $this->createFeed());
            $this->step('Cursos', fn () => $this->createCourses());
            $this->step('Mentorias', fn () => $this->createMentorships());
            $this->step('Avaliações', fn () => $this->createReviews());
            $this->step('Stories', fn () => $this->createStories());
            $this->step('Conversas', fn () => $this->createConversations());
            $this->step('Notificações', fn () => $this->createNotifications());
        });

        // Vídeos ficam fora da transação: envolvem download + encode e podem
        // demorar minutos; não faz sentido segurar a transação nesse tempo.
        $this->step('Vídeos dos criadores PRO', fn () => $this->createVideos());

        $this->summary();
    }

    // ──────────────────────────────────────────────────────────
    // Infra
    // ──────────────────────────────────────────────────────────

    private function step(string $label, callable $fn): void
    {
        $this->command?->info("→ {$label}...");
        $fn();
    }

    /** @var array<string, array<int, string>> cache de colunas por tabela */
    private array $tableColumns = [];

    /**
     * Ajusta created_at/updated_at preservando o valor informado. Ignora
     * tabelas pivô sem timestamps e nunca grava data no futuro.
     */
    private function backdate(Model $model, Carbon $when): void
    {
        if ($when->isFuture()) {
            $when = now();
        }

        $table = $model->getTable();
        $this->tableColumns[$table] ??= DB::getSchemaBuilder()->getColumnListing($table);

        $values = [];
        foreach (['created_at', 'updated_at'] as $column) {
            if (in_array($column, $this->tableColumns[$table], true)) {
                $values[$column] = $when;
            }
        }

        if ($values === []) {
            return;
        }

        $model->forceFill($values)->save();
    }

    /** Data aleatória entre $from e agora (ou $to). */
    private function between(Carbon $from, ?Carbon $to = null): Carbon
    {
        $to ??= now();
        if ($from->greaterThanOrEqualTo($to)) {
            return $from->copy();
        }

        return $from->copy()->addSeconds(rand(0, $from->diffInSeconds($to)));
    }

    private function daysAgo(int $min, int $max): Carbon
    {
        return now()->subDays(rand($min, $max))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
    }

    /** @param array<int, mixed> $items */
    private function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    private function uuid(): string
    {
        return (string) Str::uuid();
    }

    // ──────────────────────────────────────────────────────────
    // Hotéis
    // ──────────────────────────────────────────────────────────

    private function createHotels(): void
    {
        $this->hotelSpecs = DemoCatalog::hotels();

        foreach ($this->hotelSpecs as $spec) {
            $slug = Str::slug($spec['name']);

            $hotel = Hotel::create([
                'uuid'          => $this->uuid(),
                'name'          => $spec['name'],
                'segment'       => $spec['segment'],
                'slug'          => $slug,
                'description'   => $spec['description'],
                'logo_path'     => $this->media->store($this->media->logoUrl($spec['name']), 'hotels/logos', 'png'),
                'cover_path'    => $this->media->storeFrom($spec['media'], 'hotels/covers', 1600, 600),
                'city'          => $spec['city'],
                'state'         => $spec['state'],
                'country'       => 'Brasil',
                'website'       => 'https://www.' . $slug . '.com.br',
                'phone'         => '+55' . $this->areaCode($spec['state']) . rand(30000000, 39999999),
                'contact_email' => 'contato@' . $slug . '.com.br',
                'cnpj'          => $this->cnpj(),
            ]);

            // Hotel antigo na plataforma: entrou entre 1 e 3 anos atrás.
            $this->backdate($hotel, now()->subMonths(rand(14, 36)));

            if ($spec['pro']) {
                Subscription::create([
                    'uuid'              => $this->uuid(),
                    'subscribable_type' => Hotel::class,
                    'subscribable_id'   => $hotel->id,
                    'plan'              => SubscriptionPlan::ProEmpresa,
                    'status'            => SubscriptionStatus::Active,
                    'started_at'        => now()->subMonths(rand(3, 18)),
                    'expires_at'        => null,
                    'provider'          => 'demo',
                ]);
            }

            $hotel->forceFill(['profile_views' => rand(800, 14000)])->save();

            $this->hotels[] = $hotel;
        }
    }

    private function areaCode(string $state): string
    {
        return match ($state) {
            'SP' => '11', 'RJ' => '21', 'SC' => '48', 'RS' => '54', 'CE' => '88',
            'PR' => '41', 'AM' => '92', 'MG' => '31', 'PE' => '81', 'BA' => '73',
            default => '11',
        };
    }

    private function cnpj(): string
    {
        return sprintf('%02d.%03d.%03d/0001-%02d', rand(10, 99), rand(100, 999), rand(100, 999), rand(10, 99));
    }

    // ──────────────────────────────────────────────────────────
    // Profissionais
    // ──────────────────────────────────────────────────────────

    private function createUsers(): void
    {
        $this->personSpecs = DemoCatalog::people();

        foreach ($this->personSpecs as $i => $spec) {
            $hotel = $spec['hotel'] !== null ? $this->hotels[$spec['hotel']] : null;
            $slug = Str::slug($spec['name']);

            $user = User::create([
                'uuid'              => $this->uuid(),
                'name'              => $spec['name'],
                'email'             => $slug . '@' . self::EMAIL_DOMAIN,
                'password'          => Hash::make(self::PASSWORD),
                'avatar_path'       => $this->media->store($this->media->avatarUrl($i), 'avatars'),
                'banner_path'       => $this->media->storeFrom(
                    $spec['creator'] ? 'team' : $this->hotelSpecs[$spec['hotel']]['media'],
                    'banners',
                    1600,
                    500
                ),
                'bio'               => $spec['bio'],
                'specialty'         => $spec['specialty'],
                'city'              => $spec['city'] ?? $hotel?->city,
                'state'             => $spec['state'] ?? $hotel?->state,
                'website'           => $spec['creator'] ? 'https://www.' . $slug . '.com.br' : null,
                'role'              => 'user',
                'email_verified_at' => now(),
            ]);

            // Conta antiga: entrou na rede entre 6 e 30 meses atrás.
            $this->backdate($user, now()->subMonths(rand(6, 30)));

            $this->users[] = $user;

            if ($spec['creator']) {
                $this->creators[] = $user;

                Subscription::create([
                    'uuid'              => $this->uuid(),
                    'subscribable_type' => User::class,
                    'subscribable_id'   => $user->id,
                    'plan'              => SubscriptionPlan::ProConsultor,
                    'status'            => SubscriptionStatus::Active,
                    'started_at'        => now()->subMonths(rand(2, 20)),
                    'expires_at'        => null,
                    'provider'          => 'demo',
                ]);
            }
        }
    }

    /** Índice do usuário -> spec do catálogo. */
    private function specOf(int $index): array
    {
        return $this->personSpecs[$index];
    }

    // ──────────────────────────────────────────────────────────
    // Experiências e vínculos com hotéis
    // ──────────────────────────────────────────────────────────

    private function createExperiences(): void
    {
        $previousEmployers = [
            'Blue Tree Towers', 'Rede Nacional de Hotéis', 'Bourbon Hotéis & Resorts',
            'Transamérica Hospitality', 'Hotel Central Plaza', 'Pousada Recanto do Sol',
        ];
        $juniorRoles = ['Mensageiro', 'Recepcionista', 'Auxiliar de Cozinha', 'Camareira', 'Auxiliar Administrativo'];

        foreach ($this->users as $i => $user) {
            $spec = $this->specOf($i);
            $seniority = $spec['seniority'];

            if ($spec['hotel'] !== null) {
                $hotel = $this->hotels[$spec['hotel']];
                $isAdmin = ($spec['admin'] ?? false) === true;
                $startedAt = now()->subYears(min($seniority, rand(2, max(2, (int) ($seniority * 0.7)))));

                $exp = Experience::create([
                    'uuid'        => $this->uuid(),
                    'user_id'     => $user->id,
                    'hotel_id'    => $hotel->id,
                    'title'       => $spec['title'],
                    'company'     => $hotel->name,
                    'started_at'  => $startedAt,
                    'ended_at'    => null,
                    'description' => $this->experienceDescription($spec['title'], $hotel->name),
                    'verified'    => true,
                ]);
                $this->backdate($exp, $startedAt);

                HotelUser::create([
                    'hotel_id' => $hotel->id,
                    'user_id'  => $user->id,
                    'role'     => $isAdmin ? 'admin' : 'employee',
                    'status'   => 'approved',
                ]);
            } else {
                // Consultores independentes: experiência autônoma verificada por conta própria.
                $startedAt = now()->subYears(rand(2, 6));
                $exp = Experience::create([
                    'uuid'        => $this->uuid(),
                    'user_id'     => $user->id,
                    'hotel_id'    => null,
                    'title'       => $spec['title'],
                    'company'     => 'Consultoria independente',
                    'started_at'  => $startedAt,
                    'ended_at'    => null,
                    'description' => 'Atendimento a hotéis, resorts e pousadas em projetos de diagnóstico, implantação e treinamento de equipes.',
                    'verified'    => false,
                ]);
                $this->backdate($exp, $startedAt);
            }

            // Histórico de carreira: um ou dois cargos anteriores coerentes com o tempo de casa.
            $historyCount = $seniority >= 12 ? 2 : ($seniority >= 6 ? 1 : 0);
            for ($h = 0; $h < $historyCount; $h++) {
                $endedAt = now()->subYears($seniority - rand(2, 5) - ($h * 3));
                $started = (clone $endedAt)->subYears(rand(2, 4));
                if ($started->isFuture() || $endedAt->isFuture()) {
                    continue;
                }

                $exp = Experience::create([
                    'uuid'        => $this->uuid(),
                    'user_id'     => $user->id,
                    'hotel_id'    => null,
                    'title'       => $h === $historyCount - 1 ? $this->pick($juniorRoles) : $this->priorRole($spec['title']),
                    'company'     => $this->pick($previousEmployers),
                    'started_at'  => $started,
                    'ended_at'    => $endedAt,
                    'description' => 'Atuação na operação diária, com foco em padrão de atendimento e resultado da área.',
                    'verified'    => false,
                ]);
                $this->backdate($exp, $started);
            }
        }

        // Um par de vínculos pendentes/recusados para demonstrar o fluxo de aprovação do hotel.
        foreach ([1, 4] as $hotelIndex) {
            $candidate = $this->users[array_rand($this->users)];
            $hotel = $this->hotels[$hotelIndex];
            if (HotelUser::where('hotel_id', $hotel->id)->where('user_id', $candidate->id)->exists()) {
                continue;
            }

            HotelUser::create([
                'hotel_id' => $hotel->id,
                'user_id'  => $candidate->id,
                'role'     => 'employee',
                'status'   => 'pending',
            ]);
        }
    }

    private function experienceDescription(string $title, string $hotel): string
    {
        return match (true) {
            str_contains($title, 'Gerente Geral')       => "Responsável pelo P&L, pela liderança das sete chefias e pelos indicadores de satisfação do {$hotel}.",
            str_contains($title, 'Diretor')             => 'Responsável pela padronização operacional, orçamento e desenvolvimento das lideranças das unidades.',
            str_contains($title, 'Governanta')          => 'Gestão da equipe de camareiras, controle de enxoval, vistoria de apartamentos e interface com manutenção.',
            str_contains($title, 'Chef')                => 'Criação de cardápio, gestão de ficha técnica e CMV, liderança da brigada e operação de eventos.',
            str_contains($title, 'Reservas')            => 'Gestão da central de reservas, tarifas e disponibilidade nos canais e acompanhamento de pick-up.',
            str_contains($title, 'Concierge')           => 'Atendimento personalizado ao hóspede, rede de parceiros na cidade e resolução de solicitações especiais.',
            str_contains($title, 'A&B')                 => 'Coordenação dos pontos de consumo, escala da equipe de salão e controle de custo de alimentos e bebidas.',
            str_contains($title, 'Recepção')            => 'Liderança do turno de recepção, indicadores de check-in e treinamento da equipe de linha de frente.',
            str_contains($title, 'Revenue')             => 'Análise de pick-up, forecast, monitoramento de concorrência e apoio à decisão tarifária.',
            str_contains($title, 'Manutenção')          => 'Plano de manutenção preventiva e corretiva, gestão de utilidades e atendimento de chamados.',
            str_contains($title, 'Housekeeping')        => 'Supervisão da arrumação, vistoria de apartamentos e controle de produtos e enxoval.',
            str_contains($title, 'Eventos')             => 'Comercial e operação de eventos sociais e corporativos, do orçamento ao pós-evento.',
            str_contains($title, 'Maître')              => 'Gestão do salão, escala da brigada de garçons e padrão de serviço do restaurante.',
            str_contains($title, 'Marketing')           => 'Venda direta, campanhas, conteúdo e relacionamento com canais de distribuição.',
            str_contains($title, 'RH')                  => 'Recrutamento, integração, treinamento e programas de retenção das equipes operacionais.',
            str_contains($title, 'Sommelière')          => 'Curadoria da carta de vinhos, harmonização do menu e treinamento do salão.',
            default                                     => 'Atuação direta na operação diária, com foco em padrão de atendimento e satisfação do hóspede.',
        };
    }

    private function priorRole(string $title): string
    {
        return match (true) {
            str_contains($title, 'Gerente Geral') => 'Gerente de Hospedagem',
            str_contains($title, 'Diretor')       => 'Gerente Geral',
            str_contains($title, 'Governanta')    => 'Supervisora de Governança',
            str_contains($title, 'Chef')          => 'Sous Chef',
            str_contains($title, 'Consultor'),
            str_contains($title, 'Consultora')    => 'Gerente de Operações',
            default                               => 'Supervisor(a) de Operações',
        };
    }

    // ──────────────────────────────────────────────────────────
    // Rede: conexões e seguidores
    // ──────────────────────────────────────────────────────────

    private function createNetwork(): void
    {
        foreach ($this->users as $user) {
            $targets = collect($this->users)
                ->reject(fn (User $u) => $u->id === $user->id)
                ->shuffle()
                ->take(rand(6, 14));

            foreach ($targets as $target) {
                $exists = Connection::where(function ($q) use ($user, $target) {
                    $q->where('requester_id', $user->id)->where('addressee_id', $target->id);
                })->orWhere(function ($q) use ($user, $target) {
                    $q->where('requester_id', $target->id)->where('addressee_id', $user->id);
                })->exists();

                if ($exists) {
                    continue;
                }

                $status = rand(1, 100) <= 88 ? 'accepted' : 'pending';
                $connection = Connection::create([
                    'uuid'         => $this->uuid(),
                    'requester_id' => $user->id,
                    'addressee_id' => $target->id,
                    'status'       => $status,
                ]);
                $this->backdate($connection, $this->daysAgo(3, 500));
            }
        }

        // Seguidores dos consultores PRO: quem publica conteúdo tem audiência maior.
        foreach ($this->creators as $creator) {
            $followers = collect($this->users)
                ->reject(fn (User $u) => $u->id === $creator->id)
                ->shuffle()
                ->take(rand(12, 26));

            foreach ($followers as $follower) {
                UserFollower::firstOrCreate([
                    'creator_id'  => $creator->id,
                    'follower_id' => $follower->id,
                ]);
            }

            // Base "de mercado" além dos usuários semeados, para parecer uma rede viva.
            $creator->forceFill(['followers_count' => $followers->count() + rand(180, 2400)])->save();
        }

        foreach ($this->hotels as $index => $hotel) {
            $followers = collect($this->users)->shuffle()->take(rand(8, 20));
            foreach ($followers as $follower) {
                HotelFollower::firstOrCreate([
                    'hotel_id' => $hotel->id,
                    'user_id'  => $follower->id,
                ]);
            }

            $isPro = $this->hotelSpecs[$index]['pro'];
            $hotel->forceFill([
                'followers_count' => $followers->count() + ($isPro ? rand(600, 4200) : rand(60, 700)),
            ])->save();
        }
    }

    // ──────────────────────────────────────────────────────────
    // Vagas e candidaturas
    // ──────────────────────────────────────────────────────────

    private function createJobs(): void
    {
        $catalog = DemoContent::jobs();
        $letters = [
            'Trabalho há %d anos em hotelaria e conheço bem a rotina descrita na vaga. Tenho disponibilidade para a escala informada e ficaria muito feliz em contribuir com o time de vocês.',
            'Acompanho o trabalho de vocês há um tempo e me identifico muito com a forma como tratam o hóspede. Somo %d anos de experiência na área e estou em busca de um novo desafio.',
            'Sou da região e já conheço a operação de vocês. Tenho %d anos de casa em funções semelhantes e vivência com equipe em alta temporada.',
            'Estou em transição para uma operação de maior porte. Com %d anos de experiência, acredito que consigo assumir a rotina da vaga com autonomia desde o início.',
        ];
        $statuses = ['pending', 'pending', 'viewed', 'viewed', 'viewed', 'rejected', 'hired'];

        foreach ($this->hotels as $index => $hotel) {
            $spec = $this->hotelSpecs[$index];
            // Hotéis PRO publicam mais: parte do valor do plano é justamente alcance de vagas.
            $count = $spec['pro'] ? rand(3, 5) : rand(1, 3);
            $used = [];

            for ($j = 0; $j < $count; $j++) {
                do {
                    $entry = $this->pick($catalog);
                } while (in_array($entry['title'], $used, true) && count($used) < count($catalog));
                $used[] = $entry['title'];

                $publishedAt = $this->daysAgo(1, 90);
                $isClosed = rand(1, 100) <= 20;

                $job = Job::create([
                    'uuid'          => $this->uuid(),
                    'hotel_id'      => $hotel->id,
                    'title'         => $entry['title'],
                    'description'   => $entry['description'],
                    'requirements'  => $entry['requirements'],
                    'benefits'      => $entry['benefits'],
                    'contract_type' => $entry['contract_type'],
                    'modality'      => $entry['modality'],
                    'city'          => $hotel->city,
                    'state'         => $hotel->state,
                    'salary_range'  => $entry['salary'],
                    'status'        => $isClosed ? 'closed' : 'active',
                ]);
                $this->backdate($job, $publishedAt);
                $this->jobs[] = $job;

                $applicants = collect($this->users)
                    ->filter(fn (User $u, int $k) => $this->specOf($k)['creator'] === false)
                    ->shuffle()
                    ->take(rand(2, 9));

                foreach ($applicants as $k => $applicant) {
                    $seniority = $this->specOf(array_search($applicant, $this->users, true) ?: 0)['seniority'] ?? 3;

                    $application = JobApplication::create([
                        'uuid'         => $this->uuid(),
                        'job_id'       => $job->id,
                        'user_id'      => $applicant->id,
                        'cover_letter' => sprintf($this->pick($letters), max(1, $seniority)),
                        'status'       => $this->pick($statuses),
                    ]);
                    $this->backdate($application, $this->between($publishedAt));
                }
            }
        }
    }

    // ──────────────────────────────────────────────────────────
    // Feed
    // ──────────────────────────────────────────────────────────

    private function createFeed(): void
    {
        $library = DemoContent::postsBySpecialty();
        $commentLibrary = DemoContent::comments();

        foreach ($this->users as $i => $user) {
            $spec = $this->specOf($i);
            $specialty = $spec['specialty'];
            $pool = $library[$specialty] ?? $library['Hospitalidade'];

            // Consultores PRO publicam com mais frequência: conteúdo é o produto deles.
            $count = $spec['creator'] ? rand(3, 4) : rand(1, 3);
            $chosen = collect($pool)->shuffle()->take(min($count, count($pool)));

            foreach ($chosen as $entry) {
                $publishedAt = $this->daysAgo(0, 120);

                $post = Post::create([
                    'uuid'       => $this->uuid(),
                    'user_id'    => $user->id,
                    'hotel_id'   => null,
                    'content'    => $entry['text'],
                    'image_path' => $entry['media'] ? $this->media->storeFrom($entry['media'], 'posts', 1200, 800) : null,
                    'type'       => 'post',
                ]);
                $this->backdate($post, $publishedAt);

                $this->engagePost($post, $publishedAt, $specialty, $commentLibrary, $spec['creator']);
            }
        }

        // Posts institucionais das páginas de hotel.
        foreach ($this->hotels as $index => $hotel) {
            $admin = HotelUser::where('hotel_id', $hotel->id)->where('role', 'admin')->first();
            if (! $admin) {
                continue;
            }

            $entries = collect(DemoContent::hotelPosts())->shuffle()->take(rand(1, 3));
            foreach ($entries as $entry) {
                $publishedAt = $this->daysAgo(0, 100);

                $post = Post::create([
                    'uuid'       => $this->uuid(),
                    'user_id'    => $admin->user_id,
                    'hotel_id'   => $hotel->id,
                    'content'    => $entry['text'],
                    'image_path' => $this->media->storeFrom($entry['media'], 'posts', 1200, 800),
                    'type'       => 'post',
                ]);
                $this->backdate($post, $publishedAt);

                $this->engagePost($post, $publishedAt, 'geral', $commentLibrary, true);
            }
        }
    }

    /** Curtidas e comentários coerentes com o tema do post. */
    private function engagePost(Post $post, Carbon $publishedAt, string $specialty, array $commentLibrary, bool $highReach): void
    {
        $likers = collect($this->users)
            ->reject(fn (User $u) => $u->id === $post->user_id)
            ->shuffle()
            ->take($highReach ? rand(8, 24) : rand(2, 12));

        foreach ($likers as $liker) {
            $like = Like::firstOrCreate(['user_id' => $liker->id, 'post_id' => $post->id]);
            $this->backdate($like, $this->between($publishedAt));
        }

        $pool = array_merge($commentLibrary[$specialty] ?? [], $commentLibrary['geral']);
        $comments = collect($pool)->shuffle()->take(rand(0, 4));

        foreach ($comments as $text) {
            $commenter = $this->users[array_rand($this->users)];
            if ($commenter->id === $post->user_id) {
                continue;
            }

            $commentedAt = $this->between($publishedAt);
            $comment = Comment::create([
                'uuid'    => $this->uuid(),
                'post_id' => $post->id,
                'user_id' => $commenter->id,
                'content' => $text,
            ]);
            $this->backdate($comment, $commentedAt);

            // O autor responde parte dos comentários — conversa, não monólogo.
            if (rand(1, 100) <= 35) {
                $reply = Comment::create([
                    'uuid'      => $this->uuid(),
                    'post_id'   => $post->id,
                    'user_id'   => $post->user_id,
                    'parent_id' => $comment->id,
                    'content'   => $this->pick([
                        'Obrigado! Se quiser, te mando o modelo que uso aqui.',
                        'Exatamente isso. E o mais difícil é manter depois que a novidade passa.',
                        'Boa pergunta — vou fazer um post só sobre esse ponto.',
                        'Que bom que fez sentido para a sua realidade!',
                        'Conta depois como foi aí, tenho curiosidade genuína.',
                    ]),
                ]);
                $this->backdate($reply, $this->between($commentedAt));
            }
        }
    }

    // ──────────────────────────────────────────────────────────
    // Cursos
    // ──────────────────────────────────────────────────────────

    private function createCourses(): void
    {
        foreach (DemoContent::courses() as $entry) {
            $author = $this->creatorBySpecialty($entry['specialty']);
            if (! $author) {
                continue;
            }

            $publishedAt = $this->daysAgo(30, 400);

            $course = Course::create([
                'uuid'           => $this->uuid(),
                'user_id'        => $author->id,
                'title'          => $entry['title'],
                'description'    => $entry['description'],
                'thumbnail_path' => $this->media->storeFrom($entry['media'], 'courses/thumbnails', 1200, 675),
                'category'       => 'Hotelaria',
                'level'          => $entry['level'],
                'price_cents'    => $entry['price'],
                'status'         => 'published',
            ]);
            $this->backdate($course, $publishedAt);

            foreach ($entry['lessons'] as $position => $lesson) {
                CourseLesson::create([
                    'uuid'             => $this->uuid(),
                    'course_id'        => $course->id,
                    'title'            => $lesson[0],
                    'description'      => $lesson[1],
                    'content'          => $lesson[1] . ' Material de apoio com checklist e planilha disponível para download ao final da aula.',
                    'video_url'        => null,
                    'position'         => $position + 1,
                    'duration_seconds' => rand(11, 27) * 60,
                ]);
            }

            // Curso gratuito atrai muito mais matrícula que curso pago.
            $take = $entry['price'] === 0 ? rand(14, 24) : rand(5, 14);
            $students = collect($this->users)
                ->reject(fn (User $u) => $u->id === $author->id)
                ->shuffle()
                ->take($take);

            foreach ($students as $student) {
                $enrollment = CourseEnrollment::firstOrCreate([
                    'course_id' => $course->id,
                    'user_id'   => $student->id,
                ]);
                $this->backdate($enrollment, $this->between($publishedAt));
            }

            $course->forceFill([
                'lessons_count'     => count($entry['lessons']),
                'enrollments_count' => $students->count() + ($entry['price'] === 0 ? rand(200, 900) : rand(20, 180)),
            ])->save();
        }
    }

    private function creatorBySpecialty(string $specialty): ?User
    {
        foreach ($this->users as $i => $user) {
            $spec = $this->specOf($i);
            if ($spec['creator'] && $spec['specialty'] === $specialty) {
                return $user;
            }
        }

        return null;
    }

    // ──────────────────────────────────────────────────────────
    // Mentorias
    // ──────────────────────────────────────────────────────────

    private function createMentorships(): void
    {
        $messages = [
            'Sou gerente de hospedagem em um hotel de 90 apartamentos e preciso organizar a estratégia de preço da baixa temporada. Pode ser?',
            'Quero entender se faz sentido investir em motor de reservas próprio no meu porte de operação.',
            'Estou assumindo a governança pela primeira vez e queria ajuda para montar o padrão de arrumação.',
            'Tenho uma entrevista para gerente geral na semana que vem e queria treinar a apresentação de resultados.',
            'Nosso CMV está em 38% e a diretoria quer 30%. Preciso de um olhar de fora no cardápio.',
            'Vamos abrir um departamento de eventos e não sei por onde começar a precificar o salão.',
        ];

        foreach (DemoContent::mentorships() as $entry) {
            $author = $this->creatorBySpecialty($entry['specialty']);
            if (! $author) {
                continue;
            }

            $publishedAt = $this->daysAgo(20, 300);

            $mentorship = Mentorship::create([
                'uuid'             => $this->uuid(),
                'user_id'          => $author->id,
                'title'            => $entry['title'],
                'description'      => $entry['description'],
                'category'         => 'Hotelaria',
                'duration_minutes' => $entry['duration'],
                'session_type'     => $entry['type'],
                'price_cents'      => $entry['price'],
                'status'           => 'published',
            ]);
            $this->backdate($mentorship, $publishedAt);
            $this->mentorships[] = $mentorship;

            $bookers = collect($this->users)
                ->reject(fn (User $u) => $u->id === $author->id)
                ->shuffle()
                ->take($entry['price'] === 0 ? rand(5, 10) : rand(2, 7));

            foreach ($bookers as $booker) {
                // Mistura de sessões já realizadas e agendadas — histórico + agenda futura.
                $isPast = rand(1, 100) <= 60;
                $scheduledAt = $isPast
                    ? now()->subDays(rand(2, 120))->setTime(rand(9, 18), $this->pick([0, 30]))
                    : now()->addDays(rand(1, 25))->setTime(rand(9, 18), $this->pick([0, 30]));

                $booking = MentorshipBooking::create([
                    'uuid'          => $this->uuid(),
                    'mentorship_id' => $mentorship->id,
                    'user_id'       => $booker->id,
                    'message'       => $this->pick($messages),
                    'scheduled_at'  => $scheduledAt,
                    'status'        => $isPast
                        ? $this->pick(['completed', 'completed', 'completed', 'cancelled'])
                        : $this->pick(['pending', 'confirmed', 'confirmed']),
                ]);
                $this->backdate($booking, $scheduledAt->copy()->subDays(rand(2, 15)));
            }

            $mentorship->forceFill([
                'bookings_count' => $bookers->count() + rand(3, 40),
            ])->save();
        }
    }

    // ──────────────────────────────────────────────────────────
    // Vídeos
    // ──────────────────────────────────────────────────────────

    private function createVideos(): void
    {
        $comments = DemoContent::videoComments();
        $entries = DemoContent::videos();
        $total = count($entries);

        foreach ($entries as $n => $entry) {
            $author = $this->creatorBySpecialty($entry['specialty']);
            if (! $author) {
                continue;
            }

            $this->command?->line(sprintf('   vídeo %d/%d — %s', $n + 1, $total, $entry['title']));

            $slideshow = $this->media->makeSlideshow($entry['scenes']);
            $publishedAt = $this->daysAgo(5, 300);

            $video = Video::create([
                'uuid'             => $this->uuid(),
                'user_id'          => $author->id,
                'title'            => $entry['title'],
                'description'      => $entry['description'],
                'video_path'       => $slideshow[0] ?? null,
                'thumbnail_path'   => $this->media->storeFrom($entry['scenes'][0], 'videos/thumbnails', 1280, 720),
                'duration_seconds' => $slideshow[1] ?? 0,
                'category'         => $entry['category'],
                'tags'             => ['hotelaria', Str::slug($entry['specialty']), $entry['category']],
                'visibility'       => 'public',
                'status'           => 'published',
            ]);
            $this->backdate($video, $publishedAt);

            $likers = collect($this->users)
                ->reject(fn (User $u) => $u->id === $author->id)
                ->shuffle()
                ->take(rand(8, 25));

            foreach ($likers as $liker) {
                $like = VideoLike::firstOrCreate(['video_id' => $video->id, 'user_id' => $liker->id]);
                $this->backdate($like, $this->between($publishedAt));
            }

            $chosen = collect($comments)->shuffle()->take(rand(1, 5));
            foreach ($chosen as $text) {
                $commenter = $this->users[array_rand($this->users)];
                if ($commenter->id === $author->id) {
                    continue;
                }

                $comment = VideoComment::create([
                    'uuid'     => $this->uuid(),
                    'video_id' => $video->id,
                    'user_id'  => $commenter->id,
                    'content'  => $text,
                ]);
                $this->backdate($comment, $this->between($publishedAt));
            }

            $video->forceFill([
                'likes_count'    => $video->likes()->count() + rand(20, 400),
                'comments_count' => $video->comments()->count(),
                'views_count'    => rand(900, 34000),
                'shares_count'   => rand(4, 180),
            ])->save();
        }
    }

    // ──────────────────────────────────────────────────────────
    // Avaliações
    // ──────────────────────────────────────────────────────────

    private function createReviews(): void
    {
        $library = DemoContent::reviews();

        $write = function (string $type, int $id, array $userIds, Carbon $after) use ($library) {
            foreach ($userIds as $userId) {
                // Distribuição realista: maioria 5, alguns 4, poucos 3.
                $roll = rand(1, 100);
                $rating = $roll <= 62 ? 5 : ($roll <= 90 ? 4 : 3);

                $review = Review::create([
                    'uuid'            => $this->uuid(),
                    'reviewable_type' => $type,
                    'reviewable_id'   => $id,
                    'user_id'         => $userId,
                    'rating'          => $rating,
                    'comment'         => $this->pick($library[$rating]),
                ]);
                $this->backdate($review, $this->between($after));
            }
        };

        // Avaliações dos consultores.
        foreach ($this->creators as $creator) {
            $reviewers = collect($this->users)
                ->reject(fn (User $u) => $u->id === $creator->id)
                ->shuffle()
                ->take(rand(5, 14))
                ->pluck('id')
                ->all();

            $write(User::class, $creator->id, $reviewers, now()->subDays(200));
        }

        // Avaliações de cursos: só quem está matriculado avalia.
        foreach (Course::all() as $course) {
            $students = CourseEnrollment::where('course_id', $course->id)
                ->inRandomOrder()
                ->limit(rand(3, 9))
                ->pluck('user_id')
                ->all();

            $write(Course::class, $course->id, $students, $course->created_at ?? now()->subDays(120));
        }

        // Avaliações de mentorias: só quem teve sessão concluída avalia.
        foreach ($this->mentorships as $mentorship) {
            $attendees = MentorshipBooking::where('mentorship_id', $mentorship->id)
                ->where('status', 'completed')
                ->pluck('user_id')
                ->all();

            $write(Mentorship::class, $mentorship->id, $attendees, $mentorship->created_at ?? now()->subDays(90));
        }
    }

    // ──────────────────────────────────────────────────────────
    // Stories (exclusivo PRO)
    // ──────────────────────────────────────────────────────────

    private function createStories(): void
    {
        $jobCaptions = DemoContent::jobStoryCaptions();
        $mentorshipCaptions = DemoContent::mentorshipStoryCaptions();

        // Stories de vaga: apenas hotéis com plano PRO - Empresa.
        foreach ($this->hotels as $index => $hotel) {
            if (! $this->hotelSpecs[$index]['pro']) {
                continue;
            }

            $jobs = collect($this->jobs)
                ->filter(fn (Job $j) => $j->hotel_id === $hotel->id && $j->status === 'active')
                ->shuffle()
                ->take(rand(1, 2));

            foreach ($jobs as $job) {
                $publishedAt = $this->daysAgo(0, 12);

                $story = Story::create([
                    'uuid'       => $this->uuid(),
                    'story_type' => 'job',
                    'job_id'     => $job->id,
                    'caption'    => $this->pick($jobCaptions),
                    'image_path' => $this->media->storeFrom(
                        $this->hotelSpecs[$index]['media'],
                        'story-images',
                        1080,
                        1350
                    ),
                ]);
                $this->backdate($story, $publishedAt);
                $this->engageStory($story, $publishedAt);
            }
        }

        // Stories de mentoria: apenas consultores com plano PRO - Consultor.
        foreach ($this->creators as $creator) {
            $mentorships = collect($this->mentorships)
                ->filter(fn (Mentorship $m) => $m->user_id === $creator->id)
                ->shuffle()
                ->take(1);

            foreach ($mentorships as $mentorship) {
                $publishedAt = $this->daysAgo(0, 10);

                $story = Story::create([
                    'uuid'          => $this->uuid(),
                    'story_type'    => 'mentorship',
                    'mentorship_id' => $mentorship->id,
                    'caption'       => $this->pick($mentorshipCaptions),
                    'image_path'    => $this->media->storeFrom('team', 'story-images', 1080, 1350),
                ]);
                $this->backdate($story, $publishedAt);
                $this->engageStory($story, $publishedAt);
            }
        }
    }

    private function engageStory(Story $story, Carbon $publishedAt): void
    {
        $likers = collect($this->users)->shuffle()->take(rand(4, 18));
        foreach ($likers as $liker) {
            $like = StoryLike::firstOrCreate(['story_id' => $story->id, 'user_id' => $liker->id]);
            $this->backdate($like, $this->between($publishedAt));
        }

        $story->forceFill([
            'likes_count'  => $likers->count(),
            'views_count'  => $likers->count() * rand(9, 30),
            'shares_count' => rand(0, 22),
        ])->save();
    }

    // ──────────────────────────────────────────────────────────
    // Conversas (caixa de entrada)
    // ──────────────────────────────────────────────────────────

    private function createConversations(): void
    {
        $threads = DemoContent::conversations();

        foreach ($threads as $t => $lines) {
            $a = $this->users[($t * 7) % count($this->users)];
            $b = $this->users[($t * 5 + 3) % count($this->users)];
            if ($a->id === $b->id) {
                continue;
            }

            $startedAt = $this->daysAgo(1, 45);

            foreach ($lines as $k => $line) {
                $sender = $k % 2 === 0 ? $a : $b;
                $receiver = $k % 2 === 0 ? $b : $a;
                $sentAt = $startedAt->copy()->addHours($k * rand(1, 6));

                $message = Message::create([
                    'uuid'        => $this->uuid(),
                    'sender_id'   => $sender->id,
                    'receiver_id' => $receiver->id,
                    'content'     => $line,
                    'type'        => 'text',
                    // Última mensagem fica sem ler para a caixa de entrada mostrar badge.
                    'read_at'     => $k < count($lines) - 1 ? $sentAt->copy()->addMinutes(rand(2, 90)) : null,
                ]);
                $this->backdate($message, $sentAt);
            }
        }

        // Caixa de entrada compartilhada do hotel: candidato falando com a página.
        foreach (array_slice($this->hotels, 0, 4) as $hotel) {
            $candidate = $this->users[array_rand($this->users)];
            $admin = HotelUser::where('hotel_id', $hotel->id)->where('role', 'admin')->first();
            if (! $admin || $admin->user_id === $candidate->id) {
                continue;
            }

            $startedAt = $this->daysAgo(1, 20);

            $inbound = Message::create([
                'uuid'              => $this->uuid(),
                'sender_id'         => $candidate->id,
                'receiver_hotel_id' => $hotel->id,
                'content'           => 'Olá! Me candidatei a uma vaga de vocês na semana passada e gostaria de saber se o processo já está em andamento. Obrigado!',
                'type'              => 'text',
            ]);
            $this->backdate($inbound, $startedAt);

            $reply = Message::create([
                'uuid'            => $this->uuid(),
                'sender_id'       => $admin->user_id,
                'sender_hotel_id' => $hotel->id,
                'receiver_id'     => $candidate->id,
                'content'         => 'Olá! Tudo bem? Estamos finalizando a triagem esta semana e retornamos para todos os candidatos até sexta-feira. Obrigado pelo interesse!',
                'type'            => 'text',
                'read_at'         => null,
            ]);
            $this->backdate($reply, $startedAt->copy()->addHours(rand(3, 30)));
        }
    }

    // ──────────────────────────────────────────────────────────
    // Notificações
    // ──────────────────────────────────────────────────────────

    private function createNotifications(): void
    {
        foreach ($this->users as $user) {
            $count = rand(3, 8);

            for ($n = 0; $n < $count; $n++) {
                $actor = $this->users[array_rand($this->users)];
                if ($actor->id === $user->id) {
                    continue;
                }

                $kind = $this->pick(['connection', 'feed', 'job', 'message', 'hotel']);
                [$type, $title, $description, $link] = match ($kind) {
                    'connection' => ['connection_accepted', 'Nova conexão', "{$actor->name} aceitou o seu convite de conexão.", '/rede'],
                    'feed'       => ['post_liked', 'Curtida na sua publicação', "{$actor->name} curtiu a sua publicação.", '/feed'],
                    'job'        => ['job_application', 'Atualização de candidatura', 'Sua candidatura foi visualizada pelo recrutador.', '/vagas'],
                    'message'    => ['message_received', 'Nova mensagem', "{$actor->name} enviou uma mensagem para você.", '/mensagens'],
                    default      => ['hotel_follow', 'Novidade de um hotel que você segue', 'Um hotel que você segue publicou uma nova vaga.', '/vagas'],
                };

                $notification = Notification::create([
                    'uuid'        => $this->uuid(),
                    'user_id'     => $user->id,
                    'actor_id'    => $actor->id,
                    'type'        => $type,
                    'category'    => $kind,
                    'title'       => $title,
                    'description' => $description,
                    'link'        => $link,
                    'read'        => rand(1, 100) <= 60,
                ]);
                $this->backdate($notification, $this->daysAgo(0, 30));
            }
        }
    }

    // ──────────────────────────────────────────────────────────
    // Resumo
    // ──────────────────────────────────────────────────────────

    private function summary(): void
    {
        $rows = [
            ['Hotéis', Hotel::count()],
            ['Profissionais', User::count()],
            ['Assinaturas PRO', Subscription::count()],
            ['Vagas', Job::count()],
            ['Candidaturas', JobApplication::count()],
            ['Posts', Post::count()],
            ['Comentários', Comment::count()],
            ['Curtidas', Like::count()],
            ['Stories', Story::count()],
            ['Vídeos', Video::count()],
            ['Cursos', Course::count()],
            ['Aulas', CourseLesson::count()],
            ['Matrículas', CourseEnrollment::count()],
            ['Mentorias', Mentorship::count()],
            ['Agendamentos', MentorshipBooking::count()],
            ['Avaliações', Review::count()],
            ['Mensagens', Message::count()],
            ['Conexões', Connection::count()],
        ];

        $this->command?->newLine();
        $this->command?->table(['Entidade', 'Total'], $rows);
        $this->command?->info('Login de qualquer perfil: e-mail do usuário + senha "password" (domínio @' . self::EMAIL_DOMAIN . ').');
    }
}
