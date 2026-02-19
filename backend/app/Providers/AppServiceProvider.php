<?php

namespace App\Providers;

use App\Models\DailyCheckin;
use App\Models\Decision;
use App\Models\Doctrine;
use App\Models\WeeklyReview;
use App\Policies\DailyCheckinPolicy;
use App\Policies\DecisionPolicy;
use App\Policies\DoctrinePolicy;
use App\Policies\WeeklyReviewPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Doctrine::class, DoctrinePolicy::class);
        Gate::policy(DailyCheckin::class, DailyCheckinPolicy::class);
        Gate::policy(Decision::class, DecisionPolicy::class);
        Gate::policy(WeeklyReview::class, WeeklyReviewPolicy::class);

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('decisions', function (Request $request): Limit {
            $identifier = $request->user()?->id ? (string) $request->user()->id : $request->ip();

            return Limit::perMinute(10)->by($identifier);
        });
    }
}
