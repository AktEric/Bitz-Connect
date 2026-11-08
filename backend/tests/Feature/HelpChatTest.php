<?php

use App\Domain\Help\Services\VitoriaLocal;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function ask(string $text, ?string $role = null): string
{
    return (new VitoriaLocal())->reply($text, $role);
}

// ── Motor local (gratuito, sem IA externa) ─────────────────────────

it('conversa normalmente: oi, tudo bem, obrigado, tchau', function () {
    expect(ask('oi'))->toMatch('/ajudar|Vitória/u');
    expect(ask('Oi, tudo bem?'))->toMatch('/tudo|bem/i');
    expect(ask('bom dia!'))->toContain('ajudar');
    expect(ask('obrigada'))->toMatch('/nada|imagina|ajudar/i');
    expect(ask('tchau'))->toMatch('/até|tchau/iu');
    expect(ask('quem é você?'))->toContain('assistente virtual');
});

it('responde como montar o organograma, de várias formas', function (string $q) {
    $r = ask($q);
    expect($r)->toContain('Gestor direto')->toContain('organograma');
})->with([
    'como monto o organograma?',
    'como faço pra montar um organograma',
    'quero criar a hierarquia da empresa',
    'orgnograma como monta',          // erro de digitação
    'ONDE EU VEJO O ORGANOGRAMA???',
]);

it('responde como ver as pessoas da empresa', function (string $q) {
    expect(ask($q))->toContain('Pessoas');
})->with([
    'como vejo as pessoas da empresa',
    'onde ficam os funcionários?',
    'quero ver a lista de colaboradores',
]);

it('responde como editar o perfil', function (string $q) {
    expect(ask($q))->toMatch('/Perfil social|Sobre mim|Trocar foto/u');
})->with([
    'como edito meu perfil',
    'quero mudar minha foto',
    'como altero meus dados do perfil?',
]);

it('explica o que pode postar no mural e quem pode', function () {
    $r = ask('o que eu posso postar no mural?', 'Seu papel em X: **Colaborador**.');
    expect($r)->toContain('RH/dono e gestores')->toContain('Seu papel em X');
});

it('responde outros assuntos do sistema', function (string $q, string $esperado) {
    expect(ask($q))->toContain($esperado);
})->with([
    ['esqueci de bater o ponto, e agora?', 'ajuste'],
    ['como convido um funcionário novo?', 'convite'],
    ['como peço férias?', 'solicitaç'],
    ['como ativo a verificação em duas etapas', 'autenticador'],
    ['onde vejo meu holerite', 'Holerites'],
    ['a pesquisa de clima é anônima?', 'anônima'],
]);

it('reexplica passo a passo quando a pessoa diz que não entendeu (conversa do print)', function () {
    $history = [
        ['role' => 'user', 'content' => 'como monto o organograma?'],
        ['role' => 'assistant', 'content' => '**Montar o organograma** ...'],
    ];

    $r = (new VitoriaLocal())->reply('mas eu n entendi bem', null, $history);

    expect($r)->toContain('passo a passo')->toContain('1. O organograma')->toContain('Empresa → Organograma')
        ->toContain('Gestor direto')->not->toContain('1. :')->not->toContain('não encontrei');
});

it('resume curto quando pede de novo e já foi explicado passo a passo', function () {
    $history = [
        ['role' => 'user', 'content' => 'como monto o organograma?'],
        ['role' => 'assistant', 'content' => '**Montar o organograma** ...'],
        ['role' => 'user', 'content' => 'não entendi'],
        ['role' => 'assistant', 'content' => 'Sem problema! Vou explicar de outro jeito, passo a passo: ...'],
    ];

    $r = (new VitoriaLocal())->reply('ainda não entendi, explica melhor', null, $history);

    expect($r)->toContain('resumir')->toContain('travou');
});

it('pergunta o assunto quando não há nada para reexplicar', function () {
    expect((new VitoriaLocal())->reply('não entendi', null, []))->toContain('Sobre qual assunto');
});

