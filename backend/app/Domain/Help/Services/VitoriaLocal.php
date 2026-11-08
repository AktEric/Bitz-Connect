<?php

namespace App\Domain\Help\Services;

/**
 * Motor LOCAL da Vitória: sem IA externa e sem custo.
 *
 * - Papo simples (oi, tudo bem?, obrigado, tchau, quem é você) por padrões.
 * - Dúvidas sobre o sistema: busca (BM25) na base resources/help/vitoria.md, com sinônimos,
 *   tolerância a erro de digitação e acentos. Responde com o trecho da base, sem inventar.
 */
class VitoriaLocal
{
    private const STOPWORDS = [
        'a','o','as','os','um','uma','uns','umas','de','da','do','das','dos','em','no','na','nos','nas','por','para','pra','pro',
        'com','sem','sobre','e','ou','que','se','eu','me','meu','minha','meus','minhas','voce','vc','ele','ela','isso','isto',
        'esse','essa','este','esta','aqui','la','ai','ao','aos','como','qual','quais','onde','quando','quem','quanto','porque',
        'ser','estar','ter','foi','sao','esta','estou','tem','tenho','posso','pode','podem','consigo','conseguir','quero','queria',
        'gostaria','preciso','precisa','fazer','faco','faz','vejo','ver','mais','muito','tambem','ja','nao','sim','la','favor',
        'ola','oi','ajuda','ajudar','sistema','empresa','dentro','quer','sei','saber','existe','mim','nosso','nossa','algum','alguma',
        'qualquer','tudo','todo','toda','todos','todas','cada','pelo','pela','num','numa','dele','dela',
        'criar','crio','cria','fica','ficam','ficar','fico','vou','vai','vamos','tipo','coisa','coisas','tela','pagina','botao','clicar','clico','acho',
        'encontro','encontrar','achar','acessar','entrar','abrir','abro','usar','uso','utilizar','funciona','funcionar',
    ];

    /** Grupos de palavras com o mesmo sentido (já sem acento). */
    private const SYNONYMS = [
        ['organograma', 'hierarquia', 'estrutura', 'chefia', 'subordinado', 'subordinada', 'reporta', 'lideranca'],
        ['postar', 'publicar', 'publicacao', 'post', 'mural', 'comunicado', 'enquete'],
        ['perfil', 'foto', 'bio', 'avatar', 'dados', 'cadastral'],
        ['pessoas', 'funcionario', 'funcionarios', 'colaborador', 'colaboradores', 'equipe', 'time', 'colega', 'colegas', 'membros'],
        ['convite', 'convidar', 'adicionar', 'cadastrar', 'incluir', 'contratar', 'novo'],
        ['ponto', 'bater', 'marcar', 'marcacao', 'batida', 'jornada', 'expediente', 'banco', 'horas'],
        ['ferias', 'solicitacao', 'solicitacoes', 'afastamento', 'folga', 'chamado', 'ticket', 'reembolso'],
        ['holerite', 'contracheque', 'pagamento', 'recebimento', 'recebimentos', 'salario', 'comprovante'],
        ['clima', 'pesquisa', 'enps', 'satisfacao'],
        ['vaga', 'vagas', 'candidatar', 'candidatura', 'oportunidade', 'emprego'],
        ['senha', 'seguranca', 'autenticacao', 'etapas', 'login', 'sessao', 'conectado', 'deslogar', 'sair'],
        ['setor', 'setores', 'departamento', 'area', 'areas'],
        ['meta', 'metas', 'objetivo', 'objetivos'],
        ['pdi', 'desenvolvimento', 'carreira'],
        ['calendario', 'agenda', 'evento', 'eventos', 'feriado', 'feriados', 'aniversario'],
        ['chat', 'mensagem', 'mensagens', 'conversa', 'conversar', 'grupo', 'grupos'],
        ['curso', 'cursos', 'video', 'videos', 'mentoria', 'mentorias', 'consultor'],
        ['aprovar', 'aprovacao', 'aprovacoes', 'recusar', 'decidir'],
        ['relatorio', 'relatorios', 'painel', 'diretoria', 'dashboard'],
        ['papel', 'papeis', 'permissao', 'permissoes', 'acesso', 'cargo', 'dono', 'rh'],
        ['auditoria', 'historico', 'log'],
    ];

