<?php

namespace App\Domain\Chat\Services;

use App\Models\ChatGroupMember;
use App\Models\ChatGroupMessage;
use App\Models\ChatMessageReaction;
use App\Models\Message;
use App\Models\User;

/** Reações às mensagens (no lugar de responder): um emoji por pessoa por mensagem, só de quem participa da conversa. */
class ChatReactions
{
    public const EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

    /** Mensagem (direta ou de grupo) que $user pode ver, a partir do uuid. @return array{0:string,1:Message|ChatGroupMessage}|null */
    public function find(User $user, string $uuid): ?array
    {
        $direct = Message::where('uuid', $uuid)->whereNotNull('receiver_id')->first();
        if ($direct && ($direct->sender_id === $user->id || $direct->receiver_id === $user->id)) {
            return ['direct', $direct];
        }
        $group = ChatGroupMessage::where('uuid', $uuid)->first();
        if ($group && ChatGroupMember::where('chat_group_id', $group->chat_group_id)->where('user_id', $user->id)->exists()) {
            return ['group', $group];
        }

        return null;
    }

    /** Define (ou remove, com null) a reação de $user. Devolve o resumo da mensagem. */
    public function set(User $user, string $kind, int $messageId, ?string $emoji): array
    {
        $where = ['message_kind' => $kind, 'message_id' => $messageId, 'user_id' => $user->id];
        if ($emoji === null) {
            ChatMessageReaction::where($where)->delete();
        } else {
            ChatMessageReaction::updateOrCreate($where, ['emoji' => $emoji]);
        }

        return $this->summaries($kind, [$messageId], $user->id)[$messageId] ?? [];
    }

    /**
     * Resumo por mensagem: [{emoji, count, mine, people: [nomes]}], do mais usado ao menos usado.
     *
     * @param  int[]  $ids
     * @return array<int, array<int, array{emoji:string,count:int,mine:bool,people:string[]}>>
     */
    public function summaries(string $kind, array $ids, int $viewerId): array
    {
        if (! $ids) {
            return [];
        }
        $rows = ChatMessageReaction::where('message_kind', $kind)->whereIn('message_id', $ids)->orderBy('id')->get();
        $names = User::whereIn('id', $rows->pluck('user_id')->unique())->pluck('name', 'id');
        $out = [];
        foreach ($rows->groupBy('message_id') as $mid => $byMessage) {
            $out[(int) $mid] = $byMessage->groupBy('emoji')->map(fn ($g, $emoji) => [
                'emoji' => (string) $emoji, 'count' => $g->count(), 'mine' => $g->contains('user_id', $viewerId),
                'people' => $g->map(fn ($r) => $names[$r->user_id] ?? '—')->take(8)->values()->all(),
            ])->sortByDesc('count')->values()->all();
        }

        return $out;
    }
}
