<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\WorkspacePost;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** O perfil da empresa dá boas-vindas, no mural, a quem foi admitido (menciona o setor e o cargo). Uma vez por pessoa. */
class PostWelcomes extends Command
{
    protected $signature = 'workspace:welcome-posts {--days=3 : janela de recuperação, em dias, para quem a rotina não pegou no dia}';

    protected $description = 'Cria no mural a publicação de boas-vindas dos novos colaboradores.';

    public function handle(): int
    {
        $today = CarbonImmutable::now('America/Sao_Paulo')->startOfDay();
        $from  = $today->subDays(max(0, (int) $this->option('days')));
        $count = 0;

        $members = HotelUser::with(['user', 'department'])->where('status', 'approved')
            ->whereDate('hired_at', '>=', $from->toDateString())->whereDate('hired_at', '<=', $today->toDateString())->get();

        foreach ($members as $m) {
            $hotel = Hotel::find($m->hotel_id);
            if (! $m->user || ! $hotel) {
                continue;
            }
            if (WorkspacePost::where('hotel_id', $m->hotel_id)->where('kind', 'welcome')->where('subject_id', $m->user_id)->exists()) {
                continue;
            }
            $where = trim(($m->department ? "ao time de {$m->department->name}" : 'à empresa') . ($m->job_title ? ", como {$m->job_title}" : ''));
            WorkspacePost::create([
                'uuid' => (string) Str::uuid(), 'hotel_id' => $m->hotel_id,
                'author_id' => $m->user_id, 'subject_id' => $m->user_id, 'subject_date' => $m->hired_at->toDateString(),
                'kind' => 'welcome', 'pinned' => false, 'as_company' => true,
                'body' => "Seja muito bem-vindo(a) à {$hotel->name}, @[{$m->user->name}]({$m->user->uuid})! 🎉 Você chega {$where}. Que a gente construa muita coisa boa juntos. Deixe seu abraço de boas-vindas nos comentários! 👋",
            ]);
            $count++;
        }

        $this->info("{$count} publicação(ões) de boas-vindas criadas.");

        return self::SUCCESS;
    }
}
