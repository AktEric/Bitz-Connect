<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Help\Services\VitoriaAssistant;
use App\Domain\Help\Services\VitoriaLocal;
use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Central de ajuda: chat com a Vitória. */
class HelpChatController extends Controller
{
    use ApiResponseTrait;

    private const ROLE_LABELS = [
        'owner'    => 'Dono',
        'hr'       => 'RH',
        'finance'  => 'Financeiro',
        'director' => 'Diretoria',
        'manager'  => 'Gestor',
        'employee' => 'Colaborador',
    ];

    public function __construct(
        private readonly VitoriaAssistant $vitoria,
        private readonly VitoriaLocal $local,
        private readonly WorkspaceAccess $access,
    ) {}

    /** POST /help/chat */
    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messages'           => ['required', 'array', 'min:1', 'max:20'],
            'messages.*.role'    => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:2000'],
            'workspace'          => ['nullable', 'string', 'max:120'],
            'page'               => ['nullable', 'string', 'max:200'],
        ]);

        $messages = array_values(array_map(
            fn ($m) => ['role' => $m['role'], 'content' => trim($m['content'])],
            $data['messages'],
        ));

        // A API exige começar e terminar com a pessoa falando.
        while ($messages && $messages[0]['role'] !== 'user') {
            array_shift($messages);
        }
        if (! $messages || end($messages)['role'] !== 'user') {
            return $this->error('Envie uma mensagem para a Vitória.', [], 422);
        }

        // Padrão: motor local, gratuito. O Claude só é usado se HELP_DRIVER=claude e houver chave.
        if (config('services.help.driver', 'local') !== 'claude') {
            $last = end($messages)['content'];
            $note = $this->roleNote($request, $data['workspace'] ?? null);

            $history = array_slice($messages, 0, -1); // memória: o que já foi conversado

            $reply = $this->local->reply($last, $note, $history);

            // Perguntas sem resposta ficam no log (storage/logs): é a lista do que falta ensinar à Vitória em resources/help/vitoria.md.
            if (! $this->local->answered) {
                Log::info('vitoria.sem_resposta', ['pergunta' => mb_substr($last, 0, 300), 'workspace' => $data['workspace'] ?? null]);
            }

            return $this->success('OK', ['reply' => $reply]);
        }

        try {
            $reply = $this->vitoria->reply($messages, $this->context($request, $data['workspace'] ?? null, $data['page'] ?? null));
        } catch (RuntimeException $e) {
            if ($e->getMessage() === VitoriaAssistant::NOT_CONFIGURED) {
                return $this->error('A Vitória ainda não foi configurada. Peça ao responsável pelo sistema para informar a chave da IA.', [], 503);
            }

            return $this->error('A Vitória está indisponível agora. Tente novamente em instantes.', [], 502);
        }

        return $this->success('OK', ['reply' => $reply]);
    }

    /** Frase com o papel da pessoa, usada pelo motor local nas perguntas de permissão. */
    private function roleNote(Request $request, ?string $slug): ?string
    {
        $hotel = $slug ? Hotel::where('slug', $slug)->first() : null;
        $m     = $hotel ? $this->access->membership($request->user(), $hotel) : null;
        if (! $hotel || ! $m) {
            return null;
        }
        $role = $this->access->accessRole($m, $hotel);
        $tail = $this->access->isAdmin($m)
            ? ' Você administra pessoas (dono/RH).'
            : ($this->access->isManager($request->user(), $hotel)
                ? ' Você é gestor de equipe.'
                : ' Se precisar de algo que exige outro papel, fale com o RH.');

        return 'Seu papel em ' . $hotel->name . ': **' . (self::ROLE_LABELS[$role] ?? $role) . '**.' . $tail;
    }

    /** Quem está falando: nome e, se estiver num Workspace, o papel na empresa (para a Vitória respeitar as permissões). */
    private function context(Request $request, ?string $slug, ?string $page): string
    {
        $user  = $request->user();
        $lines = ['Nome: ' . $user->name];

        $hotel = $slug ? Hotel::where('slug', $slug)->first() : null;
        $m     = $hotel ? $this->access->membership($user, $hotel) : null;

        if ($hotel && $m) {
            $role = $this->access->accessRole($m, $hotel);
            $lines[] = 'Empresa: ' . $hotel->name;
            $lines[] = 'Papel na empresa: ' . (self::ROLE_LABELS[$role] ?? $role);
            $lines[] = 'É gestor de alguém: ' . ($this->access->isManager($user, $hotel) ? 'sim' : 'não');
            $lines[] = 'Administra pessoas (dono ou RH): ' . ($this->access->isAdmin($m) ? 'sim' : 'não');
            $lines[] = 'Vê o painel da diretoria: ' . ($this->access->canSeeDashboard($m) ? 'sim' : 'não');
        } else {
            $lines[] = 'Não está dentro de um Workspace de empresa agora (rede profissional ou lista de empresas).';
        }

        if ($page) {
            $lines[] = 'Tela atual: ' . $page;
        }

        return implode("\n", $lines);
    }
}
