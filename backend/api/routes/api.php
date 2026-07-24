<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BreakGlassController;
use App\Http\Controllers\Api\V1\CampusMasterDataController;
use App\Http\Controllers\Api\V1\CaseController;
use App\Http\Controllers\Api\V1\CaseFinalSummaryController;
use App\Http\Controllers\Api\V1\ContentAttachmentController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\ContentGovernanceController;
use App\Http\Controllers\Api\V1\ContentManagementController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DecisionController;
use App\Http\Controllers\Api\V1\EvidenceController;
use App\Http\Controllers\Api\V1\InvestigationController;
use App\Http\Controllers\Api\V1\MasterDataController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MyWorkController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PortalController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\RecoveryController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReporterRegistrationController;
use App\Http\Controllers\Api\V1\ReportEvidenceSubmissionController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'SILAPPKASAL API is healthy',
            'data' => [
                'status' => 'ok',
                'service' => 'silappkasal-api',
            ],
        ]);
    });

    Route::middleware('private.no-store')->prefix('auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::middleware('auth:sanctum')->prefix('me')->group(function (): void {
        Route::get('/profile', [MeController::class, 'profile']);
        Route::patch('/profile', [MeController::class, 'updateProfile']);
        Route::patch('/change-password', [MeController::class, 'changePassword']);
        Route::get('/account-status', [MeController::class, 'accountStatus']);
    });

    Route::middleware(['private.no-store', 'auth:sanctum'])->prefix('content')->group(function (): void {
        Route::get('/sections', [ContentController::class, 'sections']);
        Route::get('/categories', [ContentController::class, 'categories']);
        Route::get('/articles', [ContentController::class, 'articles']);
        Route::get('/article-categories', [ContentController::class, 'articleCategories'])
            ->middleware('throttle:60,1');
        Route::get('/articles/slug/{section}/{slug}', [ContentController::class, 'articleBySlug'])
            ->where('section', 'education|policy')
            ->where('slug', '[a-z0-9-]+')
            ->middleware('throttle:60,1');
        Route::get('/articles/{publicId}', [ContentController::class, 'article'])
            ->whereUuid('publicId');
        Route::get('/faqs', [ContentController::class, 'faqs']);
        Route::get('/consultation', [ContentController::class, 'consultation']);
        Route::get('/featured', [ContentController::class, 'featured']);
        Route::get('/attachments/{attachment}', [ContentAttachmentController::class, 'download'])
            ->whereUuid('attachment')
            ->name('content.attachments.download');
    });

    Route::middleware(['private.no-store', 'auth:sanctum'])->prefix('content-management')->group(function (): void {
        Route::get('/items', [ContentManagementController::class, 'index']);
        Route::get('/summary', [ContentManagementController::class, 'summary']);
        Route::get('/capabilities', [ContentManagementController::class, 'capabilities'])
            ->middleware('throttle:60,1');
        Route::get('/article-categories', [ContentManagementController::class, 'articleCategories'])
            ->middleware('throttle:60,1');
        Route::post('/article-categories', [ContentManagementController::class, 'storeArticleCategory'])
            ->middleware('throttle:30,1');
        Route::delete('/article-categories/{category}', [ContentManagementController::class, 'destroyArticleCategory'])
            ->whereUuid('category')
            ->middleware('throttle:30,1');
        Route::get('/items/{item}', [ContentManagementController::class, 'show'])->whereUuid('item');
        Route::post('/items', [ContentManagementController::class, 'store']);
        Route::post('/items/{item}/revisions', [ContentManagementController::class, 'createRevision'])->whereUuid('item');
        Route::patch('/versions/{version}', [ContentManagementController::class, 'update'])->whereUuid('version');
        Route::post('/versions/{version}/submit', [ContentManagementController::class, 'submit'])->whereUuid('version');
        Route::post('/versions/{version}/attachments', [ContentManagementController::class, 'upload'])
            ->whereUuid('version')
            ->middleware('throttle:10,1')
            ->name('content.attachments.upload');
        Route::delete('/attachments/{attachment}', [ContentManagementController::class, 'removeAttachment'])
            ->whereUuid('attachment');
    });

    Route::middleware(['private.no-store', 'auth:sanctum', 'role:super_admin'])->prefix('content-governance')->group(function (): void {
        Route::middleware('permission:content.read.management.all')->group(function (): void {
            Route::get('/reviews', [ContentGovernanceController::class, 'reviews']);
            Route::get('/published', [ContentGovernanceController::class, 'published']);
            Route::get('/campuses', [ContentGovernanceController::class, 'reviewCampuses']);
            Route::get('/categories', [ContentGovernanceController::class, 'reviewCategories']);
            Route::get('/items/{item}', [ContentGovernanceController::class, 'show'])->whereUuid('item');
        });

        Route::middleware(['permission:content.review', 'throttle:30,1'])->group(function (): void {
            Route::post('/versions/{version}/start-review', [ContentGovernanceController::class, 'startReview'])->whereUuid('version');
            Route::post('/versions/{version}/request-revision', [ContentGovernanceController::class, 'requestRevision'])->whereUuid('version');
            Route::post('/versions/{version}/reject', [ContentGovernanceController::class, 'reject'])->whereUuid('version');
            Route::post('/versions/{version}/approve', [ContentGovernanceController::class, 'approve'])->whereUuid('version');
            Route::post('/versions/{version}/publish', [ContentGovernanceController::class, 'publish'])->whereUuid('version');
        });
        Route::post('/items/{item}/archive', [ContentGovernanceController::class, 'archive'])
            ->whereUuid('item')->middleware(['permission:content.archive', 'throttle:30,1']);

        Route::middleware('permission:content.feature.manage')->prefix('featured')->group(function (): void {
            Route::get('/', [ContentGovernanceController::class, 'featured']);
            Route::get('/eligible', [ContentGovernanceController::class, 'eligibleFeatured']);
            Route::get('/campuses', [ContentGovernanceController::class, 'campuses']);
            Route::post('/', [ContentGovernanceController::class, 'storeFeatured'])->middleware('throttle:30,1');
            Route::patch('/{placement}', [ContentGovernanceController::class, 'updateFeatured'])
                ->whereUuid('placement')->middleware('throttle:30,1');
            Route::delete('/{placement}', [ContentGovernanceController::class, 'removeFeatured'])
                ->whereUuid('placement')->middleware('throttle:30,1');
        });
    });

    Route::middleware('auth:sanctum')->prefix('master')->group(function (): void {
        Route::get('/{type}', [MasterDataController::class, 'index'])
            ->whereIn('type', [
                'report-categories',
                'report-types',
                'evidence-types',
                'case-statuses',
                'risk-levels',
                'priority-levels',
                'campus-statuses',
                'relations',
                'location-types',
                'escalation-types',
                'recovery-types',
                'recovery-statuses',
            ]);
    });

    Route::get('/universities', [CampusMasterDataController::class, 'universities'])
        ->middleware('throttle:30,1');
    Route::get('/faculties', [CampusMasterDataController::class, 'faculties'])
        ->middleware('throttle:30,1');
    Route::get('/study-programs', [CampusMasterDataController::class, 'studyPrograms'])
        ->middleware('throttle:30,1');

    Route::middleware('auth:sanctum')->prefix('campus-admin')->group(function (): void {
        Route::get('/universities', [CampusMasterDataController::class, 'indexAdmin']);
        Route::post('/universities', [CampusMasterDataController::class, 'storeUniversity']);
        Route::get('/universities/{university}', [CampusMasterDataController::class, 'showUniversity']);
        Route::put('/universities/{university}', [CampusMasterDataController::class, 'updateUniversity']);
        Route::patch('/universities/{university}/toggle-active', [CampusMasterDataController::class, 'toggleUniversityActive']);

        Route::get('/faculties', [CampusMasterDataController::class, 'indexFacultiesAdmin']);
        Route::post('/faculties', [CampusMasterDataController::class, 'storeFaculty']);
        Route::get('/faculties/{faculty}', [CampusMasterDataController::class, 'showFaculty']);
        Route::put('/faculties/{faculty}', [CampusMasterDataController::class, 'updateFaculty']);
        Route::patch('/faculties/{faculty}/toggle-active', [CampusMasterDataController::class, 'toggleFacultyActive']);

        Route::get('/study-programs', [CampusMasterDataController::class, 'indexStudyProgramsAdmin']);
        Route::post('/study-programs', [CampusMasterDataController::class, 'storeStudyProgram']);
        Route::get('/study-programs/{studyProgram}', [CampusMasterDataController::class, 'showStudyProgram']);
        Route::put('/study-programs/{studyProgram}', [CampusMasterDataController::class, 'updateStudyProgram']);
        Route::patch('/study-programs/{studyProgram}/toggle-active', [CampusMasterDataController::class, 'toggleStudyProgramActive']);
    });

    Route::prefix('reports')->group(function (): void {
        Route::post('/', [ReportController::class, 'store'])
            ->middleware(['auth:sanctum', 'throttle:reports.submit']);

        Route::get('/track/{trackingCode}', [ReportController::class, 'track'])
            ->middleware('throttle:10,1');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/', [ReportController::class, 'index']);
            Route::post('/{report}/forward-to-case', [ReportController::class, 'forwardToCase']);
            Route::get('/{report}/reporter-evidence-files', [ReportEvidenceSubmissionController::class, 'indexForOversightReport']);
            Route::get('/{report}', [ReportController::class, 'show']);
        });
    });

    Route::prefix('reporter-registrations')->group(function (): void {
        Route::post('/', [ReporterRegistrationController::class, 'store'])
            ->middleware('throttle:reporter.registration');
        Route::patch('/correct', [ReporterRegistrationController::class, 'correct'])
            ->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/', [ReporterRegistrationController::class, 'index']);
            Route::get('/{reporterRegistration}', [ReporterRegistrationController::class, 'show']);
            Route::patch('/{reporterRegistration}/approve', [ReporterRegistrationController::class, 'approve']);
            Route::patch('/{reporterRegistration}/reject', [ReporterRegistrationController::class, 'reject']);
        });
    });

    Route::middleware('auth:sanctum')->prefix('cases')->group(function (): void {
        Route::get('/', [CaseController::class, 'index']);
        Route::get('/{case}/reporter-evidence-files', [ReportEvidenceSubmissionController::class, 'indexForCase']);
        Route::post('/{case}/investigations', [InvestigationController::class, 'storeForCase']);
        Route::get('/{case}/investigations', [InvestigationController::class, 'indexForCase']);
        Route::post('/{case}/recommendations', [RecommendationController::class, 'storeForCase']);
        Route::get('/{case}/recommendations', [RecommendationController::class, 'indexForCase']);
        Route::get('/{case}/final-summary', [CaseFinalSummaryController::class, 'show']);
        Route::post('/{case}/final-summary', [CaseFinalSummaryController::class, 'store']);
        Route::patch('/{case}/final-summary', [CaseFinalSummaryController::class, 'update']);
        Route::post('/{case}/final-summary/publish', [CaseFinalSummaryController::class, 'publish']);
        Route::post('/{case}/close', [CaseController::class, 'close']);
        Route::get('/{case}', [CaseController::class, 'show']);
        Route::patch('/{case}/status', [CaseController::class, 'updateStatus']);
        Route::patch('/{case}/assessment', [CaseController::class, 'updateAssessment']);
        Route::patch('/{case}/assign', [CaseController::class, 'assign']);
    });

    Route::middleware('auth:sanctum')->prefix('investigations')->group(function (): void {
        Route::post('/{investigation}/evidences', [EvidenceController::class, 'storeForInvestigation']);
        Route::get('/{investigation}/evidences', [EvidenceController::class, 'indexForInvestigation']);
        Route::get('/{investigation}/status-options', [InvestigationController::class, 'statusOptions']);
        Route::get('/{investigation}', [InvestigationController::class, 'show']);
        Route::patch('/{investigation}/status', [InvestigationController::class, 'updateStatus']);
        Route::post('/{investigation}/activities', [InvestigationController::class, 'storeActivity']);
    });

    Route::middleware('auth:sanctum')->prefix('evidences')->group(function (): void {
        Route::post('/{evidence}/file', [EvidenceController::class, 'uploadFile'])
            ->middleware('throttle:evidence.upload')
            ->name('evidence.upload');
        Route::get('/{evidence}/file', [EvidenceController::class, 'downloadFile'])->name('evidence.download');
        Route::get('/{evidence}/preview', [EvidenceController::class, 'previewFile'])->name('evidence.preview');
        Route::get('/{evidence}', [EvidenceController::class, 'show']);
        Route::patch('/{evidence}', [EvidenceController::class, 'update']);
        Route::patch('/{evidence}/status', [EvidenceController::class, 'updateStatus']);
        Route::get('/{evidence}/custody', [EvidenceController::class, 'custody']);
    });

    Route::middleware('auth:sanctum')->prefix('recommendations')->group(function (): void {
        Route::post('/{recommendation}/decisions', [DecisionController::class, 'storeForRecommendation']);
        Route::get('/{recommendation}/decisions', [DecisionController::class, 'indexForRecommendation']);
        Route::get('/{recommendation}/status-options', [RecommendationController::class, 'statusOptions']);
        Route::get('/{recommendation}', [RecommendationController::class, 'show']);
        Route::patch('/{recommendation}', [RecommendationController::class, 'update']);
        Route::post('/{recommendation}/submit', [RecommendationController::class, 'submit']);
        Route::post('/{recommendation}/review', [RecommendationController::class, 'review'])->name('recommendation.review');
        Route::patch('/{recommendation}/status', [RecommendationController::class, 'updateStatus']);
    });

    Route::middleware('auth:sanctum')->prefix('decisions')->group(function (): void {
        Route::post('/{decision}/recoveries', [RecoveryController::class, 'storeForDecision']);
        Route::get('/{decision}/recoveries', [RecoveryController::class, 'indexForDecision']);
        Route::get('/{decision}/status-options', [DecisionController::class, 'statusOptions']);
        Route::get('/{decision}', [DecisionController::class, 'show']);
        Route::patch('/{decision}', [DecisionController::class, 'update']);
        Route::patch('/{decision}/status', [DecisionController::class, 'updateStatus']);
    });

    Route::middleware('auth:sanctum')->prefix('recoveries')->group(function (): void {
        Route::get('/{recovery}/status-options', [RecoveryController::class, 'statusOptions']);
        Route::get('/{recovery}', [RecoveryController::class, 'show']);
        Route::patch('/{recovery}', [RecoveryController::class, 'update']);
        Route::patch('/{recovery}/status', [RecoveryController::class, 'updateStatus']);
        Route::post('/{recovery}/monitoring', [RecoveryController::class, 'storeMonitoring']);
        Route::get('/{recovery}/monitoring', [RecoveryController::class, 'indexMonitoring']);
    });

    Route::middleware('auth:sanctum')->prefix('audit-logs')->group(function (): void {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/summary', [AuditLogController::class, 'summary'])->name('audit.oversight');
        Route::get('/oversight', [AuditLogController::class, 'oversight'])->name('audit.oversight.items');
        Route::get('/export', [AuditLogController::class, 'export'])->name('audit.export');
        Route::get('/{auditLog:public_id}', [AuditLogController::class, 'show'])
            ->whereUuid('auditLog')
            ->name('audit.show');
    });

    Route::middleware('auth:sanctum')->prefix('break-glass')->group(function (): void {
        Route::post('/request', [BreakGlassController::class, 'request']);
        Route::get('/pending', [BreakGlassController::class, 'pending']);
        Route::get('/history', [BreakGlassController::class, 'history']);
        Route::get('/mine', [BreakGlassController::class, 'mine']);
        Route::get('/{breakGlassRequest}', [BreakGlassController::class, 'show']);
        Route::patch('/{breakGlassRequest}/approve', [BreakGlassController::class, 'approve'])->name('break_glass.approve');
        Route::patch('/{breakGlassRequest}/deny', [BreakGlassController::class, 'deny'])->name('break_glass.deny');
        Route::patch('/{breakGlassRequest}/revoke', [BreakGlassController::class, 'revoke'])->name('break_glass.revoke');
        Route::post('/{breakGlassRequest}/reveal', [BreakGlassController::class, 'reveal'])->name('break_glass.reveal');
    });

    Route::middleware(['private.no-store', 'auth:sanctum'])->prefix('dashboard')->group(function (): void {
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/reports', [DashboardController::class, 'reports']);
        Route::get('/cases', [DashboardController::class, 'cases']);
        Route::get('/workflow', [DashboardController::class, 'workflow']);
        Route::get('/evidence', [DashboardController::class, 'evidence']);
    });

    Route::middleware('auth:sanctum')->prefix('notifications')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index']);
        Route::patch('/read-all', [NotificationController::class, 'readAll']);
        Route::patch('/{notification}/read', [NotificationController::class, 'markRead']);
    });

    Route::middleware('auth:sanctum')->prefix('portal')->group(function (): void {
        Route::get('/summary', [PortalController::class, 'summary']);
        Route::get('/reports', [PortalController::class, 'reports']);
        Route::get('/reports/{registrationNumber}/evidence-files', [ReportEvidenceSubmissionController::class, 'indexForReporter']);
        Route::post('/reports/{registrationNumber}/evidence-files', [ReportEvidenceSubmissionController::class, 'storeForReporter'])
            ->middleware('throttle:reporter.evidence.upload');
        Route::get('/evidence-files/{uuid}/download', [ReportEvidenceSubmissionController::class, 'downloadForReporter']);
        Route::get('/evidence-files/{uuid}/preview', [ReportEvidenceSubmissionController::class, 'previewForReporter']);
        Route::get('/reports/{registrationNumber}/timeline', [PortalController::class, 'reportTimeline']);
        Route::get('/reports/{registrationNumber}/handling-progress', [PortalController::class, 'reportHandlingProgress']);
        Route::get('/reports/{registrationNumber}', [PortalController::class, 'report']);
        Route::get('/notifications', [PortalController::class, 'notifications']);
    });

    Route::middleware('auth:sanctum')->prefix('reporter-evidence-files')->group(function (): void {
        Route::get('/{uuid}/download', [ReportEvidenceSubmissionController::class, 'downloadForSatgas'])->name('reporter_evidence.download');
        Route::get('/{uuid}/preview', [ReportEvidenceSubmissionController::class, 'previewForSatgas'])->name('reporter_evidence.preview');
    });

    Route::middleware('auth:sanctum')->prefix('users')->group(function (): void {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/reporters', [UserController::class, 'storeReporter']);
        Route::get('/lookup', [UserController::class, 'lookup']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::patch('/{user}/activate', [UserController::class, 'activate']);
        Route::patch('/{user}/deactivate', [UserController::class, 'deactivate']);
        Route::patch('/{user}/reset-password', [UserController::class, 'resetPassword']);
        Route::patch('/{user}/role', [UserController::class, 'role']);
    });

    Route::middleware('auth:sanctum')->prefix('my-work')->group(function (): void {
        Route::get('/summary', [MyWorkController::class, 'summary']);
        Route::get('/cases', [MyWorkController::class, 'cases']);
        Route::get('/investigations', [MyWorkController::class, 'investigations']);
        Route::get('/recommendations', [MyWorkController::class, 'recommendations']);
    });
});
