<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\BreakGlassRequest;
use App\Models\CaseFinalSummary;
use App\Models\CaseRecord;
use App\Models\ContentItem;
use App\Models\Decision;
use App\Models\Evidence;
use App\Models\Investigation;
use App\Models\Recommendation;
use App\Models\Recovery;
use App\Models\Report;
use App\Models\ReporterRegistration;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\BreakGlassPolicy;
use App\Policies\CampusMasterDataPolicy;
use App\Policies\CaseFinalSummaryPolicy;
use App\Policies\CasePolicy;
use App\Policies\ContentItemPolicy;
use App\Policies\DashboardPolicy;
use App\Policies\DecisionPolicy;
use App\Policies\EvidencePolicy;
use App\Policies\InvestigationPolicy;
use App\Policies\MyWorkPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\RecommendationPolicy;
use App\Policies\RecoveryPolicy;
use App\Policies\ReporterPortalPolicy;
use App\Policies\ReporterRegistrationPolicy;
use App\Policies\ReporterSelfServicePolicy;
use App\Policies\ReportPolicy;
use App\Policies\UserPolicy;
use App\Services\SecurityAccessDeniedLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;

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
        Gate::policy(BreakGlassRequest::class, BreakGlassPolicy::class);
        Gate::policy(CaseRecord::class, CasePolicy::class);
        Gate::policy(CaseFinalSummary::class, CaseFinalSummaryPolicy::class);
        Gate::policy(ContentItem::class, ContentItemPolicy::class);
        Gate::policy(Decision::class, DecisionPolicy::class);
        Gate::policy(Evidence::class, EvidencePolicy::class);
        Gate::policy(Investigation::class, InvestigationPolicy::class);
        Gate::policy(DatabaseNotification::class, NotificationPolicy::class);
        Gate::policy(Recommendation::class, RecommendationPolicy::class);
        Gate::policy(Recovery::class, RecoveryPolicy::class);
        Gate::policy(ReporterRegistration::class, ReporterRegistrationPolicy::class);
        Gate::policy(Report::class, ReportPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::define('viewDashboard', fn ($user): bool => app(DashboardPolicy::class)->view($user));
        Gate::define('viewMyWork', fn ($user): bool => app(MyWorkPolicy::class)->view($user));
        Gate::define('accessReporterPortal', fn ($user): bool => app(ReporterPortalPolicy::class)->access($user));
        Gate::define('accessReporterSelfService', fn ($user): bool => app(ReporterSelfServicePolicy::class)->access($user));
        Gate::define('manage-campus-master-data', fn ($user): bool => app(CampusMasterDataPolicy::class)->manage($user));
        Gate::after(function ($user, string $ability, ?bool $result): void {
            if ($result === false
                && app()->bound('request')
                && request()->route()?->getName() !== 'audit.export') {
                app(SecurityAccessDeniedLogger::class)->record(request());
            }
        });

        RateLimiter::for('reports.submit', function (Request $request) {
            $accessToken = $request->bearerToken()
                ? PersonalAccessToken::findToken($request->bearerToken())
                : null;
            $user = $accessToken?->tokenable;

            return $user
                ? Limit::perHour(10)->by('user:'.$user->id)
                : Limit::perMinute(3)->by('ip:'.$request->ip());
        });

        RateLimiter::for('reporter.registration', function (Request $request) {
            return Limit::perMinute(20)->by('ip:'.$request->ip());
        });

        RateLimiter::for('evidence.upload', function (Request $request) {
            return Limit::perHour(10)->by('user:'.$request->user()->id);
        });

        RateLimiter::for('reporter.evidence.upload', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perHour(10)->by(
                $userId ? 'user:'.$userId : 'ip:'.$request->ip()
            );
        });
    }
}
