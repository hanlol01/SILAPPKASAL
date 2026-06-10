<?php

namespace App\Providers;

use App\Models\CaseRecord;
use App\Models\Report;
use App\Policies\CasePolicy;
use App\Policies\ReportPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
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
        Gate::policy(CaseRecord::class, CasePolicy::class);
        Gate::policy(Report::class, ReportPolicy::class);

        RateLimiter::for('reports.submit', function (Request $request) {
            $accessToken = $request->bearerToken()
                ? PersonalAccessToken::findToken($request->bearerToken())
                : null;
            $user = $accessToken?->tokenable;

            return $user
                ? Limit::perHour(10)->by('user:'.$user->id)
                : Limit::perMinute(3)->by('ip:'.$request->ip());
        });
    }
}
