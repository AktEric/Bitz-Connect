<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pesquisa de clima anônima. As respostas nunca guardam quem respondeu; só a lista de quem já participou
 * (com a data, sem hora). Resultados só aparecem com no mínimo MIN_RESPONSES respostas, e por setor só se o setor
 * tiver esse mínimo — para ninguém ser identificado pela própria resposta.
 */
class ClimateController extends WorkspaceBaseController
{
    public const MIN_RESPONSES = 5;

    /** Perguntas padrão: eNPS 0–10 e três afirmações de 1–5. Cada pesquisa guarda as suas (editáveis ao abrir). */
    public const QUESTIONS = [
        'nps'          => ['label' => 'De 0 a 10, o quanto você recomendaria esta empresa como lugar para trabalhar?', 'type' => 'nps', 'min' => 0, 'max' => 10],
        'recognition'  => ['label' => 'Sinto que o meu trabalho é reconhecido.', 'type' => 'scale', 'min' => 1, 'max' => 5],
        'clarity'      => ['label' => 'Tenho clareza do que se espera de mim.', 'type' => 'scale', 'min' => 1, 'max' => 5],
        'manager'      => ['label' => 'Meu gestor me apoia no dia a dia.', 'type' => 'scale', 'min' => 1, 'max' => 5],
    ];

    /** Perguntas da pesquisa (chave => definição); pesquisas antigas, sem perguntas gravadas, usam as padrão. */
    private function questionsOf(object $s): array
    {
        $saved = $s->questions ? json_decode($s->questions, true) : null;
        if (! $saved) {
            return self::QUESTIONS;
        }

        return collect($saved)->mapWithKeys(fn ($q) => [$q['key'] => [
            'label' => $q['label'], 'type' => $q['type'],
            'min' => $q['type'] === 'nps' ? 0 : 1, 'max' => $q['type'] === 'nps' ? 10 : 5,
        ]])->all();
    }

    private function listOf(array $questions): array
    {
        return collect($questions)->map(fn ($q, $k) => ['key' => $k] + $q)->values()->all();
    }

    private function canSeeResults(?HotelUser $m): bool
    {
        return $m && $this->access->canSeeDashboard($m);
    }

    private function isOpen(object $s): bool
    {
        return $s->status === 'open' && (! $s->closes_on || \Illuminate\Support\Carbon::parse($s->closes_on)->endOfDay()->isFuture());
    }

    private function find(Hotel $hotel, string $uuid): ?object
    {
        return DB::table('climate_surveys')->where('hotel_id', $hotel->id)->where('uuid', $uuid)->first();
    }

    /** GET /workspaces/{slug}/climate */
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $see = $this->canSeeResults($m);
        $mine = DB::table('climate_participations')->where('user_id', $request->user()->id)->pluck('climate_survey_id')->flip();
        $items = DB::table('climate_surveys')->where('hotel_id', $hotel->id)->orderByDesc('id')->limit(30)->get()
            ->filter(fn ($s) => $see || $this->isOpen($s))
            ->map(fn ($s) => [
                'uuid' => $s->uuid, 'title' => $s->title, 'status' => $this->isOpen($s) ? 'open' : 'closed', 'closes_on' => $s->closes_on,
                'answered' => $mine->has($s->id), 'questions' => $this->listOf($this->questionsOf($s)),
                'responses' => $see ? DB::table('climate_answers')->where('climate_survey_id', $s->id)->count() : null,
            ])->values();

