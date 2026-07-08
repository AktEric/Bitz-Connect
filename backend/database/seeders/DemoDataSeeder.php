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
use App\Models\Post;
use App\Models\Review;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserFollower;
use App\Models\Video;
use App\Models\VideoComment;
use App\Models\VideoLike;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Popula o ambiente com um cenário fictício completo de rede social para
 * demonstração — hotéis, profissionais, posts, vídeos, cursos, mentorias e
 * avaliações. Não toca em nenhum dado pré-existente: todo mundo criado aqui
 * usa o domínio de e-mail @demo.hotelconnect.test, senha "password".
 *
 * Rodar com: php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /** @var array<int, User> */
    private array $allUsers = [];

    /** @var array<int, User> creators (ProConsultor) */
    private array $creators = [];

    /** @var array<int, Hotel> */
    private array $hotels = [];

    public function run(): void
    {
        \DB::transaction(function () {
            $this->command?->info('Criando hotéis fictícios...');
            $this->createHotels();

            $this->command?->info('Criando usuários fictícios...');
            $this->createUsers();

            $this->command?->info('Vinculando experiências e colaboradores...');
            $this->createExperiencesAndHotelUsers();

            $this->command?->info('Criando conexões e seguidores...');
            $this->createConnectionsAndFollows();

            $this->command?->info('Criando vagas e candidaturas...');
            $this->createJobsAndApplications();

            $this->command?->info('Criando posts, comentários e curtidas...');
            $this->createFeedContent();

            $this->command?->info('Criando vídeos dos criadores PRO...');
            $this->createVideos();

            $this->command?->info('Criando cursos...');
            $this->createCourses();

            $this->command?->info('Criando mentorias...');
            $this->createMentorships();

            $this->command?->info('Criando avaliações...');
            $this->createReviews();

            $this->command?->info('Cenário de demonstração criado com sucesso.');
        });
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    private function fetch(string $url): ?string
    {
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 6]]);
            $data = @file_get_contents($url, false, $ctx);
            return $data === false ? null : $data;
        } catch (\Throwable) {
            return null;
        }
    }

    private function storeImage(string $url, string $dir, string $ext = 'jpg'): ?string
    {
        $data = $this->fetch($url);
        if ($data === null) {
            return null;
        }
        $path = $dir . '/' . Str::random(20) . '.' . $ext;
        Storage::disk('public')->put($path, $data);
        return $path;
    }

    private function avatarUrl(int $seed): string
    {
        return "https://i.pravatar.cc/300?img=" . (($seed % 70) + 1);
    }

    private function bannerUrl(string $seed): string
    {
        return "https://picsum.photos/seed/{$seed}/1200/400";
    }

    private function logoUrl(string $name): string
    {
        $bg = substr(md5($name), 0, 6);
        return "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background={$bg}&color=fff&size=256&bold=true";
    }

    private function paragraphs(array $sentences, int $min = 1, int $max = 3): string
    {
        shuffle($sentences);
        return implode(' ', array_slice($sentences, 0, rand($min, $max)));
    }

    // ──────────────────────────────────────────────────────────
    // Hotels
    // ──────────────────────────────────────────────────────────

    private function createHotels(): void
    {
        $catalog = [
            ['name' => 'Costa Azul Resort',        'segment' => 'Resort',    'city' => 'Florianópolis', 'state' => 'SC', 'pro' => true],
            ['name' => 'Serra Verde Hotel',         'segment' => 'Hotel',     'city' => 'Gramado',       'state' => 'RS', 'pro' => true],
            ['name' => 'Pousada Vento Norte',       'segment' => 'Pousada',   'city' => 'Jericoacoara',  'state' => 'CE', 'pro' => false],
            ['name' => 'Rede Horizonte Hotéis',     'segment' => 'Rede',      'city' => 'São Paulo',     'state' => 'SP', 'pro' => true],
            ['name' => 'Ipanema Palace Hotel',      'segment' => 'Hotel',     'city' => 'Rio de Janeiro','state' => 'RJ', 'pro' => true],
            ['name' => 'Chalés da Montanha',        'segment' => 'Pousada',   'city' => 'Campos do Jordão','state' => 'SP', 'pro' => false],
            ['name' => 'Business Center Hotel',     'segment' => 'Hotel',     'city' => 'Curitiba',      'state' => 'PR', 'pro' => false],
            ['name' => 'Amazônia Eco Lodge',        'segment' => 'Resort',    'city' => 'Manaus',        'state' => 'AM', 'pro' => false],
            ['name' => 'Hostel Trilha Urbana',      'segment' => 'Hostel',    'city' => 'Belo Horizonte','state' => 'MG', 'pro' => false],
            ['name' => 'Grand Nordeste Hotéis',     'segment' => 'Rede',      'city' => 'Recife',        'state' => 'PE', 'pro' => true],
        ];

        foreach ($catalog as $entry) {
            $slug = Str::slug($entry['name']) . '-' . Str::random(4);

            $hotel = Hotel::create([
                'uuid'          => (string) Str::uuid(),
                'name'          => $entry['name'],
                'segment'       => $entry['segment'],
                'slug'          => $slug,
                'description'   => "Referência em hospitalidade em {$entry['city']}, oferecendo experiências memoráveis para hóspedes e oportunidades de carreira para profissionais do setor.",
                'logo_path'     => $this->storeImage($this->logoUrl($entry['name']), 'hotels/logos', 'png'),
                'cover_path'    => $this->storeImage($this->bannerUrl($slug . '-cover'), 'hotels/covers'),
                'city'          => $entry['city'],
                'state'         => $entry['state'],
                'country'       => 'Brasil',
                'website'       => 'https://' . Str::slug($entry['name']) . '.com.br',
                'phone'         => '55' . rand(11, 99) . rand(900000000, 999999999),
                'contact_email' => 'contato@' . Str::slug($entry['name']) . '.com.br',
            ]);

            if ($entry['pro']) {
                Subscription::create([
                    'uuid'               => (string) Str::uuid(),
                    'subscribable_type'  => Hotel::class,
                    'subscribable_id'    => $hotel->id,
                    'plan'               => SubscriptionPlan::ProEmpresa,
                    'status'             => SubscriptionStatus::Active,
                    'started_at'         => now()->subDays(rand(10, 200)),
                    'expires_at'         => null,
                    'provider'           => 'demo',
                ]);
            }

            $this->hotels[] = $hotel;
        }
    }

    // ──────────────────────────────────────────────────────────
    // Users
    // ──────────────────────────────────────────────────────────

    private function createUsers(): void
    {
        $titles = [
            'Gerente Geral', 'Diretor de Operações', 'Governanta Executiva', 'Concierge Sênior',
            'Chef Executivo', 'Recepcionista Chefe', 'Especialista em Revenue Management',
            'Consultor de Hotelaria', 'Gerente de Eventos', 'Coordenador de F&B',
            'Analista de Turismo', 'Gerente de Marketing Hoteleiro', 'Supervisor de Housekeeping',
            'Especialista em Experiência do Hóspede', 'Gerente Comercial', 'Diretor de RH Hoteleiro',
            'Sommelier', 'Gerente de Manutenção Predial', 'Consultora de Sustentabilidade Hoteleira',
            'Especialista em Revenue & Pricing',
        ];

        $firstNames = ['Ana', 'Bruno', 'Carla', 'Diego', 'Elaine', 'Fábio', 'Gabriela', 'Henrique', 'Isabela', 'João',
            'Karina', 'Lucas', 'Marina', 'Nicolas', 'Olívia', 'Pedro', 'Queila', 'Rafael', 'Sabrina', 'Thiago',
            'Úrsula', 'Vinícius', 'Wesley', 'Ximena', 'Yasmin', 'Zeca', 'Camila', 'Douglas', 'Eduarda', 'Felipe'];
        $lastNames = ['Almeida', 'Barros', 'Castro', 'Dias', 'Esteves', 'Ferreira', 'Gomes', 'Henriques', 'Ibrahim',
            'Junqueira', 'Klein', 'Lopes', 'Martins', 'Nogueira', 'Oliveira', 'Pereira', 'Queiroz', 'Ramos',
            'Silveira', 'Teixeira'];

        $bios = [
            'Apaixonado(a) por hospitalidade e experiências que encantam hóspedes.',
            'Mais de 10 anos transformando operações hoteleiras em referências de qualidade.',
            'Focado(a) em resultados, pessoas e excelência operacional.',
            'Especialista em criar times de alta performance no setor hoteleiro.',
            'Levando inovação e tecnologia para a hospitalidade brasileira.',
            'Construindo experiências inesquecíveis, um hóspede de cada vez.',
        ];

        $specialties = ['Gestão Hoteleira', 'Revenue Management', 'Hospitalidade', 'Governança', 'Eventos', 'F&B', 'Recursos Humanos', null];

        for ($i = 0; $i < 30; $i++) {
            $name = $firstNames[$i % count($firstNames)] . ' ' . $lastNames[($i * 3) % count($lastNames)];
            $slugBase = Str::slug($name);
            $isCreator = $i < 10; // primeiros 10 usuários são criadores PRO

            $user = User::create([
                'uuid'              => (string) Str::uuid(),
                'name'              => $name,
                'email'             => "{$slugBase}.{$i}@demo.hotelconnect.test",
                'password'          => Hash::make(self::PASSWORD),
                'avatar_path'       => $this->storeImage($this->avatarUrl($i), 'avatars'),
                'banner_path'       => $this->storeImage($this->bannerUrl("user-{$slugBase}-{$i}"), 'banners'),
                'bio'               => $this->paragraphs($bios, 1, 2),
                'specialty'         => $isCreator ? $specialties[$i % (count($specialties) - 1)] : $specialties[$i % count($specialties)],
                'city'              => $this->hotels[$i % count($this->hotels)]->city,
                'state'             => $this->hotels[$i % count($this->hotels)]->state,
                'role'              => 'user',
                'email_verified_at' => now(),
            ]);

            // usuário não persistido no fillable: title fica só na Experience
            $user->setAttribute('_demo_title', $titles[$i % count($titles)]);

            $this->allUsers[] = $user;

            if ($isCreator) {
                $this->creators[] = $user;

                Subscription::create([
                    'uuid'              => (string) Str::uuid(),
                    'subscribable_type' => User::class,
                    'subscribable_id'   => $user->id,
                    'plan'              => SubscriptionPlan::ProConsultor,
                    'status'            => SubscriptionStatus::Active,
                    'started_at'        => now()->subDays(rand(10, 180)),
                    'expires_at'        => null,
                    'provider'          => 'demo',
                ]);
            }
        }
    }

    // ──────────────────────────────────────────────────────────
    // Experiences + HotelUser (colaboradores)
    // ──────────────────────────────────────────────────────────

    private function createExperiencesAndHotelUsers(): void
    {
        // Um admin "dono" por hotel — primeiros 10 usuários (metade dos criadores viram donos também)
        foreach ($this->hotels as $i => $hotel) {
            $owner = $this->allUsers[$i];

            Experience::create([
                'uuid'        => (string) Str::uuid(),
                'user_id'     => $owner->id,
                'hotel_id'    => $hotel->id,
                'title'       => $owner->getAttribute('_demo_title') ?? 'Gerente Geral',
                'company'     => $hotel->name,
                'started_at'  => now()->subYears(rand(1, 6)),
                'ended_at'    => null,
                'description' => 'Liderança geral da operação, com foco em excelência no atendimento e resultados sustentáveis.',
                'verified'    => true,
            ]);

            HotelUser::create([
                'hotel_id' => $hotel->id,
                'user_id'  => $owner->id,
                'role'     => 'admin',
                'status'   => 'approved',
            ]);
        }

        // Demais usuários viram colaboradores (employee) de hotéis aleatórios
        for ($i = 10; $i < count($this->allUsers); $i++) {
            $user = $this->allUsers[$i];
            $hotel = $this->hotels[$i % count($this->hotels)];
            $isOngoing = rand(0, 100) < 70; // 70% ainda trabalham lá
            $statusRoll = rand(0, 100);
            $status = $statusRoll < 70 ? 'approved' : ($statusRoll < 90 ? 'pending' : 'rejected');

            Experience::create([
                'uuid'        => (string) Str::uuid(),
                'user_id'     => $user->id,
                'hotel_id'    => $hotel->id,
                'title'       => $user->getAttribute('_demo_title') ?? 'Profissional de Hotelaria',
                'company'     => $hotel->name,
                'started_at'  => now()->subYears(rand(1, 5))->subMonths(rand(0, 11)),
                'ended_at'    => $isOngoing ? null : now()->subMonths(rand(1, 10)),
                'description' => 'Atuação direta na operação diária, com foco em qualidade e satisfação do hóspede.',
                'verified'    => $status === 'approved',
            ]);

            HotelUser::create([
                'hotel_id' => $hotel->id,
                'user_id'  => $user->id,
                'role'     => 'employee',
                'status'   => $status,
            ]);

            // Segunda experiência (passada, encerrada) em outro hotel — histórico de carreira
            if (rand(0, 100) < 40) {
                $prevHotel = $this->hotels[($i + 3) % count($this->hotels)];
                Experience::create([
                    'uuid'        => (string) Str::uuid(),
                    'user_id'     => $user->id,
                    'hotel_id'    => null,
                    'title'       => 'Recepcionista',
                    'company'     => $prevHotel->name,
                    'started_at'  => now()->subYears(rand(6, 10)),
                    'ended_at'    => now()->subYears(rand(2, 5)),
                    'description' => 'Início de carreira, com forte aprendizado em atendimento ao cliente.',
                    'verified'    => false,
                ]);
            }
        }
    }

    // ──────────────────────────────────────────────────────────
    // Connections + Follows
    // ──────────────────────────────────────────────────────────

    private function createConnectionsAndFollows(): void
    {
        $count = count($this->allUsers);

        // Conexões entre profissionais
        for ($i = 0; $i < 45; $i++) {
            $a = $this->allUsers[rand(0, $count - 1)];
            $b = $this->allUsers[rand(0, $count - 1)];
            if ($a->id === $b->id) {
                continue;
            }
            $exists = Connection::where(function ($q) use ($a, $b) {
                $q->where('requester_id', $a->id)->where('addressee_id', $b->id);
            })->orWhere(function ($q) use ($a, $b) {
                $q->where('requester_id', $b->id)->where('addressee_id', $a->id);
            })->exists();
            if ($exists) {
                continue;
            }

            Connection::create([
                'uuid'         => (string) Str::uuid(),
                'requester_id' => $a->id,
                'addressee_id' => $b->id,
                'status'       => rand(0, 100) < 85 ? 'accepted' : 'pending',
            ]);
        }

        // Seguidores dos criadores (páginas de criador)
        foreach ($this->creators as $creator) {
            $followers = collect($this->allUsers)
                ->reject(fn (User $u) => $u->id === $creator->id)
                ->random(rand(6, 18));

            foreach ($followers as $follower) {
                UserFollower::firstOrCreate([
                    'creator_id'  => $creator->id,
                    'follower_id' => $follower->id,
                ]);
            }
            $creator->increment('followers_count', $followers->count());
        }

        // Seguidores dos hotéis
        foreach ($this->hotels as $hotel) {
            $followers = collect($this->allUsers)->random(rand(5, 15));
            foreach ($followers as $follower) {
                HotelFollower::firstOrCreate([
                    'hotel_id' => $hotel->id,
                    'user_id'  => $follower->id,
                ]);
            }
            $hotel->increment('followers_count', $followers->count());
        }
    }

    // ──────────────────────────────────────────────────────────
    // Jobs + Applications
    // ──────────────────────────────────────────────────────────

    private function createJobsAndApplications(): void
    {
        $jobTitles = ['Recepcionista', 'Camareira', 'Gerente de Turno', 'Analista de Reservas', 'Chef de Cozinha', 'Barman', 'Assistente de Eventos', 'Gerente de Revenue'];
        $contractTypes = ['clt', 'pj', 'freelance', 'internship'];
        $modalities = ['on_site', 'remote', 'hybrid'];

        foreach ($this->hotels as $hotel) {
            $numJobs = rand(1, 3);
            for ($j = 0; $j < $numJobs; $j++) {
                $job = Job::create([
                    'uuid'          => (string) Str::uuid(),
                    'hotel_id'      => $hotel->id,
                    'title'         => $jobTitles[array_rand($jobTitles)],
                    'description'   => "Estamos em busca de um(a) profissional dedicado(a) para integrar o time do {$hotel->name}, contribuindo para a excelência no atendimento aos nossos hóspedes.",
                    'requirements'  => 'Experiência prévia no setor hoteleiro, boa comunicação e disponibilidade de horário.',
                    'benefits'      => 'Vale-refeição, plano de saúde, day off no aniversário.',
                    'contract_type' => $contractTypes[array_rand($contractTypes)],
                    'modality'      => $modalities[array_rand($modalities)],
                    'city'          => $hotel->city,
                    'state'         => $hotel->state,
                    'salary_range'  => 'R$ ' . rand(18, 60) * 100 . ' - R$ ' . rand(61, 120) * 100,
                    'status'        => 'active',
                ]);

                $applicants = collect($this->allUsers)->random(rand(0, 6));
                foreach ($applicants as $applicant) {
                    JobApplication::create([
                        'uuid'         => (string) Str::uuid(),
                        'job_id'       => $job->id,
                        'user_id'      => $applicant->id,
                        'cover_letter' => 'Tenho grande interesse na vaga e acredito que minha experiência agrega valor ao time.',
                        'status'       => ['pending', 'viewed', 'rejected', 'hired'][array_rand(['pending', 'viewed', 'rejected', 'hired'])],
                    ]);
                }
            }
        }
    }

    // ──────────────────────────────────────────────────────────
    // Feed: posts, comments, likes
    // ──────────────────────────────────────────────────────────

    private function createFeedContent(): void
    {
        $postTexts = [
            'Hoje fechamos mais um mês com a taxa de ocupação acima da meta. Orgulho do time!',
            'Compartilhando um pouco da nossa rotina de treinamento com a equipe de recepção.',
            'A hospitalidade brasileira é única — cada hóspede que recebemos vira uma boa história.',
            'Dica do dia: pequenos gestos fazem toda a diferença na experiência do hóspede.',
            'Estamos contratando! Se você é apaixonado por hotelaria, dá uma olhada nas nossas vagas abertas.',
            'Participei de um evento incrível sobre revenue management essa semana. Aprendizado renovado!',
            'Nosso café da manhã ganhou uma reformulação completa. Vem conferir!',
            'Reflexão de sexta: liderar é servir. E servir bem é o que nos move todos os dias.',
            'Recebemos uma avaliação linda de um hóspede hoje. Isso não tem preço.',
            'Bastidores da preparação para a alta temporada — muita gente envolvida para entregar o melhor.',
            'A tecnologia está mudando a forma como gerenciamos reservas. Quem mais está migrando para automação?',
            'Time reunido para planejamento estratégico do próximo trimestre.',
        ];

        foreach ($this->allUsers as $user) {
            $numPosts = rand(2, 5);
            for ($p = 0; $p < $numPosts; $p++) {
                $withImage = rand(0, 100) < 30;
                $post = Post::create([
                    'uuid'       => (string) Str::uuid(),
                    'user_id'    => $user->id,
                    'hotel_id'   => null,
                    'content'    => $postTexts[array_rand($postTexts)],
                    'image_path' => $withImage ? $this->storeImage($this->bannerUrl('post-' . $user->id . '-' . $p), 'posts') : null,
                    'type'       => 'post',
                    'created_at' => now()->subDays(rand(0, 60))->subHours(rand(0, 23)),
                ]);

                // Curtidas
                $likers = collect($this->allUsers)->random(min(rand(0, 12), count($this->allUsers)));
                foreach ($likers as $liker) {
                    Like::firstOrCreate(['user_id' => $liker->id, 'post_id' => $post->id]);
                }

                // Comentários
                $numComments = rand(0, 4);
                for ($c = 0; $c < $numComments; $c++) {
                    $commenter = $this->allUsers[array_rand($this->allUsers)];
                    Comment::create([
                        'uuid'    => (string) Str::uuid(),
                        'post_id' => $post->id,
                        'user_id' => $commenter->id,
                        'content' => ['Muito bom!', 'Parabéns pelo trabalho!', 'Excelente iniciativa.', 'Inspirador!', 'Concordo plenamente.'][array_rand(['Muito bom!', 'Parabéns pelo trabalho!', 'Excelente iniciativa.', 'Inspirador!', 'Concordo plenamente.'])],
                    ]);
                }
            }
        }
    }

    // ──────────────────────────────────────────────────────────
    // Videos (apenas criadores PRO)
    // ──────────────────────────────────────────────────────────

    private function createVideos(): void
    {
        $sampleVideo = $this->fetch('https://www.w3schools.com/html/mov_bbb.mp4');
        $videoPath = null;
        if ($sampleVideo !== null) {
            $videoPath = 'videos/demo-sample.mp4';
            Storage::disk('public')->put($videoPath, $sampleVideo);
        }

        $titles = [
            'Como encantar hóspedes em 3 passos', '5 dicas de revenue management', 'Bastidores de um check-in perfeito',
            'A importância do primeiro contato', 'Tendências de hotelaria para este ano', 'Como motivar seu time de recepção',
        ];
        $categories = ['gestao', 'atendimento', 'operacoes', 'carreira'];

        foreach ($this->creators as $creator) {
            $numVideos = rand(2, 4);
            for ($v = 0; $v < $numVideos; $v++) {
                $video = Video::create([
                    'uuid'             => (string) Str::uuid(),
                    'user_id'          => $creator->id,
                    'title'            => $titles[array_rand($titles)],
                    'description'      => 'Conteúdo exclusivo sobre gestão hoteleira e experiência do hóspede.',
                    'video_path'       => $videoPath ?? 'videos/demo-sample.mp4',
                    'thumbnail_path'   => $this->storeImage($this->bannerUrl('video-' . $creator->id . '-' . $v), 'videos/thumbnails'),
                    'duration_seconds' => rand(10, 30),
                    'category'         => $categories[array_rand($categories)],
                    'tags'             => ['hotelaria', 'gestao'],
                    'visibility'       => 'public',
                    'status'           => 'published',
                    'created_at'       => now()->subDays(rand(0, 90)),
                ]);

                $likers = collect($this->allUsers)->random(rand(0, 15));
                foreach ($likers as $liker) {
                    VideoLike::firstOrCreate(['video_id' => $video->id, 'user_id' => $liker->id]);
                }

                $numComments = rand(0, 3);
                for ($c = 0; $c < $numComments; $c++) {
                    VideoComment::create([
                        'uuid'     => (string) Str::uuid(),
                        'video_id' => $video->id,
                        'user_id'  => $this->allUsers[array_rand($this->allUsers)]->id,
                        'content'  => ['Muito útil, obrigado!', 'Excelente conteúdo!', 'Vou aplicar no meu hotel!'][array_rand(['Muito útil, obrigado!', 'Excelente conteúdo!', 'Vou aplicar no meu hotel!'])],
                    ]);
                }

                $video->update([
                    'likes_count'    => $video->likes()->count(),
                    'comments_count' => $video->comments()->count(),
                    'views_count'    => rand(50, 2000),
                    'shares_count'   => rand(0, 40),
                ]);
            }
        }
    }

    // ──────────────────────────────────────────────────────────
    // Courses
    // ──────────────────────────────────────────────────────────

    private function createCourses(): void
    {
        $courseTitles = [
            'Fundamentos de Gestão Hoteleira', 'Revenue Management na Prática', 'Excelência no Atendimento ao Hóspede',
            'Liderança de Equipes na Hotelaria', 'Marketing Digital para Hotéis',
        ];
        $levels = ['beginner', 'intermediate', 'advanced'];

        foreach ($this->creators as $creator) {
            $numCourses = rand(1, 2);
            for ($c = 0; $c < $numCourses; $c++) {
                $course = Course::create([
                    'uuid'           => (string) Str::uuid(),
                    'user_id'        => $creator->id,
                    'title'          => $courseTitles[array_rand($courseTitles)],
                    'description'    => 'Curso completo com aulas práticas para elevar o nível da sua operação hoteleira.',
                    'thumbnail_path' => $this->storeImage($this->bannerUrl('course-' . $creator->id . '-' . $c), 'courses/thumbnails'),
                    'category'       => 'Hotelaria',
                    'level'          => $levels[array_rand($levels)],
                    'price_cents'    => rand(0, 100) < 40 ? 0 : rand(50, 400) * 100,
                    'status'         => 'published',
                    'created_at'     => now()->subDays(rand(0, 120)),
                ]);

                $numLessons = rand(3, 6);
                for ($l = 0; $l < $numLessons; $l++) {
                    CourseLesson::create([
                        'uuid'             => (string) Str::uuid(),
                        'course_id'        => $course->id,
                        'title'            => "Aula " . ($l + 1) . ": Módulo prático",
                        'description'      => 'Conteúdo direto ao ponto, com exemplos reais do dia a dia hoteleiro.',
                        'content'          => 'Texto de apoio da aula, com os principais pontos abordados no vídeo.',
                        'video_url'        => null,
                        'position'         => $l + 1,
                        'duration_seconds' => rand(300, 900),
                    ]);
                }

                $enrolled = collect($this->allUsers)->random(rand(0, 12));
                foreach ($enrolled as $student) {
                    CourseEnrollment::firstOrCreate(['course_id' => $course->id, 'user_id' => $student->id]);
                }

                $course->update([
                    'lessons_count'     => $numLessons,
                    'enrollments_count' => $enrolled->count(),
                ]);
            }
        }
    }

    // ──────────────────────────────────────────────────────────
    // Mentorships
    // ──────────────────────────────────────────────────────────

    private function createMentorships(): void
    {
        $titles = ['Mentoria de Carreira em Hotelaria', 'Consultoria de Revenue Management', 'Sessão de Liderança e Gestão de Equipes'];

        foreach ($this->creators as $creator) {
            $numMentorships = rand(1, 2);
            for ($m = 0; $m < $numMentorships; $m++) {
                $mentorship = Mentorship::create([
                    'uuid'             => (string) Str::uuid(),
                    'user_id'          => $creator->id,
                    'title'            => $titles[array_rand($titles)],
                    'description'      => 'Sessão individual para destravar desafios reais da sua operação ou carreira.',
                    'category'         => 'Hotelaria',
                    'duration_minutes' => [30, 45, 60][array_rand([30, 45, 60])],
                    'session_type'     => rand(0, 1) ? 'online' : 'presential',
                    'price_cents'      => rand(0, 100) < 30 ? 0 : rand(80, 500) * 100,
                    'status'           => 'published',
                    'created_at'       => now()->subDays(rand(0, 90)),
                ]);

                $bookers = collect($this->allUsers)->random(rand(0, 6));
                $count = 0;
                foreach ($bookers as $booker) {
                    MentorshipBooking::create([
                        'uuid'          => (string) Str::uuid(),
                        'mentorship_id' => $mentorship->id,
                        'user_id'       => $booker->id,
                        'message'       => 'Gostaria de agendar uma sessão para discutir desafios da minha operação.',
                        'scheduled_at'  => now()->addDays(rand(1, 20)),
                        'status'        => ['pending', 'confirmed', 'completed'][array_rand(['pending', 'confirmed', 'completed'])],
                    ]);
                    $count++;
                }

                $mentorship->update(['bookings_count' => $count]);
            }
        }
    }

    // ──────────────────────────────────────────────────────────
    // Reviews
    // ──────────────────────────────────────────────────────────

    private function createReviews(): void
    {
        $comments = [
            'Conteúdo excelente, recomendo muito!', 'Mudou a forma como enxergo a gestão do meu hotel.',
            'Profissional muito qualificado e didático.', 'Superou minhas expectativas.',
            'Aprendi bastante, valeu cada minuto.',
        ];

        foreach ($this->creators as $creator) {
            $reviewers = collect($this->allUsers)
                ->reject(fn (User $u) => $u->id === $creator->id)
                ->random(rand(3, 10));

            foreach ($reviewers as $reviewer) {
                Review::create([
                    'uuid'            => (string) Str::uuid(),
                    'reviewable_type' => User::class,
                    'reviewable_id'   => $creator->id,
                    'user_id'         => $reviewer->id,
                    'rating'          => rand(3, 5),
                    'comment'         => $comments[array_rand($comments)],
                ]);
            }

            foreach (Course::where('user_id', $creator->id)->get() as $course) {
                $courseReviewers = CourseEnrollment::where('course_id', $course->id)->inRandomOrder()->limit(rand(0, 5))->pluck('user_id');
                foreach ($courseReviewers as $userId) {
                    Review::create([
                        'uuid'            => (string) Str::uuid(),
                        'reviewable_type' => Course::class,
                        'reviewable_id'   => $course->id,
                        'user_id'         => $userId,
                        'rating'          => rand(3, 5),
                        'comment'         => $comments[array_rand($comments)],
                    ]);
                }
            }
        }
    }
}
