<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Binds repository interfaces to their Eloquent implementations.
 * Add bindings here as each domain's repository is created.
 *
 * Example:
 *   $this->app->bind(
 *       \App\Domain\User\Repositories\UserRepositoryInterface::class,
 *       \App\Domain\User\Repositories\EloquentUserRepository::class,
 *   );
 */
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \App\Domain\User\Repositories\UserRepositoryInterface::class,
            \App\Domain\User\Repositories\EloquentUserRepository::class,
        );

        $this->app->bind(
            \App\Domain\Hotel\Repositories\HotelRepositoryInterface::class,
            \App\Domain\Hotel\Repositories\EloquentHotelRepository::class,
        );

        $this->app->bind(
            \App\Domain\Feed\Repositories\PostRepositoryInterface::class,
            \App\Domain\Feed\Repositories\EloquentPostRepository::class,
        );

        $this->app->bind(
            \App\Domain\Job\Repositories\JobRepositoryInterface::class,
            \App\Domain\Job\Repositories\EloquentJobRepository::class,
        );

        $this->app->bind(
            \App\Domain\Subscription\Repositories\SubscriptionRepositoryInterface::class,
            \App\Domain\Subscription\Repositories\EloquentSubscriptionRepository::class,
        );

        $this->app->bind(
            \App\Domain\Video\Repositories\VideoRepositoryInterface::class,
            \App\Domain\Video\Repositories\EloquentVideoRepository::class,
        );

        $this->app->bind(
            \App\Domain\Course\Repositories\CourseRepositoryInterface::class,
            \App\Domain\Course\Repositories\EloquentCourseRepository::class,
        );

        $this->app->bind(
            \App\Domain\Mentorship\Repositories\MentorshipRepositoryInterface::class,
            \App\Domain\Mentorship\Repositories\EloquentMentorshipRepository::class,
        );

        $this->app->bind(
            \App\Domain\Story\Repositories\StoryRepositoryInterface::class,
            \App\Domain\Story\Repositories\EloquentStoryRepository::class,
        );
    }
}
