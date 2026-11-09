<?php

namespace App\Domain\Chat\Services;

use App\Models\ChatGroupMember;
use App\Models\ChatGroupMessage;
use App\Models\Message;
use App\Models\Sticker;
use App\Models\User;
use App\Support\ImageSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Figurinhas próprias: qualquer pessoa cria a partir de uma imagem, envia nas conversas e quem recebe pode salvar
 * na própria coleção. A imagem fica em disco privado e só é entregue a quem tem a figurinha ou a recebeu numa conversa.
 */
class Stickers
{
    public const MAX_PER_USER = 100;
    public const MAX_BYTES = 1_048_576;
    private const EXT = ['png', 'webp', 'gif', 'jpg', 'jpeg'];

    /** @return Sticker|string a figurinha criada ou a mensagem de erro */
    public function create(User $user, UploadedFile $file): Sticker|string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
        if (! in_array($ext, self::EXT, true)) {
            return 'Use uma imagem PNG, WebP, GIF ou JPG.';
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return 'A figurinha pode ter até 1 MB.';
        }
        $info = @getimagesize($file->getRealPath());
        if ($info === false || $info[0] > 1024 || $info[1] > 1024 || $info[0] < 32 || $info[1] < 32) {
            return 'A imagem precisa ter entre 32 e 1024 pixels de largura e altura.';
        }
        if (DB::table('user_stickers')->where('user_id', $user->id)->count() >= self::MAX_PER_USER) {
            return 'Você atingiu o limite de ' . self::MAX_PER_USER . ' figurinhas. Remova alguma para adicionar outra.';
        }

        $uuid = (string) Str::uuid();
        $path = "chat/stickers/{$uuid}.{$ext}";
        Storage::disk('local')->put($path, ImageSanitizer::clean((string) file_get_contents($file->getRealPath())));
        $sticker = Sticker::create(['uuid' => $uuid, 'created_by' => $user->id, 'path' => $path, 'mime' => $info['mime'], 'size' => $file->getSize()]);
        $this->hold($user, $sticker);

        return $sticker;
    }

    public function hold(User $user, Sticker $sticker): void
    {
        DB::table('user_stickers')->insertOrIgnore(['user_id' => $user->id, 'sticker_id' => $sticker->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function holds(User $user, Sticker $sticker): bool
    {
        return DB::table('user_stickers')->where('user_id', $user->id)->where('sticker_id', $sticker->id)->exists();
    }

    /** @return string[] uuids da coleção da pessoa, da mais nova para a mais antiga */
    public function collectionOf(User $user): array
    {
        return Sticker::join('user_stickers', 'user_stickers.sticker_id', '=', 'stickers.id')->where('user_stickers.user_id', $user->id)
            ->orderByDesc('user_stickers.id')->pluck('stickers.uuid')->all();
    }

    /** A pessoa recebeu (ou enviou) esta figurinha numa conversa que ela participa? */
    public function seenIn(User $user, Sticker $sticker): bool
    {
        $direct = Message::where('metadata->sticker_uuid', $sticker->uuid)->where(fn ($q) => $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id))->exists();
        if ($direct) {
            return true;
        }
        $groupIds = ChatGroupMessage::where('metadata->sticker_uuid', $sticker->uuid)->pluck('chat_group_id')->unique();

        return $groupIds->isNotEmpty() && ChatGroupMember::whereIn('chat_group_id', $groupIds)->where('user_id', $user->id)->exists();
    }

    public function canView(User $user, Sticker $sticker): bool
    {
        return $this->holds($user, $sticker) || $this->seenIn($user, $sticker);
    }
}