    /** Seções da base que não servem como resposta. */
    private const SKIP_SECTIONS = ['Como responder', 'Base de conhecimento da Vitória (central de ajuda)'];

    /** @var array<int, array{heading: string, text: string, tokens: array<int, string>}>|null */
    private ?array $chunks = null;
    /** @var array<string, int> */
    private array $df = [];
    private float $avgLen = 1.0;

    public function __construct(private readonly ?string $knowledgePath = null) {}

    /** Resposta para a conversa. $roleNote = papel da pessoa na empresa (opcional). */
    public function reply(string $message, ?string $roleNote = null): string
    {
        $small = $this->smallTalk($message);
        if ($small !== null) {
            return $small;
        }

        $hits = $this->search($message);
        if (! $hits) {
            return "Hmm, não encontrei isso aqui na minha base. 😕 Tenta me perguntar de outro jeito, por exemplo: \"como monto o organograma?\", \"como edito meu perfil?\" ou \"o que posso postar no mural?\".\n\nSe for algo específico da sua empresa, fale com o RH ou abra um chamado em Férias e solicitações.";
        }

        $out = [];
        foreach ($hits as $h) {
            // Itens que já começam com o próprio título em negrito dispensam o título da seção.
            $out[] = str_starts_with($h['text'], '**') ? $h['text'] : '**' . $h['heading'] . '**' . "\n" . $h['text'];
        }
        $reply = implode("\n\n", $out);

        if ($roleNote && preg_match('/\b(posso|pode|podem|consigo|permit|proibid|autoriz|acesso|permiss)/u', mb_strtolower($message))) {
            $reply .= "\n\n" . $roleNote;
        }

        return $reply;
    }

    // ───────────────────────── Papo simples ─────────────────────────

