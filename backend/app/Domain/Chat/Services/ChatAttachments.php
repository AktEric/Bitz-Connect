<?php

namespace App\Domain\Chat\Services;

use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\ChatGroupMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Anexos de conversa (foto, arquivo e mensagem de voz). Ficam em disco PRIVADO e só são entregues por
 * rota autenticada a quem participa da conversa; o caminho em disco nunca sai na API.
 */
class ChatAttachments
{
    public const MAX_FILE_KB  = 10240;
    public const MAX_AUDIO_KB = 5120;
    public const MAX_VOICE_SECONDS = 300;

    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    private const AUDIO_EXT = ['webm', 'ogg', 'oga', 'mp3', 'm4a', 'wav', 'mp4', 'aac'];
    private const FILE_EXT  = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'odt', 'ods'];

    /** Regras de validação do campo de arquivo ('file') e de apoio, para o controller usar. */
    public static function rules(): array
    {
        return [
            'file'     => ['nullable', 'file', 'max:' . self::MAX_FILE_KB],
            'voice'    => ['sometimes', 'boolean'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_VOICE_SECONDS],
        ];
    }

    /**
     * Valida e guarda o arquivo. Devolve [type, content, metadata] da mensagem, ou uma string de erro.
     *
     * @return array{0:string,1:string,2:array}|string
     */
    public function store(UploadedFile $file, bool $voice, ?int $duration, ?string $caption, string $scope): array|string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');

        if ($voice) {
            if (! in_array($ext, self::AUDIO_EXT, true)) {
                return 'Formato de áudio não suportado.';
            }
            if ($file->getSize() > self::MAX_AUDIO_KB * 1024) {
                return 'O áudio é grande demais (máx. 5 MB).';
            }
            $kind = 'audio';
        } elseif (in_array($ext, self::IMAGE_EXT, true)) {
            if (@getimagesize($file->getRealPath()) === false) {
                return 'A imagem enviada é inválida.';
            }
            $kind = 'image';
        } elseif (in_array($ext, self::FILE_EXT, true)) {
            $kind = 'file';
        } else {
            return 'Tipo de arquivo não permitido.';
        }

        $uuid = (string) Str::uuid();
        $path = $file->storeAs("chat/{$scope}", "{$uuid}.{$ext}", 'local');
        $name = Str::limit(preg_replace('/[\x00-\x1f\/\\\\]/', '', $file->getClientOriginalName()) ?: "arquivo.{$ext}", 120, '');

        $content = $caption !== null && trim($caption) !== ''
            ? trim($caption)
            : match ($kind) { 'image' => '📷 Foto', 'audio' => '🎤 Mensagem de voz', default => "📎 {$name}" };

        return [$kind, $content, [
            'uuid' => $uuid, 'path' => $path, 'name' => $name, 'size' => $file->getSize(), 'kind' => $kind,
            'mime' => $kind === 'audio' ? 'audio/' . ($ext === 'm4a' ? 'mp4' : $ext) : ($file->getMimeType() ?: 'application/octet-stream'),
            'duration' => $kind === 'audio' ? $duration : null,
        ]];
    }

    /**
     * Figurinha ou GIF enviados como JSON ({type: sticker|gif, metadata}). Devolve [type, content, metadata],
     * null quando a requisição não é desse tipo, ou uma string de erro.
     *
     * @return array{0:string,1:string,2:array}|string|null
     */
    public function rich(?string $type, mixed $metadata, ?User $sender = null): array|string|null
    {
        if ($type === 'sticker' && is_array($metadata) && isset($metadata['sticker_uuid'])) {
            // figurinha própria: só quem a tem na coleção pode enviá-la
            $uuid = $metadata['sticker_uuid'];
            $sticker = is_string($uuid) ? \App\Models\Sticker::where('uuid', $uuid)->first() : null;
            $stickers = app(Stickers::class);

            return $sticker && $sender && $stickers->holds($sender, $sticker) ? ['sticker', '🏷️ Figurinha', ['sticker_uuid' => $sticker->uuid]] : 'Figurinha inválida.';
        }
        if ($type === 'sticker') {
            $id = is_array($metadata) ? ($metadata['id'] ?? null) : null;

            return is_string($id) && preg_match('/^[a-z0-9-]{2,30}$/', $id) ? ['sticker', '🏷️ Figurinha', ['id' => $id]] : 'Figurinha inválida.';
        }
        if ($type === 'gif') {
            $m = is_array($metadata) ? $metadata : [];
            $ok = fn ($u) => is_string($u) && strlen($u) <= 400 && preg_match('#^https://media[0-9]*\.giphy\.com/[A-Za-z0-9/_\-.]+$#', $u);
            if (! $ok($m['url'] ?? null) || ! $ok($m['preview'] ?? $m['url'] ?? null)) {
                return 'GIF inválido.';
            }

            return ['gif', '🎞️ GIF', ['url' => $m['url'], 'preview' => $m['preview'] ?? $m['url'], 'title' => mb_substr((string) ($m['title'] ?? 'GIF'), 0, 80)]];
        }

        return null;
    }

    /** Metadata seguro para a API: sem o caminho em disco. */
    public static function publicMetadata(?array $metadata): ?array
    {
        if (! $metadata || ! isset($metadata['path'])) {
            return $metadata;
        }
        unset($metadata['path']);

        return $metadata;
    }

    /** Mensagem (pessoal ou de grupo) que contém o anexo, se $user puder acessá-la. Devolve [path, name, mime, kind]. */
    public function findFor(User $user, string $uuid): ?array
    {
        $direct = Message::where('metadata->uuid', $uuid)->first();
        if ($direct && ($direct->sender_id === $user->id || $direct->receiver_id === $user->id)) {
            return $this->descriptor($direct->metadata);
        }

        $group = ChatGroupMessage::where('metadata->uuid', $uuid)->first();
        if ($group && ChatGroupMember::where('chat_group_id', $group->chat_group_id)->where('user_id', $user->id)->exists()) {
            return $this->descriptor($group->metadata);
        }

        return null;
    }

    private function descriptor(?array $m): ?array
    {
        return $m && isset($m['path']) && Storage::disk('local')->exists($m['path'])
            ? ['path' => $m['path'], 'name' => $m['name'] ?? 'arquivo', 'mime' => $m['mime'] ?? 'application/octet-stream', 'kind' => $m['kind'] ?? 'file']
            : null;
    }
}
