<?php

namespace App\Providers;

use App\Models\CaseRecord;
use App\Models\Decision;
use App\Models\Evidence;
use App\Models\Investigation;
use App\Models\Recommendation;
use App\Models\Recovery;
use App\Models\Report;
use App\Policies\CasePolicy;
use App\Policies\DecisionPolicy;
use App\Policies\EvidencePolicy;
use App\Policies\InvestigationPolicy;
use App\Policies\RecommendationPolicy;
use App\Policies\RecoveryPolicy;
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
        Gate::policy(Decision::class, DecisionPolicy::class);
        Gate::policy(Evidence::class, EvidencePolicy::class);
        Gate::policy(Investigation::class, InvestigationPolicy::class);
        Gate::policy(Recommendation::class, RecommendationPolicy::class);
        Gate::policy(Recovery::class, RecoveryPolicy::class);
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
