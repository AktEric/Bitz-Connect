<?php

namespace App\Providers;

use App\Events\Chat\MessageSent;
use App\Events\Feed\CommentReplied;
use App\Events\Feed\PostCommented;
use App\Events\Feed\PostLiked;
use App\Events\Hotel\EmployeeLinkApproved;
use App\Events\Hotel\EmployeeLinkRejected;
use App\Events\Hotel\EmployeeLinkRequested;
use App\Events\Job\JobApplicationCreated;
use App\Events\Job\JobApplicationStatusChanged;
use App\Listeners\Chat\SendMessageNotification;
use App\Listeners\Feed\SendCommentRepliedNotification;
use App\Listeners\Feed\SendPostCommentedNotification;
use App\Listeners\Feed\SendPostLikedNotification;
use App\Listeners\Hotel\SendEmployeeLinkApprovedNotification;
use App\Listeners\Hotel\SendEmployeeLinkRejectedNotification;
use App\Listeners\Hotel\SendEmployeeLinkRequestedNotification;
use App\Listeners\Job\SendJobApplicationCreatedNotification;
use App\Listeners\Job\SendJobApplicationStatusChangedNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registers all application event → listener mappings.
 *
 * Uses a plain ServiceProvider (not the framework's EventServiceProvider)
 * to avoid double-registration in Laravel 11 when loaded via withProviders().
 */
class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ── Chat ──────────────────────────────────────────────
        Event::listen(MessageSent::class, SendMessageNotification::class . '@handle');

        // ── Feed ──────────────────────────────────────────────
        Event::listen(PostLiked::class,     SendPostLikedNotification::class . '@handle');
        Event::listen(PostCommented::class,  SendPostCommentedNotification::class . '@handle');
        Event::listen(CommentReplied::class, SendCommentRepliedNotification::class . '@handle');

        // ── Jobs ──────────────────────────────────────────────
        Event::listen(JobApplicationCreated::class,       SendJobApplicationCreatedNotification::class . '@handle');
        Event::listen(JobApplicationStatusChanged::class, SendJobApplicationStatusChangedNotification::class . '@handle');

        // ── Hotel ─────────────────────────────────────────────
        Event::listen(EmployeeLinkRequested::class, SendEmployeeLinkRequestedNotification::class . '@handle');
        Event::listen(EmployeeLinkApproved::class,  SendEmployeeLinkApprovedNotification::class . '@handle');
        Event::listen(EmployeeLinkRejected::class,  SendEmployeeLinkRejectedNotification::class . '@handle');
    }
}
