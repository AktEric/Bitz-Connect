<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * In-app notification for a user.
 *
 * Categories: connection | message | feed | job | hotel | profile | system
 *
 * Types (by category):
 *   connection : connection_invite | connection_accepted | connection_rejected | connection_cancelled
 *   message    : new_message | unread_messages
 *   feed       : post_liked | post_commented | comment_replied | comment_liked |
 *                mention_in_post | mention_in_comment
 *   job        : job_application_received | job_application_updated |
 *                job_application_accepted | job_application_rejected |
 *                new_job_from_hotel | new_job_from_connection
 *   hotel      : hotel_link_requested | hotel_link_approved | hotel_link_rejected | hotel_admin_added
 *   profile    : profile_viewed | complete_profile
 *   system     : (generic)
 */
class Notification extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'actor_id',
        'type',
        'category',
        'title',
        'description',
        'link',
        'read',
    ];

    protected function casts(): array
    {
        return [
            'read' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    /** The user who receives this notification. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The user who triggered this notification (actor). */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
