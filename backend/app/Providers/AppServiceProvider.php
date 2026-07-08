<?php

namespace App\Providers;

use App\Domain\Subscription\Services\EntitlementService;
use App\Enums\Feature;
use App\Models\Comment;
use App\Models\Hotel;
use App\Models\HotelClaim;
use App\Models\Job;
use App\Models\Post;
use App\Models\User;
use App\Policies\CommentPolicy;
use App\Policies\HotelClaimPolicy;
use App\Policies\HotelPolicy;
use App\Policies\JobPolicy;
use App\Policies\PostPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Hotel::class, HotelPolicy::class);
        Gate::policy(HotelClaim::class, HotelClaimPolicy::class);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);
        Gate::policy(Job::class, JobPolicy::class);

        // Feature entitlement gates (Sprint: monetization-hotelconnect-pro).
        // Each feature becomes a Gate resolving the *user's* entitlement, so
        // controllers/policies can `Gate::allows('publish_videos')`. Company
        // (hotel-scoped) features resolve via EntitlementService with the hotel
        // as subject; for a user they correctly return false.
        foreach (Feature::cases() as $feature) {
            Gate::define($feature->value, function (User $user) use ($feature) {
                return app(EntitlementService::class)->allows($user, $feature);
            });
        }
    }
}
