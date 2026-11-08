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
        'fica','ficam','ficar','fico','vou','vai','vamos','tipo','coisa','coisas','tela','pagina','botao','clicar','clico','acho',
        'encontro','encontrar','achar','acessar','entrar','usar','uso','utilizar','funciona','funcionar',
        'alguem','outra','outro','outras','outros','gente','pessoal',
    ];

    /** Verbos de ação genéricos: ajudam a desempatar, mas valem menos que o assunto da pergunta. */
    private const SOFT = ['criar', 'crio', 'cria', 'abrir', 'abro', 'novo', 'nova', 'fazer', 'faco'];

    /** Grupos de palavras com o mesmo sentido (já sem acento). */
    private const SYNONYMS = [
        ['organograma', 'hierarquia', 'estrutura', 'chefia', 'subordinado', 'subordinada', 'reporta', 'lideranca'],
        ['postar', 'publicar', 'publicacao', 'post', 'mural', 'comunicado', 'enquete'],
        ['perfil', 'foto', 'bio', 'avatar', 'dados', 'cadastral'],
        ['pessoas', 'funcionario', 'funcionarios', 'colaborador', 'colaboradores', 'equipe', 'time', 'colega', 'colegas', 'membros'],
        ['convite', 'convidar', 'adicionar', 'cadastrar', 'cadastro', 'incluir', 'contratar', 'admitir', 'novo'],
        ['ponto', 'bater', 'marcar', 'marcacao', 'batida', 'jornada', 'expediente', 'banco', 'horas', 'horario', 'hora', 'extra', 'extras', 'atraso', 'falta', 'chegada', 'saida'],
        ['ajuste', 'ajustar', 'corrigir', 'correcao', 'errei', 'errado', 'erro', 'esqueci', 'esqueceu', 'consertar', 'retificar'],
        ['chefe', 'chefes', 'superior', 'lider', 'gestor', 'gerente', 'supervisor', 'coordenador'],
        ['2fa', 'autenticador', 'etapas', 'verificacao', 'duas', 'fatores'],
        ['conexao', 'conexoes', 'conectar', 'seguir', 'rede', 'networking', 'amigo', 'amizade'],
        ['ciencia', 'leu', 'leitura', 'lido', 'visualizou', 'confirmou', 'confirmar', 'obrigatoria'],
        ['lgpd', 'privacidade', 'exportar', 'exportacao', 'baixar', 'consentimento'],
        ['ferias', 'solicitacao', 'solicitacoes', 'afastamento', 'folga', 'chamado', 'ticket', 'reembolso', 'licenca', 'atestado', 'abono'],
        ['holerite', 'contracheque', 'pagamento', 'recebimento', 'recebimentos', 'salario', 'comprovante'],
        ['clima', 'pesquisa', 'enps', 'satisfacao'],
        ['vaga', 'vagas', 'candidatar', 'candidatura', 'oportunidade', 'emprego'],
        ['senha', 'seguranca', 'autenticacao', 'etapas', 'login', 'sessao', 'conectado', 'deslogar', 'sair'],
        ['setor', 'setores', 'departamento', 'area', 'areas'],
        ['meta', 'metas', 'objetivo', 'objetivos'],
        ['pdi', 'desenvolvimento', 'carreira'],
        ['calendario', 'agenda', 'evento', 'eventos', 'feriado', 'feriados', 'aniversario'],
        ['chat', 'mensagem', 'mensagens', 'conversa', 'conversar', 'grupo', 'grupos', 'mandar', 'enviar', 'falar', 'contato'],
        ['curso', 'cursos', 'video', 'videos', 'mentoria', 'mentorias', 'consultor'],
        ['aprovar', 'aprovacao', 'aprovacoes', 'recusar', 'decidir'],
        ['relatorio', 'relatorios', 'painel', 'diretoria', 'dashboard'],
        ['papel', 'papeis', 'permissao', 'permissoes', 'acesso', 'cargo', 'dono', 'rh', 'diferenca', 'nivel', 'niveis'],
        ['auditoria', 'historico', 'log'],
    ];

    /** Seções da base que não servem como resposta. */
    private const SKIP_SECTIONS = ['Como responder', 'Base de conhecimento da Vitória (central de ajuda)'];

    /** @var array<int, array{heading: string, text: string, tokens: array<int, string>}>|null */
    private ?array $chunks = null;
    /** @var array<string, int> */
    private array $df = [];
    private float $avgLen = 1.0;

    /** false quando a última pergunta não teve resposta na base (para registrar e melhorar a base depois). */
    public bool $answered = true;

    public function __construct(private readonly ?string $knowledgePath = null) {}

    private const OVERVIEW = "O sistema tem duas áreas:\n\n**Workspace** (a intranet e o RH da sua empresa)\n• **Meu dia**: Início, Ponto, Mural e Calendário.\n• **Meu RH**: Férias e solicitações, Holerites, Metas, Desenvolvimento e Pesquisa de clima.\n• **Empresa**: Pessoas, Organograma e Vagas.\n• Quem é gestor, RH ou dono também vê Aprovações, Ponto do time, Relatórios e outras telas de gestão.\n\n**Rede profissional**: feed, vagas, cursos, vídeos, mentorias, conexões e chat.\n\nMe diga o que você quer fazer — por exemplo \"bater o ponto\", \"pedir férias\" ou \"ver o organograma\" — e eu explico o passo a passo. 😊";

    /**
     * Resposta para a conversa.
     *
     * @param  string|null  $roleNote  papel da pessoa na empresa (opcional)
     * @param  array<int, array{role: string, content: string}>  $history  mensagens ANTERIORES à atual (memória da conversa)
     */
    public function reply(string $message, ?string $roleNote = null, array $history = []): string
    {
        $this->answered = true;
        $small = $this->smallTalk($message);
        if ($small !== null) {
            return $small;
        }

        $t = $this->normalize($message);
        $wordCount = count(preg_split('/\s+/', $t, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        // "Não entendi", "explica melhor", "mais simples": reexplica o último assunto de outro jeito.
        if ($wordCount <= 9 && preg_match('/\b(n|nao) (entendi|compreendi|saquei|ficou claro|consegui)|\b(explica|explique|explicar|detalha|detalhe)\b|\bmais simples\b|\bpasso a passo\b|\bpode repetir\b|\brepete\b|\bcomo assim\b|\bficou confuso\b|\bnao entendo\b|\bn entendo\b|\bme perdi\b|\bnao sei fazer\b/', $t)) {
            return $this->explainAgain($history);
        }

        // Visão geral / perguntas genéricas sobre o sistema.
        if ($wordCount <= 10 && preg_match('/\b(como funciona (o )?(sistema|workspace|plataforma|isso aqui)|o que (tem|existe|da pra fazer|posso fazer) (no|aqui|nesse|neste)|para que serve|pra que serve|visao geral|por onde (eu )?(comeco|começo|iniciar|inicio)|nao sei (por onde|o que fazer)|me (explica|ensina|apresenta) (o )?sistema|tour|primeira vez)\b/', $t)) {
            return self::OVERVIEW;
        }

        $hits = $this->search($message);

        // Pergunta curta de continuação ("e o perfil?", "e no celular?"): junta com o assunto anterior.
        if (! $hits && $wordCount <= 6) {
            $prev = $this->lastUserTopic($history);
            if ($prev !== null) {
                $hits = $this->search($prev . ' ' . $message);
            }
        }

        if (! $hits) {
            $this->answered = false;
            $near = $this->suggestions($message);
            if ($near) {
                return "Hmm, não encontrei isso exatamente. 🤔 Será que você quis saber sobre:\n" . implode("\n", array_map(fn ($t) => '• ' . $t, $near)) . "\n\nMe diga qual (ou escreva de outro jeito) que eu explico. Se for algo específico da sua empresa, fale com o RH ou abra um chamado em Férias e solicitações.";
            }

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

    // ───────────────────────── Memória da conversa ─────────────────────────

    /** Última pergunta da pessoa que teve resposta na base (ignora papo, "não entendi" etc.). */
    private function lastUserTopic(array $history): ?string
    {
        foreach (array_reverse($history) as $m) {
            if (($m['role'] ?? '') !== 'user') {
                continue;
            }
            $text = (string) ($m['content'] ?? '');
            if ($this->smallTalk($text) === null && $this->search($text)) {
                return $text;
            }
        }

        return null;
    }

    /** Reexplica a última resposta em passos curtos; se já foi explicado assim, resume e pergunta onde travou. */
    private function explainAgain(array $history): string
    {
        $topic = $this->lastUserTopic($history);
        $hit = $topic !== null ? ($this->search($topic)[0] ?? null) : null;
        if (! $hit) {
            return 'Claro, posso explicar melhor! 😊 Sobre qual assunto? Me diga o que você quer fazer — por exemplo "montar o organograma" ou "bater o ponto" — e eu explico passo a passo.';
        }

        $lastAssistant = '';
        foreach (array_reverse($history) as $m) {
            if (($m['role'] ?? '') === 'assistant') {
                $lastAssistant = (string) $m['content'];
                break;
            }
        }

        // Tira o título em negrito, o que está entre parênteses e a marcação; separa em frases curtas.
        $body = preg_replace('/^\*\*[^*]+\*\*:?\s*/u', '', $hit['text']) ?? $hit['text'];
        // Parênteses explicativos saem, mas os que trazem o caminho do menu (com "→") ficam.
        $body = preg_replace_callback('/\s*\(([^)]*)\)/u', fn ($m) => str_contains($m[1], '→') ? $m[0] : '', $body) ?? $body;
        $body = str_replace(['**', '*'], '', $body);
        $body = ltrim($body, " :\t");
        $body = mb_strtoupper(mb_substr($body, 0, 1)) . mb_substr($body, 1);
        $sentences = array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?])\s+|\n+/u', $body) ?: [])));

        if (str_contains($lastAssistant, 'passo a passo') && $sentences) {
            return "Vou resumir bem curtinho:\n\n" . $sentences[0] . "\n\nEm que parte você travou? Me conta que eu explico só ela. 😊";
        }

        $steps = [];
        foreach (array_slice($sentences, 0, 6) as $i => $s) {
            $steps[] = ($i + 1) . '. ' . $s;
        }

        return "Sem problema! Vou explicar de outro jeito, passo a passo:\n\n" . implode("\n", $steps) . "\n\nFicou mais claro? Se ainda tiver dúvida, me diga em que parte. 😊";
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

        [$weights, $cover] = $this->queryWeights($message);
        if (! $weights) {
            return [];
        }

        $scored = $this->scoreChunks($weights);
        if (! $scored) {
            return [];
        }
        $top = (float) reset($scored);
        if ($top < 1.6) {
            return [];
        }

        // Pergunta com 3+ palavras em que o melhor trecho cobre só uma delas (nem por sinônimo) = fora do assunto.
        if (count($cover) >= 3) {
            $topTokens = array_flip($this->chunks[array_key_first($scored)]['tokens']);
            $matched = count(array_filter($cover, function (array $alts) use ($topTokens) {
                foreach ($alts as $a) {
                    if (isset($topTokens[$a])) {
                        return true;
                    }
                }

                return false;
            }));
            if ($matched / count($cover) < 0.4) {
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

    /** Assuntos mais próximos da pergunta (para sugerir quando não há resposta certa). @return array<int, string> */
    private function suggestions(string $message): array
    {
        $this->load();
        [$weights] = $this->queryWeights($message);
        $scored = $weights ? $this->scoreChunks($weights) : [];
        $titles = [];
        foreach ($scored as $i => $s) {
            if ($s < 0.9 || count($titles) >= 3) {
                break;
            }
            $t = trim($this->chunks[$i]['title']);
            if (! in_array($t, $titles, true)) {
                $titles[] = $t;
            }
        }

        return $titles;
    }

    /** Pontua cada trecho da base para a pergunta (BM25 + título). @return array<int, float> maior primeiro */
    private function scoreChunks(array $weights): array
    {
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

        arsort($scored);

        return $scored;
    }

    /**
     * Termos da pergunta com peso, e, para cada palavra "de assunto", o conjunto de radicais que a cobrem
     * (ela mesma + sinônimos), usado para saber se o trecho encontrado realmente trata da pergunta.
     *
     * @return array{0: array<string, float>, 1: array<int, array<int, string>>}
     */
    private function queryWeights(string $message): array
    {
        $vocab = $this->df;
        $weights = [];
        $cover = [];
        foreach ($this->tokens($message, false) as $raw) {
            $stem = $this->stem($raw);
            if (! isset($vocab[$stem])) {
                $fix = $this->nearest($stem, $vocab);
                if ($fix !== null) {
                    $stem = $fix;
                }
            }
            $soft = in_array($raw, self::SOFT, true);
            $weights[$stem] = max($weights[$stem] ?? 0, $soft ? 0.8 : 1.0);
            $alts = [$stem];

            foreach (self::SYNONYMS as $group) {
                if (in_array($raw, $group, true) || in_array($stem, array_map([$this, 'stem'], $group), true)) {
                    foreach ($group as $syn) {
                        $s = $this->stem($syn);
                        $weights[$s] = max($weights[$s] ?? 0, 0.45);
                        $alts[] = $s;
                    }
                }
            }

            if (! $soft) {
                $cover[] = array_values(array_unique($alts));
            }
        }

        return [$weights, $cover];
    }

    /** Corrige erro de digitação: palavra do vocabulário a até 1–2 letras de distância. */
    private function nearest(string $term, array $vocab): ?string
    {
        $len = strlen($term);
        if ($len < 5) {
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
        if (str_starts_with($w, 'feriad')) {
            return 'feriad'; // feriado ≠ férias
        }
        $w = strtr($w, ['coes' => 'cao', 'oes' => 'ao', 'aes' => 'ao', 'ais' => 'al', 'eis' => 'el']);
        if (strlen($w) > 4 && str_ends_with($w, 's')) {
            $w = substr($w, 0, -1);
        }
        foreach (['mente', 'ando', 'endo', 'indo', 'amos', 'ado', 'ada', 'ar', 'er', 'ir', 'ou', 'ei', 'am', 'em'] as $suf) {
            if (strlen($w) > strlen($suf) + (strlen($suf) <= 2 ? 2 : 3) && str_ends_with($w, $suf)) {
                return substr($w, 0, -strlen($suf));
            }
        }
        // monto / monta / montar → mont ; colaboradore(s) → colaborador
        if (strlen($w) >= 4 && in_array($w[-1], ['a', 'e', 'o'], true)) {
            $w = substr($w, 0, -1);
        }

        return $w;
    }
}
