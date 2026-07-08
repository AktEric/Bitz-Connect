<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Central service responsible for ALL notification creation.
 *
 * Rules:
 *  - No controller or domain service should create notifications directly.
 *  - Events fire → Listeners call this service.
 *  - ConnectionController still calls this directly (pre-existing pattern kept).
 *
 * Categories: connection | message | feed | job | hotel | profile | system
 */
class NotificationService
{
    // ──────────────────────────────────────────────────────────
    // Core factory
    // ──────────────────────────────────────────────────────────

    /**
     * Persist a notification record.
     * Never self-notify: if recipient === actor, returns null silently.
     */
    public function create(
        User    $recipient,
        ?User   $actor,
        string  $type,
        string  $category,
        string  $title,
        ?string $description = null,
        ?string $link = null,
    ): ?Notification {
        // Do not notify a user about their own actions
        if ($actor && $recipient->id === $actor->id) {
            return null;
        }

        return Notification::create([
            'uuid'        => (string) Str::uuid(),
            'user_id'     => $recipient->id,
            'actor_id'    => $actor?->id,
            'type'        => $type,
            'category'    => $category,
            'title'       => $title,
            'description' => $description,
            'link'        => $link,
            'read'        => false,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // Connections
    // ──────────────────────────────────────────────────────────

    public function connectionInvite(User $addressee, User $requester): void
    {
        $this->create(
            $addressee,
            $requester,
            'connection_invite',
            'connection',
            "{$requester->name} quer se conectar com você",
            null,
            "/users/{$requester->uuid}",
        );
    }

    public function connectionAccepted(User $requester, User $addressee): void
    {
        $this->create(
            $requester,
            $addressee,
            'connection_accepted',
            'connection',
            "{$addressee->name} aceitou seu convite de conexão",
            null,
            "/users/{$addressee->uuid}",
        );
    }

    public function connectionRejected(User $requester, User $addressee): void
    {
        $this->create(
            $requester,
            $addressee,
            'connection_rejected',
            'connection',
            "{$addressee->name} recusou seu convite de conexão",
            null,
            "/users/{$addressee->uuid}",
        );
    }

    public function connectionCancelled(User $addressee, User $requester): void
    {
        $this->create(
            $addressee,
            $requester,
            'connection_cancelled',
            'connection',
            "{$requester->name} cancelou o convite de conexão",
            null,
            null,
        );
    }

    // ──────────────────────────────────────────────────────────
    // Messages
    // ──────────────────────────────────────────────────────────

    /**
     * Create or upsert a "new_message" notification for the recipient.
     *
     * Upsert logic: if there is already an unread notification from the same
     * sender (read = false), update its description and updated_at instead of
     * creating a new record. This prevents notification flooding when the same
     * contact sends multiple messages in a row.
     *
     * Description rules by message type:
     *   - job      → "Compartilhou uma vaga com você"
     *   - img-stub / file-stub → "Enviou uma mídia"
     *   - text (default) → truncate $content to 100 chars + "…" if needed
     *
     * Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7
     */
    public function newMessage(
        User        $recipient,
        User        $sender,
        MessageType $type,
        ?string     $content = null,
    ): void {
        if ($recipient->id === $sender->id) {
            return;
        }

        $description = match ($type) {
            MessageType::Job      => 'Compartilhou uma vaga com você',
            MessageType::ImgStub,
            MessageType::FileStub => 'Enviou uma mídia',
            default               => mb_strlen($content ?? '') > 100
                                        ? mb_substr($content, 0, 100) . '…'
                                        : ($content ?? ''),
        };

        // Upsert: update existing unread notification from the same sender
        $existing = Notification::where('user_id', $recipient->id)
            ->where('actor_id', $sender->id)
            ->where('type', 'new_message')
            ->where('read', false)
            ->first();

        if ($existing) {
            $existing->update(['description' => $description, 'updated_at' => now()]);
            return;
        }

        $this->create(
            recipient:   $recipient,
            actor:       $sender,
            type:        'new_message',
            category:    'message',
            title:       "{$sender->name} enviou uma mensagem",
            description: $description,
            link:        "/chat/{$sender->uuid}",
        );
    }

    // ──────────────────────────────────────────────────────────
    // Feed
    // ──────────────────────────────────────────────────────────

    public function postLiked(User $author, User $liker, string $postUuid): void
    {
        $this->create(
            $author,
            $liker,
            'post_liked',
            'feed',
            "{$liker->name} curtiu sua publicação",
            null,
            "/feed/{$postUuid}",
        );
    }

    public function postCommented(User $author, User $commenter, string $postUuid, ?string $excerpt = null): void
    {
        $this->create(
            $author,
            $commenter,
            'post_commented',
            'feed',
            "{$commenter->name} comentou na sua publicação",
            $excerpt,
            "/feed/{$postUuid}",
        );
    }

    public function commentReplied(User $commentAuthor, User $replier, string $postUuid, ?string $excerpt = null): void
    {
        $this->create(
            $commentAuthor,
            $replier,
            'comment_replied',
            'feed',
            "{$replier->name} respondeu seu comentário",
            $excerpt,
            "/feed/{$postUuid}",
        );
    }

    public function commentLiked(User $commentAuthor, User $liker, string $postUuid): void
    {
        $this->create(
            $commentAuthor,
            $liker,
            'comment_liked',
            'feed',
            "{$liker->name} curtiu seu comentário",
            null,
            "/feed/{$postUuid}",
        );
    }

    public function mentionInPost(User $mentioned, User $actor, string $postUuid): void
    {
        $this->create(
            $mentioned,
            $actor,
            'mention_in_post',
            'feed',
            "{$actor->name} mencionou você em uma publicação",
            null,
            "/feed/{$postUuid}",
        );
    }

    public function mentionInComment(User $mentioned, User $actor, string $postUuid): void
    {
        $this->create(
            $mentioned,
            $actor,
            'mention_in_comment',
            'feed',
            "{$actor->name} mencionou você em um comentário",
            null,
            "/feed/{$postUuid}",
        );
    }

    // ──────────────────────────────────────────────────────────
    // Jobs
    // ──────────────────────────────────────────────────────────

    public function jobApplicationReceived(User $hotelAdmin, User $applicant, string $jobUuid, string $jobTitle): void
    {
        $this->create(
            $hotelAdmin,
            $applicant,
            'job_application_received',
            'job',
            'Nova candidatura para "' . $jobTitle . '"',
            "{$applicant->name} se candidatou",
            "/jobs/{$jobUuid}/applications",
        );
    }

    public function jobApplicationUpdated(User $applicant, string $jobTitle, string $jobUuid, string $status): void
    {
        $this->create(
            $applicant,
            null,
            'job_application_updated',
            'job',
            'Sua candidatura para "' . $jobTitle . '" foi atualizada',
            "Status: {$status}",
            "/me/applications",
        );
    }

    public function jobApplicationAccepted(User $applicant, User $hotelAdmin, string $jobTitle, string $jobUuid): void
    {
        $this->create(
            $applicant,
            $hotelAdmin,
            'job_application_accepted',
            'job',
            'Parabéns! Sua candidatura para "' . $jobTitle . '" foi aceita',
            null,
            "/me/applications",
        );
    }

    public function jobApplicationRejected(User $applicant, User $hotelAdmin, string $jobTitle, string $jobUuid): void
    {
        $this->create(
            $applicant,
            $hotelAdmin,
            'job_application_rejected',
            'job',
            'Sua candidatura para "' . $jobTitle . '" não foi aprovada',
            null,
            "/me/applications",
        );
    }

    /**
     * Notify followers/connections about a new job posted by a hotel.
     * Call once per recipient.
     */
    public function newJobFromHotel(User $follower, string $hotelName, string $hotelSlug, string $jobTitle, string $jobUuid): void
    {
        $this->create(
            $follower,
            null,
            'new_job_from_hotel',
            'job',
            "{$hotelName} publicou uma nova vaga",
            $jobTitle,
            "/jobs/{$jobUuid}",
        );
    }

    public function newJobFromConnection(User $follower, User $connection, string $jobTitle, string $jobUuid): void
    {
        $this->create(
            $follower,
            $connection,
            'new_job_from_connection',
            'job',
            "{$connection->name} publicou uma nova vaga",
            $jobTitle,
            "/jobs/{$jobUuid}",
        );
    }

    // ──────────────────────────────────────────────────────────
    // Hotel
    // ──────────────────────────────────────────────────────────

    public function hotelLinkRequested(User $admin, User $requester, string $hotelName, string $hotelSlug): void
    {
        $this->create(
            $admin,
            $requester,
            'hotel_link_requested',
            'hotel',
            "{$requester->name} solicitou vínculo com {$hotelName}",
            null,
            "/hotels/{$hotelSlug}",
        );
    }

    public function hotelLinkApproved(User $user, string $hotelName, string $hotelSlug): void
    {
        $this->create(
            $user,
            null,
            'hotel_link_approved',
            'hotel',
            "Seu vínculo com {$hotelName} foi aprovado",
            null,
            "/hotels/{$hotelSlug}",
        );
    }

    public function hotelLinkRejected(User $user, string $hotelName): void
    {
        $this->create(
            $user,
            null,
            'hotel_link_rejected',
            'hotel',
            "Seu vínculo com {$hotelName} foi recusado",
            null,
            null,
        );
    }

    public function hotelAdminAdded(User $newAdmin, User $addedBy, string $hotelName, string $hotelSlug): void
    {
        $this->create(
            $newAdmin,
            $addedBy,
            'hotel_admin_added',
            'hotel',
            "Você foi adicionado como administrador de {$hotelName}",
            null,
            "/hotels/{$hotelSlug}",
        );
    }

    // ──────────────────────────────────────────────────────────
    // Profile
    // ──────────────────────────────────────────────────────────

    /**
     * Profile view — structure prepared for future use.
     * Only fires if viewer !== profile owner (already checked via create()).
     */
    public function profileViewed(User $profileOwner, User $viewer): void
    {
        $this->create(
            $profileOwner,
            $viewer,
            'profile_viewed',
            'profile',
            "{$viewer->name} visualizou seu perfil",
            null,
            "/users/{$viewer->uuid}",
        );
    }

    public function completeProfile(User $user): void
    {
        $this->create(
            $user,
            null,
            'complete_profile',
            'profile',
            'Complete seu perfil para se destacar',
            'Adicione experiências, foto e bio',
            "/me",
        );
    }
}
