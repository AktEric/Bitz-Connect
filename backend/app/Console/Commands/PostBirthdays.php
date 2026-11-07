<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\WorkspacePost;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Publica no mural, todo dia, os parabéns de quem faz aniversário (só de quem deixou o aniversário visível). */
class PostBirthdays extends Command
{
    protected $signature = 'workspace:birthday-posts';

    protected $description = 'Cria no mural a publicação de parabéns dos aniversariantes do dia.';

    public function handle(): int
    {
        $today = CarbonImmutable::now('America/Sao_Paulo');
        $count = 0;

        $members = HotelUser::with('user')->where('status', 'approved')->where('birthday_visible', true)->whereNotNull('birth_date')
            ->whereMonth('birth_date', $today->month)->whereDay('birth_date', $today->day)->get();

        foreach ($members as $m) {
            if (! $m->user || ! Hotel::whereKey($m->hotel_id)->exists()) {
                continue;
            }
            $exists = WorkspacePost::where('hotel_id', $m->hotel_id)->where('kind', 'birthday')->where('subject_id', $m->user_id)
                ->whereDate('subject_date', $today->toDateString())->exists();
            if ($exists) {
                continue;
            }
            $first = Str::before($m->user->name, ' ');
            WorkspacePost::create([
                'uuid' => (string) Str::uuid(), 'hotel_id' => $m->hotel_id,
                'author_id' => $m->user_id, 'subject_id' => $m->user_id, 'subject_date' => $today->toDateString(),
                'kind' => 'birthday', 'pinned' => false,
                'body' => "Hoje é aniversário de {$first}! 🎂 Deixe sua mensagem de parabéns.",
            ]);
            $count++;
        }

        $this->info("{$count} publicação(ões) de aniversário criadas.");

        return self::SUCCESS;
    }
}