        return $this->success('Pesquisas carregadas.', [
            'items' => $items, 'can_manage' => $this->access->isAdmin($m), 'can_see_results' => $see,
            'questions' => $this->listOf(self::QUESTIONS),
            'min_responses' => self::MIN_RESPONSES,
        ]);
    }

    /** POST /workspaces/{slug}/climate  { title, closes_on? } — RH/dono; uma pesquisa aberta por vez. */
    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m || ! $this->access->isAdmin($m)) {
            return $this->forbidden('Somente o dono ou o RH abrem pesquisas de clima.');
        }
        $data = $request->validate(['title' => ['required', 'string', 'min:4', 'max:120'], 'closes_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'questions' => ['nullable', 'array', 'min:1', 'max:8'], 'questions.*.label' => ['required', 'string', 'min:8', 'max:160'],
            'questions.*.type' => ['required', 'in:nps,scale'],
        ]);
        $custom = collect($data['questions'] ?? [])->values()->map(fn ($q, $i) => ['key' => 'q' . ($i + 1), 'label' => trim($q['label']), 'type' => $q['type']]);
        if ($custom->where('type', 'nps')->count() > 1) {
            return $this->error('Use no máximo uma pergunta de indicação (0 a 10).', ['questions' => ['Só pode haver uma pergunta de indicação.']], 422);
        }
        $open = DB::table('climate_surveys')->where('hotel_id', $hotel->id)->where('status', 'open')->get()->first(fn ($s) => $this->isOpen($s));
        if ($open) {
            return $this->error('Já existe uma pesquisa aberta. Encerre-a antes de abrir outra.', [], 409);
        }
        $id = DB::table('climate_surveys')->insertGetId([
            'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'created_by' => $request->user()->id, 'title' => trim($data['title']),
            'status' => 'open', 'closes_on' => $data['closes_on'] ?? null,
            'questions' => json_encode($custom->isNotEmpty() ? $custom->all() : $this->listOf(array_map(fn ($q) => $q, self::QUESTIONS))),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLog::record($request, $hotel, 'climate.opened', null, ['survey' => $id]);

        foreach (HotelUser::with('user')->where('hotel_id', $hotel->id)->where('status', 'approved')->get() as $hu) {
            if ($hu->user && $hu->user_id !== $request->user()->id) {
                $this->notifications->workspaceNotice($hu->user, $request->user(), 'workspace_climate', 'Pesquisa de clima aberta', trim($data['title']) . ' — leva 1 minuto e é anônima.', $this->link($hotel, 'pesquisas'));
            }
        }

        return $this->created('Pesquisa aberta.', ['uuid' => DB::table('climate_surveys')->where('id', $id)->value('uuid')]);
    }

    /** POST /workspaces/{slug}/climate/{uuid}/close */
    public function close(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $s = $m ? $this->find($hotel, $uuid) : null;
        if (! $s || ! $this->access->isAdmin($m)) {
            return $this->notFound('Pesquisa não encontrada.');
        }
        DB::table('climate_surveys')->where('id', $s->id)->update(['status' => 'closed', 'updated_at' => now()]);
        AuditLog::record($request, $hotel, 'climate.closed', null, ['survey' => $s->id]);

        return $this->success('Pesquisa encerrada.', null);
    }

    /** POST /workspaces/{slug}/climate/{uuid}/answer  { scores{}, comment? } */
    public function answer(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $s = $m ? $this->find($hotel, $uuid) : null;
        if (! $s || ! $this->isOpen($s)) {
            return $this->notFound('Pesquisa não encontrada ou encerrada.');
        }
        $rules = ['comment' => ['nullable', 'string', 'max:500'], 'scores' => ['required', 'array']];
        $questions = $this->questionsOf($s);
        foreach ($questions as $k => $q) {
            $rules["scores.{$k}"] = ['required', 'integer', "between:{$q['min']},{$q['max']}"];
        }
        $data = $request->validate($rules);

        $done = DB::transaction(function () use ($s, $request, $m, $data, $questions) {
            $inserted = DB::table('climate_participations')->insertOrIgnore([
                'climate_survey_id' => $s->id, 'user_id' => $request->user()->id, 'answered_on' => now()->toDateString(),
            ]);
            if (! $inserted) {
                return false;
            }
            DB::table('climate_answers')->insert([
                'climate_survey_id' => $s->id, 'department_id' => $m->department_id,
                'scores' => json_encode(array_intersect_key($data['scores'], $questions)),
                'comment' => ($c = trim((string) ($data['comment'] ?? ''))) === '' ? null : $c,
            ]);

            return true;
        });
        if (! $done) {
            return $this->error('Você já respondeu esta pesquisa.', [], 409);
        }

        return $this->created('Obrigado! Sua resposta foi registrada de forma anônima.', null);
    }

    /** @param \Illuminate\Support\Collection $rows linhas de climate_answers */
    private function summarize($rows, array $questions): array
    {
        $scores = $rows->map(fn ($r) => json_decode($r->scores, true));
        $npsKey = collect($questions)->search(fn ($q) => $q['type'] === 'nps');
        $n = $rows->count();
        $enps = null;
        if ($npsKey !== false && $n) {
            $promoters  = $scores->filter(fn ($s) => ($s[$npsKey] ?? -1) >= 9)->count();
            $detractors = $scores->filter(fn ($s) => isset($s[$npsKey]) && $s[$npsKey] <= 6)->count();
            $enps = (int) round((($promoters - $detractors) / $n) * 100);
        }

        return [
            'responses' => $n,
            'enps'      => $enps,
            'averages'  => collect($questions)->where('type', 'scale')->map(fn ($q, $k) => round($scores->avg($k) ?? 0, 2))->all(),
        ];
    }

    private function norm(string $label): string
    {
        return mb_strtolower(trim($label));
    }

    /** GET /workspaces/{slug}/climate/{uuid}/results — dono, RH e diretoria; com proteção de anonimato. */
    public function results(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $s = $m && $this->canSeeResults($m) ? $this->find($hotel, $uuid) : null;
        if (! $s) {
            return $this->notFound('Pesquisa não encontrada.');
        }
        $questions = $this->questionsOf($s);
        $rows = DB::table('climate_answers')->where('climate_survey_id', $s->id)->get();
        $participants = DB::table('climate_participations')->where('climate_survey_id', $s->id)->count();
        $eligible = HotelUser::where('hotel_id', $hotel->id)->where('status', 'approved')->count();
        $base = ['uuid' => $s->uuid, 'title' => $s->title, 'questions' => $this->listOf($questions),
            'participation' => ['answered' => $participants, 'eligible' => $eligible], 'min_responses' => self::MIN_RESPONSES];

        if ($rows->count() < self::MIN_RESPONSES) {
            return $this->success('Resultados indisponíveis.', $base + ['hidden' => true, 'summary' => null, 'departments' => [], 'comments' => [], 'comparison' => null, 'history' => []]);
        }
        $names = DB::table('departments')->where('hotel_id', $hotel->id)->pluck('name', 'id');
        $depts = $rows->groupBy('department_id')->filter(fn ($g) => $g->count() >= self::MIN_RESPONSES)
            ->map(fn ($g, $id) => ['name' => $names[$id] ?? 'Sem setor'] + $this->summarize($g, $questions))->values();
        $comments = $rows->pluck('comment')->filter()->shuffle()->values(); // sem ordem, sem autoria
        $summary = $this->summarize($rows, $questions);

        // Evolução: outras pesquisas da empresa que também atingiram o mínimo de respostas.
        $history = []; $comparison = null; $previous = null;
        foreach (DB::table('climate_surveys')->where('hotel_id', $hotel->id)->orderByDesc('id')->limit(12)->get()->reverse() as $o) {
            $orows = DB::table('climate_answers')->where('climate_survey_id', $o->id)->get();
            if ($orows->count() < self::MIN_RESPONSES) {
                continue;
            }
            $oq = $this->questionsOf($o);
            $osum = $this->summarize($orows, $oq);
            $history[] = ['uuid' => $o->uuid, 'title' => $o->title, 'enps' => $osum['enps'], 'responses' => $osum['responses'], 'current' => $o->id === $s->id];
            if ($o->id < $s->id) {
                $previous = [$o, $oq, $osum];   // a mais recente anterior a esta
            }
        }
        if ($previous) {
            [$po, $pq, $psum] = $previous;
            $byLabel = collect($pq)->mapWithKeys(fn ($q, $k) => [$this->norm($q['label']) => $k]);
            $items = [];
            foreach ($questions as $k => $q) {
                $pk = $byLabel[$this->norm($q['label'])] ?? null;
                if ($q['type'] === 'scale' && $pk && isset($psum['averages'][$pk])) {
                    $items[] = ['key' => $k, 'label' => $q['label'], 'current' => $summary['averages'][$k], 'previous' => $psum['averages'][$pk],
                        'delta' => round($summary['averages'][$k] - $psum['averages'][$pk], 2)];
                }
            }
            $comparison = ['title' => $po->title, 'enps' => ['current' => $summary['enps'], 'previous' => $psum['enps'],
                'delta' => $summary['enps'] !== null && $psum['enps'] !== null ? $summary['enps'] - $psum['enps'] : null], 'items' => $items];
        }

        return $this->success('Resultados carregados.', $base + ['hidden' => false, 'summary' => $summary, 'departments' => $depts, 'comments' => $comments, 'comparison' => $comparison, 'history' => $history]);
    }
}