    private function smallTalk(string $message): ?string
    {
        $t = $this->normalize($message);
        $words = preg_split('/\s+/', trim($t), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (! $words) {
            return null;
        }
        // Só tratamos como papo quando a mensagem é curta; perguntas longas vão para a busca.
        $short = count($words) <= 7;

        $has = fn (string $re): bool => (bool) preg_match($re, $t);

        if ($short && $has('/\b(quem e voce|quem eh voce|qual (e )?(o )?seu nome|como (voce )?(se )?chama|voce e (um )?(robo|ia|humana|real)|voce e a vitoria)\b/')) {
            return 'Eu sou a Vitória, a assistente virtual da central de ajuda. 😊 Conheço o sistema e posso te explicar como fazer as coisas — organograma, perfil, ponto, mural, solicitações e muito mais.';
        }
        if ($short && $has('/\b(o que (voce )?(sabe|faz|pode)|em que (voce )?(pode|consegue) ajudar|como (voce )?(pode|consegue) ajudar|me ajuda|preciso de ajuda)\b/') && count($words) <= 6) {
            return 'Posso te explicar como usar o sistema: montar o organograma, ver as pessoas da empresa, editar o perfil, bater o ponto, pedir férias, usar o mural e o que cada papel pode fazer. É só perguntar do seu jeito. 😉';
        }
        if ($short && $has('/\b(obrigad[oa]|valeu|vlw|brigad[oa]|muito obrigad[oa]|agradeco|show de bola)\b/')) {
            return $this->pick(['Por nada! Qualquer dúvida, é só chamar. 😊', 'De nada! Estou por aqui se precisar de mais alguma coisa.', 'Imagina! Fico feliz em ajudar. 💜']);
        }
        if ($short && $has('/\b(tchau|ate mais|ate logo|falou|flw|ate a proxima|encerrar)\b/')) {
            return $this->pick(['Até mais! Quando precisar, é só abrir a ajuda. 👋', 'Tchau! Qualquer dúvida, estou aqui.']);
        }
        if ($short && $has('/\b(tudo bem|tudo bom|tudo certo|como vai|como voce esta|como voce ta|como esta voce|como tu esta|td bem|blz|beleza|e ai|como anda)\b/')) {
            return $this->pick([
                'Tudo ótimo por aqui, obrigada por perguntar! 😊 E você, como está? Posso te ajudar com alguma coisa do sistema?',
                'Tudo bem sim! E com você? Se tiver alguma dúvida sobre o sistema, pode mandar.',
            ]);
        }
        if ($short && $has('/\b(bom dia|boa tarde|boa noite|oi+|ola+|opa|eae|eai|hey|hello|salve|fala ai|oie)\b/') && count($words) <= 4) {
            return $this->pick([
                'Oi! Tudo bem? 😊 Sou a Vitória. Como posso te ajudar hoje?',
                'Olá! Que bom te ver por aqui. Em que posso ajudar?',
            ]);
        }

        return null;
    }

    private function pick(array $options): string
    {
        return $options[array_rand($options)];
    }

    // ───────────────────────── Busca na base ─────────────────────────

    /** @return array<int, array{heading: string, text: string}> */
    private function search(string $message): array
    {
        $this->load();
        if (! $this->chunks) {
            return [];
        }

        $weights = $this->queryWeights($message);
        if (! $weights) {
            return [];
        }

        $n = count($this->chunks);
        $k1 = 1.4;
        $b = 0.75;
        $scored = [];

        foreach ($this->chunks as $i => $c) {
            $tf = array_count_values($c['tokens']);
            $len = max(1, count($c['tokens']));
            $score = 0.0;
            foreach ($weights as $term => $w) {
                if (! isset($tf[$term])) {
                    continue;
                }
                $df = $this->df[$term] ?? 0;
                $idf = log(1 + ($n - $df + 0.5) / ($df + 0.5));
                $f = $tf[$term];
                $score += $w * $idf * (($f * ($k1 + 1)) / ($f + $k1 * (1 - $b + $b * $len / $this->avgLen)));
            }
            // Título que combina com a pergunta vale mais.
            // Título do próprio item (o trecho em negrito no início) pesa bastante; o título da seção, pouco.
            $titleTokens = $this->tokens($c['title']);
            $headTokens  = $this->tokens($c['heading']);
            foreach ($weights as $term => $w) {
                if (in_array($term, $titleTokens, true)) {
                    $score += $w * 2.2;
                } elseif (in_array($term, $headTokens, true)) {
                    $score += $w * 0.3;
                }
            }
            if ($score > 0) {
                $scored[$i] = $score;
            }
        }

        if (! $scored) {
            return [];
        }
        arsort($scored);
        $top = (float) reset($scored);
        if ($top < 1.6) {
            return [];
        }

        // Pergunta longa em que o melhor trecho só bate com uma palavra solta = fora do assunto (não inventar resposta).
        $direct = array_keys(array_filter($weights, fn ($w) => $w >= 1.0));
        if (count($direct) >= 3) {
            $topTokens = array_flip($this->chunks[array_key_first($scored)]['tokens']);
            $matched = count(array_filter($direct, fn ($t) => isset($topTokens[$t])));
            if ($matched / count($direct) < 0.4) {
                return [];
            }
        }

        $hits = [];
        foreach ($scored as $i => $s) {
            if (count($hits) >= 2 || $s < $top * 0.62) {
                break;
            }
            $hits[] = ['heading' => $this->chunks[$i]['heading'], 'text' => $this->chunks[$i]['text']];
        }

        return $hits;
    }

    /** @return array<string, float> termo (já normalizado) => peso */
    private function queryWeights(string $message): array
    {
        $vocab = $this->df;
        $weights = [];
        foreach ($this->tokens($message, false) as $raw) {
            $stem = $this->stem($raw);
            if (! isset($vocab[$stem])) {
                $fix = $this->nearest($stem, $vocab);
                if ($fix !== null) {
                    $stem = $fix;
                }
            }
            $weights[$stem] = max($weights[$stem] ?? 0, 1.0);

            foreach (self::SYNONYMS as $group) {
                if (in_array($raw, $group, true) || in_array($stem, array_map([$this, 'stem'], $group), true)) {
                    foreach ($group as $syn) {
                        $s = $this->stem($syn);
                        $weights[$s] = max($weights[$s] ?? 0, 0.45);
                    }
                }
            }
        }

        return $weights;
    }

    /** Corrige erro de digitação: palavra do vocabulário a até 1–2 letras de distância. */
    private function nearest(string $term, array $vocab): ?string
    {
        $len = strlen($term);
        if ($len < 4) {
            return null;
        }
        $max = $len >= 7 ? 2 : 1;
        $best = null;
        $bestD = $max + 1;
        foreach (array_map('strval', array_keys($vocab)) as $v) {
            // Erro de digitação raramente muda a primeira letra (evita "monto" virar "ponto").
            if ($v[0] !== $term[0] || abs(strlen($v) - $len) > $max) {
                continue;
            }
            $d = levenshtein($term, $v);
            if ($d < $bestD) {
                $bestD = $d;
                $best = $v;
            }
        }

        return $bestD <= $max ? $best : null;
    }

    // ───────────────────────── Base de conhecimento ─────────────────────────

    private function load(): void
    {
        if ($this->chunks !== null) {
            return;
        }
        $this->chunks = [];
        $path = $this->knowledgePath ?? resource_path('help/vitoria.md');
        if (! is_file($path)) {
            return;
        }

        $heading = '';
        $current = null;
        $flush = function () use (&$current, &$heading) {
            if ($current !== null && trim($current) !== '' && ! in_array($heading, self::SKIP_SECTIONS, true)) {
                $text  = $this->clean($current);
                $title = preg_match('/^\*\*([^*]+)\*\*/u', $text, $mt) ? $mt[1] : $heading;
                $this->chunks[] = ['heading' => $heading, 'title' => $title, 'text' => $text, 'tokens' => []];
            }
            $current = null;
        };

        foreach (preg_split('/\R/u', (string) file_get_contents($path)) ?: [] as $line) {
            if (str_starts_with($line, '## ')) {
                $flush();
                $heading = trim(substr($line, 3));
                continue;
            }
            if (str_starts_with($line, '# ') || str_starts_with($line, '> ')) {
                continue;
            }
            if (preg_match('/^- /u', $line)) {
                $flush();
                $current = substr($line, 2);
                continue;
            }
            if (trim($line) === '') {
                continue;
            }
            // linha solta (parágrafo): vira trecho próprio
            if ($current === null || ! preg_match('/^\s/u', $line)) {
                $flush();
                $current = $line;
                continue;
            }
            $current .= "\n" . trim($line);
        }
        $flush();

        $total = 0;
        foreach ($this->chunks as $i => $c) {
            // o título da seção também entra na busca (com o texto do item)
            $tokens = $this->tokens($c['text']);
            $this->chunks[$i]['tokens'] = $tokens;
            $total += count($tokens);
            foreach (array_unique($tokens) as $t) {
                $this->df[$t] = ($this->df[$t] ?? 0) + 1;
            }
        }
        $this->avgLen = max(1.0, $total / max(1, count($this->chunks)));
    }

    private function clean(string $text): string
    {
        return trim(preg_replace('/[ \t]+/u', ' ', $text) ?? $text);
    }

    // ───────────────────────── Texto ─────────────────────────

    /** @return array<int, string> tokens normalizados (sem acento, sem stopwords, com radical) */
    private function tokens(string $text, bool $stem = true): array
    {
        $out = [];
        foreach (preg_split('/\s+/', $this->normalize($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
            if (mb_strlen($w) < 2 || in_array($w, self::STOPWORDS, true)) {
                continue;
            }
            $out[] = $stem ? $this->stem($w) : $w;
        }

        return $out;
    }

    private function normalize(string $text): string
    {
        $t = mb_strtolower($text);
        $t = strtr($t, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);

        return trim(preg_replace('/[^a-z0-9\s]+/u', ' ', $t) ?? $t);
    }

    /** Radical bem simples (plural/verbo), igual para a pergunta e para a base. */
    private function stem(string $w): string
    {
        $w = strtr($w, ['coes' => 'cao', 'oes' => 'ao', 'aes' => 'ao', 'ais' => 'al', 'eis' => 'el']);
        if (strlen($w) > 4 && str_ends_with($w, 's')) {
            $w = substr($w, 0, -1);
        }
        foreach (['mente', 'ando', 'endo', 'indo', 'amos', 'ado', 'ada', 'ido', 'ida', 'ar', 'er', 'ir', 'ou', 'ei', 'am', 'em'] as $suf) {
            if (strlen($w) > strlen($suf) + 3 && str_ends_with($w, $suf)) {
                return substr($w, 0, -strlen($suf));
            }
        }
        // monto / monta / montar → mont ; colaboradore(s) → colaborador
        if (strlen($w) > 4 && in_array($w[-1], ['a', 'e', 'o'], true)) {
            $w = substr($w, 0, -1);
        }

        return $w;
    }
}
