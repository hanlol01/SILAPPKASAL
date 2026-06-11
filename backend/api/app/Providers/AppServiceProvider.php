<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Decision;
use App\Models\Evidence;
use App\Models\Investigation;
use App\Models\Recommendation;
use App\Models\Recovery;
use App\Models\ReporterRegistration;
use App\Models\Report;
use App\Policies\AuditLogPolicy;
use App\Policies\CasePolicy;
use App\Policies\DecisionPolicy;
use App\Policies\DashboardPolicy;
use App\Policies\EvidencePolicy;
use App\Policies\InvestigationPolicy;
use App\Policies\MyWorkPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\RecommendationPolicy;
use App\Policies\RecoveryPolicy;
use App\Policies\ReporterRegistrationPolicy;
use App\Policies\ReportPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Notifications\DatabaseNotification;
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
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(CaseRecord::class, CasePolicy::class);
        Gate::policy(Decision::class, DecisionPolicy::class);
        Gate::policy(Evidence::class, EvidencePolicy::class);
        Gate::policy(Investigation::class, InvestigationPolicy::class);
        Gate::policy(DatabaseNotification::class, NotificationPolicy::class);
        Gate::policy(Recommendation::class, RecommendationPolicy::class);
        Gate::policy(Recovery::class, RecoveryPolicy::class);
        Gate::policy(ReporterRegistration::class, ReporterRegistrationPolicy::class);
        Gate::policy(Report::class, ReportPolicy::class);
        Gate::define('viewDashboard', fn ($user): bool => app(DashboardPolicy::class)->view($user));
        Gate::define('viewMyWork', fn ($user): bool => app(MyWorkPolicy::class)->view($user));

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
