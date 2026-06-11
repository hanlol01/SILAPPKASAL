<?php

namespace Tests\Feature;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\DecisionOutcome;
use App\Enums\DecisionStatus as DecisionStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Enums\ReportStatus;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Decision;
use App\Models\DecisionStatus;
use App\Models\Investigation;
use App\Models\InvestigationStatus;
use App\Models\Recommendation;
use App\Models\RecommendationStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
    }

    public function test_notifications_table_uses_laravel_database_notification_shape(): void
    {
        $this->assertTrue(Schema::hasTable('notifications'));
        $this->assertTrue(Schema::hasColumn('notifications', 'id'));
        $this->assertTrue(Schema::hasColumn('notifications', 'type'));
        $this->assertTrue(Schema::hasColumn('notifications', 'notifiable_type'));
        $this->assertTrue(Schema::hasColumn('notifications', 'notifiable_id'));
        $this->assertTrue(Schema::hasColumn('notifications', 'data'));
        $this->assertTrue(Schema::hasColumn('notifications', 'read_at'));
        $this->assertFalse(Schema::hasColumn('notifications', 'provider_response'));
        $this->assertFalse(Schema::hasColumn('notifications', 'delivery_status'));
    }

    public function test_user_can_list_and_mark_only_own_notifications(): void
    {
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other@university.ac.id');

        app(NotificationService::class)->caseAssigned($this->makeCase($satgas), [$satgas->id]);
        app(NotificationService::class)->caseAssigned($this->makeCase($otherSatgas), [$otherSatgas->id]);

        $notification = $satgas->notifications()->firstOrFail();
        $otherNotification = $otherSatgas->notifications()->firstOrFail();

        $this->actingAsApi($satgas);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.notification_type_code', NotificationService::TYPE_CASE_ASSIGNED)
            ->assertJsonMissingPath('data.0.reporter')
            ->assertJsonMissingPath('data.0.tracking_code')
            ->assertJsonMissingPath('data.0.findings')
            ->assertJsonMissingPath('data.0.decision_content');

        $this->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id);

        $this->assertNotNull($notification->refresh()->read_at);

        $this->patchJson("/api/v1/notifications/{$otherNotification->id}/read")
            ->assertNotFound();
    }

    public function test_read_all_updates_only_current_user_notifications(): void
    {
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other@university.ac.id');

        app(NotificationService::class)->caseAssigned($this->makeCase($satgas), [$satgas->id]);
        app(NotificationService::class)->caseAssigned($this->makeCase($otherSatgas), [$otherSatgas->id]);

        $this->actingAsApi($satgas);
        $this->patchJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated_count', 1);

        $this->assertSame(0, $satgas->unreadNotifications()->count());
        $this->assertSame(1, $otherSatgas->unreadNotifications()->count());
    }

    public function test_notification_triggers_create_metadata_only_payloads(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeCase($satgas);
        $recommendation = $this->makeSubmittedRecommendation($case, $satgas);
        $decision = $this->makeFinalizedDecision($recommendation, $admin);

        app(NotificationService::class)->caseStatusChanged($case);
        app(NotificationService::class)->recommendationSubmittedToLeader($recommendation);
        app(NotificationService::class)->decisionFinalized($decision);

        $satgasPayloads = $satgas->notifications()->get()->pluck('data')->all();
        $adminPayloads = $admin->notifications()->get()->pluck('data')->all();

        $this->assertNotEmpty($satgasPayloads);
        $this->assertNotEmpty($adminPayloads);

        foreach (array_merge($satgasPayloads, $adminPayloads) as $payload) {
            $this->assertArrayHasKey('notification_type_code', $payload);
            $json = json_encode($payload);
            $this->assertStringNotContainsString('chronology', $json);
            $this->assertStringNotContainsString('reporter', $json);
            $this->assertStringNotContainsString('victim', $json);
            $this->assertStringNotContainsString('tracking', $json);
            $this->assertStringNotContainsString('findings', $json);
            $this->assertStringNotContainsString('recommended_actions', $json);
            $this->assertStringNotContainsString('decision_content', $json);
            $this->assertStringNotContainsString('recovery', $json);
            $this->assertStringNotContainsString('evidence', $json);
        }
    }

    public function test_notifications_are_queued_on_notifications_queue(): void
    {
        Notification::fake();

        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');

        app(NotificationService::class)->caseAssigned($this->makeCase($satgas), [$satgas->id]);

        Notification::assertSentTo($satgas, \App\Notifications\WorkflowDatabaseNotification::class, function ($notification): bool {
            return in_array('database', $notification->via(new User()), true);
        });
    }

    private function makeSubmittedRecommendation(CaseRecord $case, User $satgas): Recommendation
    {
        $investigationStatus = InvestigationStatus::query()->where('name', 'completed')->firstOrFail();
        $investigation = Investigation::query()->create([
            'case_id' => $case->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => $investigationStatus->code,
            'plan_summary' => 'Demo plan',
            'findings' => 'Sensitive findings should never be notified',
            'conclusion' => 'Sensitive conclusion should never be notified',
            'started_at' => now()->subDays(3),
            'completed_at' => now()->subDay(),
        ]);

        $status = RecommendationStatus::query()->where('name', RecommendationStatusEnum::SubmittedToLeader->value)->firstOrFail();

        return Recommendation::query()->create([
            'case_id' => $case->id,
            'investigation_id' => $investigation->id,
            'author_id' => $satgas->id,
            'status_code' => $status->code,
            'conclusion' => 'Sensitive recommendation conclusion',
            'recommended_actions' => 'Sensitive recommendation actions',
            'submitted_at' => now(),
        ]);
    }

    private function makeFinalizedDecision(Recommendation $recommendation, User $admin): Decision
    {
        $status = DecisionStatus::query()->where('name', DecisionStatusEnum::Finalized->value)->firstOrFail();

        return Decision::query()->create([
            'recommendation_id' => $recommendation->id,
            'recorder_id' => $admin->id,
            'status_code' => $status->code,
            'outcome_code' => DecisionOutcome::Accepted->value,
            'decision_date' => now()->toDateString(),
            'decision_summary' => 'Sensitive decision summary',
            'decision_content' => 'Sensitive decision content',
            'recorded_at' => now(),
            'finalized_at' => now(),
        ]);
    }

    private function makeCase(User $satgas): CaseRecord
    {
        $admin = User::query()->whereHas('role', fn ($query) => $query->where('code', 'admin'))->first()
            ?? $this->makeUser('admin', 'admin-seed@university.ac.id');

        $report = Report::query()->create([
            'registration_number' => 'SLP-'.now()->format('Ymd').'-'.str_pad((string) (Report::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'tracking_code' => null,
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'Sensitive chronology must not enter notification payloads.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Sensitive location',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Sensitive respondent',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'status' => ReportStatus::Forwarded->value,
            'priority' => 'PRIO-03',
            'submitted_at' => now(),
            'forwarded_at' => now(),
        ]);

        $status = CaseStatus::query()->where('name', CaseStatusEnum::Decision->value)->firstOrFail();

        $case = CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.now()->format('Ymd').'-'.str_pad((string) (CaseRecord::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'status_code' => $status->code,
            'priority_code' => 'PRIO-03',
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => now(),
        ]);

        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $satgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => true,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        return $case;
    }

    private function makeUser(string $roleCode, string $email): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => "{$roleCode} User",
            'email' => $email,
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);
    }

    private function actingAsApi(User $user): void
    {
        Sanctum::actingAs($user, ['*']);
    }
}
