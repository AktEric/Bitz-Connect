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
        \Illuminate\Support\Facades\RateLimiter::for('auth', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute($this->app->environment('testing') ? 10000 : 10)->by($request->ip()));

        // Limite geral da API: por usuário autenticado (ou por IP, se anônimo). Folga para o polling do chat/notificações.
        \Illuminate\Support\Facades\RateLimiter::for('api', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute($this->app->environment('testing') ? 100000 : 300)
            ->by($request->user()?->id ?: $request->ip()));

        // Somente desenvolvimento: ver App\Support\DevBootstrap.
        if (! $this->app->runningInConsole() && $this->app->environment('local') && env('DEV_AUTO_BOOTSTRAP')) {
            // Após o boot completo, para que os comandos em app/Console/Commands já estejam registrados.
            $this->app->booted(fn () => \App\Support\DevBootstrap::run());
        }

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