it('entende pergunta curta de continuação usando o assunto anterior', function () {
    $history = [
        ['role' => 'user', 'content' => 'como edito meu perfil?'],
        ['role' => 'assistant', 'content' => 'Editar meu perfil: ...'],
    ];

    expect((new VitoriaLocal())->reply('e a foto?', null, $history))->toMatch('/foto/i');
});

it('responde perguntas genéricas sobre o sistema com a visão geral', function (string $q) {
    expect(ask($q))->toContain('Workspace')->toContain('Meu dia');
})->with([
    'como funciona o sistema?',
    'não sei por onde começar',
    'para que serve isso aqui',
    'o que tem no sistema',
]);

it('admite quando não sabe, sem inventar', function () {
    expect(ask('qual a previsão do tempo amanhã em Marte'))->toContain('não encontrei');
    expect(ask('asdfgh qwerty'))->toContain('não encontrei');
});

// ── Endpoint ───────────────────────────────────────────────────────

it('exige login', function () {
    $this->postJson('/api/v1/help/chat', ['messages' => [['role' => 'user', 'content' => 'oi']]])->assertUnauthorized();
});

it('responde pelo motor local sem chamar nenhuma API externa', function () {
    Http::fake();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/help/chat', ['messages' => [['role' => 'user', 'content' => 'como monto o organograma?']]])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJson(fn ($j) => $j->has('data.reply')->etc());

    Http::assertNothingSent();
});

it('inclui o papel da pessoa nas perguntas de permissão', function () {
    $user = User::factory()->create();
    $hotel = Hotel::factory()->create(['name' => 'Hotel Teste']);
    HotelUser::create(['hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => 'employee', 'status' => 'approved']);
    Sanctum::actingAs($user);

    $reply = $this->postJson('/api/v1/help/chat', [
        'messages'  => [['role' => 'user', 'content' => 'posso postar no mural?']],
        'workspace' => $hotel->slug,
    ])->assertOk()->json('data.reply');

    expect($reply)->toContain('Colaborador')->toContain('Hotel Teste');
});

it('descarta respostas antigas no início do histórico', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/help/chat', ['messages' => [
        ['role' => 'assistant', 'content' => 'Olá!'],
        ['role' => 'user', 'content' => 'oi'],
    ]])->assertOk();
});

it('recusa histórico que não termina com a pessoa falando', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/help/chat', ['messages' => [
        ['role' => 'user', 'content' => 'oi'],
        ['role' => 'assistant', 'content' => 'Olá!'],
    ]])->assertStatus(422);
});

it('valida o tamanho da mensagem', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/help/chat', ['messages' => [['role' => 'user', 'content' => str_repeat('a', 2001)]]])
        ->assertStatus(422);
});

// ── Claude (opcional, desligado por padrão) ────────────────────────

it('usa o Claude somente quando HELP_DRIVER=claude', function () {
    config(['services.help.driver' => 'claude', 'services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Resposta da IA']]])]);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/help/chat', ['messages' => [['role' => 'user', 'content' => 'oi']]])
        ->assertOk()
        ->assertJsonPath('data.reply', 'Resposta da IA');

    Http::assertSent(fn (Request $r) => $r->hasHeader('x-api-key', 'test-key'));
});

it('avisa quando o Claude está ligado mas sem chave', function () {
    config(['services.help.driver' => 'claude', 'services.anthropic.key' => '']);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/help/chat', ['messages' => [['role' => 'user', 'content' => 'oi']]])
        ->assertStatus(503);
});

// Bateria de perguntas do dia a dia (escritas de jeitos diferentes) que a Vitória deve entender.
it('entende perguntas do dia a dia sobre o sistema', function (string $pergunta, string $esperado) {
    expect(mb_stripos(ask($pergunta), $esperado))->not->toBeFalse("Pergunta sem a resposta esperada ({$esperado}): {$pergunta}");
})->with([
    ['como cadastro um funcionário novo', 'convite'],
    ['quero adicionar alguém na empresa', 'convite'],
    ['como tiro uma pessoa da empresa', 'Pessoas'],
    ['como mudo o cargo de alguém', 'Cargo'],
    ['como troco o chefe de um funcionário', 'Gestor direto'],
    ['quem é meu chefe', 'Gestor direto'],
    ['como crio um setor', 'setor'],
    ['como bato o ponto', 'Ponto'],
    ['errei o horário do ponto', 'ajuste'],
    ['como vejo minhas horas extras', 'banco de horas'],
    ['fechar o mês do ponto', 'fechamento'],
    ['como peço férias', 'solicitaç'],
    ['como aprovo as férias do meu time', 'Aprovações'],
    ['quero pedir reembolso', 'reembolso'],
    ['abrir um chamado pro rh', 'chamado'],
    ['onde vejo meu holerite', 'Holerites'],
    ['como o rh anexa o contracheque', 'Anexar'],
    ['como defino uma meta', 'Metas'],
    ['o que é pdi', 'PDI'],
    ['como faço um plano de desenvolvimento', 'PDI'],
    ['como crio uma pesquisa de clima', 'Abrir pesquisa'],
    ['o que é enps', 'eNPS'],
    ['como abro uma vaga', 'Abrir vaga'],
    ['como me candidato numa vaga', 'candidatar'],
    ['como divulgar vaga no whatsapp', 'link público'],
    ['como crio um evento no calendário', 'evento'],
    ['como coloco meu aniversário', 'aniversário'],
    ['como sincronizar o calendário com o google agenda', 'Assinar'],
    ['como crio uma enquete', 'enquete'],
    ['como fixo um aviso no mural', 'Fixar'],
    ['como elogio um colega', 'Reconhecimento'],
    ['como apago um post', 'Apagar'],
    ['como troco minha senha', 'senha'],
    ['esqueci minha senha', 'senha'],
    ['como ativo o 2fa', 'autenticador'],
    ['onde vejo em quais aparelhos estou logado', 'conectado'],
    ['quem pode ver o painel da diretoria', 'diretoria'],
    ['como vejo a auditoria', 'Auditoria'],
    ['como mudo o papel de uma pessoa', 'dono'],
    ['qual a diferença entre rh e gestor', 'Gestor'],
    ['como exporto meus dados', 'exportar'],
    ['como funciona a lgpd', 'LGPD'],
    ['como crio um grupo no chat', 'grupo'],
    ['como mando mensagem pra alguém', 'Chat'],
    ['como faço um curso', 'Consultor PRO'],
    ['como envio um vídeo', 'Consultor PRO'],
    ['como faço uma conexão com alguém', 'Conexões'],
    ['onde vejo as notificações', 'sino'],
    ['como mudo minha foto de perfil', 'foto'],
    ['o que é o painel da diretoria', 'agregados'],
    ['quem é o dono da empresa', 'Dono'],
    ['como instalo no celular', 'celular'],
    ['como convido o rh', 'convite'],
    ['onde configuro as jornadas de trabalho', 'jornadas'],
    ['como cadastro feriado', 'Feriados'],
    ['posso ver o ponto de outra pessoa', 'ponto do time'],
    ['como vejo quem leu o comunicado', 'Ciência'],
    ['como peço ajuste de ponto', 'ajuste'],
]);

it('sugere assuntos parecidos quando não tem certeza', function () {
    $v = new VitoriaLocal();
    $r = $v->reply('como funciona o fechamento de caixa do restaurante e do ponto de venda');

    expect($v->answered)->toBeFalse();
    expect($r)->toContain('não encontrei');
});

it('marca quando respondeu e quando não', function () {
    $v = new VitoriaLocal();
    $v->reply('como monto o organograma?');
    expect($v->answered)->toBeTrue();

    $v->reply('qual a previsão do tempo amanhã em Marte');
    expect($v->answered)->toBeFalse();
});

it('registra no log as perguntas sem resposta', function () {
    \Illuminate\Support\Facades\Log::spy();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/help/chat', ['messages' => [['role' => 'user', 'content' => 'qual a previsão do tempo amanhã em Marte']]])->assertOk();

    \Illuminate\Support\Facades\Log::shouldHaveReceived('info')->withArgs(fn ($m, $ctx = []) => $m === 'vitoria.sem_resposta' && str_contains($ctx['pergunta'], 'Marte'))->once();
});
