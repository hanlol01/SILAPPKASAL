<?php

namespace Tests\Feature;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\ReportStatus;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use App\Notifications\WorkflowDatabaseNotification;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReporterPortalFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
    }

    public function test_reporter_can_view_portal_summary_with_safe_counts(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $otherReporter = $this->makeUser('reporter', 'other@example.test');

        $this->makeReport($reporter, 'SLP-20260611-0001', ReportStatus::Submitted);
        $this->makeCase($this->makeReport($reporter, 'SLP-20260611-0002', ReportStatus::Forwarded), CaseStatusEnum::Investigation);
        $this->makeCase($this->makeReport($reporter, 'SLP-20260611-0003', ReportStatus::Forwarded), CaseStatusEnum::Closed);
        $this->makeReport($otherReporter, 'SLP-20260611-0004', ReportStatus::Submitted);
        $this->makeReport(null, 'SLP-20260611-0005', ReportStatus::Submitted, 'anonymous');

        $reporter->notify(new WorkflowDatabaseNotification([
            'notification_type_code' => 'case_status_changed',
            'event' => 'case_status_changed',
            'title' => 'Status diperbarui',
            'body' => 'Status laporan Anda diperbarui.',
        ]));

        Sanctum::actingAs($reporter, ['*']);

        $this->getJson('/api/v1/portal/summary')
            ->assertOk()
            ->assertJsonPath('data.total_reports', 3)
            ->assertJsonPath('data.active_reports', 2)
            ->assertJsonPath('data.completed_reports', 1)
            ->assertJsonPath('data.unread_notifications', 1);
    }

    public function test_reporter_report_list_is_own_safe_and_uses_registration_number_identifier(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $otherReporter = $this->makeUser('reporter', 'other@example.test');
        $submitted = $this->makeReport($reporter, 'SLP-20260611-0001', ReportStatus::Submitted);
        $inProcess = $this->makeReport($reporter, 'SLP-20260611-0002', ReportStatus::Forwarded);
        $completed = $this->makeReport($reporter, 'SLP-20260611-0003', ReportStatus::Forwarded);
        $this->makeCase($inProcess, CaseStatusEnum::Investigation);
        $this->makeCase($completed, CaseStatusEnum::Closed);
        $this->makeReport($otherReporter, 'SLP-20260611-0004', ReportStatus::Submitted);

        Sanctum::actingAs($reporter, ['*']);

        $this->getJson('/api/v1/portal/reports')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['registration_number' => $submitted->registration_number])
            ->assertJsonFragment(['portal_status' => 'submitted'])
            ->assertJsonFragment(['portal_status' => 'in_process'])
            ->assertJsonFragment(['portal_status' => 'completed'])
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.status')
            ->assertJsonMissingPath('data.0.reviewed_at')
            ->assertJsonMissingPath('data.0.tracking_code')
            ->assertJsonMissingPath('data.0.chronology')
            ->assertJsonMissingPath('data.0.respondent_name')
            ->assertJsonMissingPath('data.0.case.status')
            ->assertJsonMissingPath('data.0.case.assignments');
    }

    public function test_reporter_portal_includes_own_anonymous_reports_but_not_legacy_null_identity_reports(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $anonymous = $this->makeReport($reporter, 'SLP-20260611-0101', ReportStatus::Submitted, 'anonymous');
        $legacyAnonymous = $this->makeReport(null, 'SLP-20260611-0102', ReportStatus::Submitted, 'anonymous');

        Sanctum::actingAs($reporter, ['*']);

        $this->getJson('/api/v1/portal/reports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'registration_number' => $anonymous->registration_number,
                'report_type' => 'anonymous',
            ])
            ->assertJsonMissing([
                'registration_number' => $legacyAnonymous->registration_number,
            ])
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.tracking_code');

        $this->getJson("/api/v1/portal/reports/{$anonymous->registration_number}")
            ->assertOk()
            ->assertJsonPath('data.registration_number', $anonymous->registration_number)
            ->assertJsonPath('data.report_type', 'anonymous')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.tracking_code');

        $this->getJson("/api/v1/portal/reports/{$legacyAnonymous->registration_number}")
            ->assertNotFound();
    }

    public function test_reporter_can_view_safe_detail_only_by_own_registration_number(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $otherReporter = $this->makeUser('reporter', 'other@example.test');
        $report = $this->makeReport($reporter, 'SLP-20260611-0001', ReportStatus::UnderReview);
        $otherReport = $this->makeReport($otherReporter, 'SLP-20260611-0002', ReportStatus::Submitted);

        Sanctum::actingAs($reporter, ['*']);

        $this->getJson("/api/v1/portal/reports/{$report->registration_number}")
            ->assertOk()
            ->assertJsonPath('data.registration_number', $report->registration_number)
            ->assertJsonPath('data.portal_status', 'under_review')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.reviewed_at')
            ->assertJsonMissingPath('data.admin_notes')
            ->assertJsonMissingPath('data.rejection_reason')
            ->assertJsonMissingPath('data.incident_location')
            ->assertJsonMissingPath('data.witness_info')
            ->assertJsonMissingPath('data.evidence')
            ->assertJsonMissingPath('data.staff');

        $this->getJson("/api/v1/portal/reports/{$otherReport->registration_number}")
            ->assertNotFound();
    }

    public function test_portal_notifications_are_own_and_read_only(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $otherReporter = $this->makeUser('reporter', 'other@example.test');

        $reporter->notify(new WorkflowDatabaseNotification([
            'notification_type_code' => 'case_assigned',
            'event' => 'case_assigned',
            'title' => 'Notifikasi Reporter',
            'body' => 'Laporan Anda sedang diproses.',
            'report_id' => 991,
            'case_id' => 992,
            'status_code' => 'internal_case_status',
        ]));
        $otherReporter->notify(new WorkflowDatabaseNotification([
            'notification_type_code' => 'case_assigned',
            'event' => 'case_assigned',
            'title' => 'Notifikasi Lain',
            'body' => 'Tidak boleh terlihat.',
        ]));

        Sanctum::actingAs($reporter, ['*']);

        $this->getJson('/api/v1/portal/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Notifikasi Reporter')
            ->assertJsonPath('data.0.data.notification_type_code', 'case_assigned')
            ->assertJsonPath('data.0.data.event', 'case_assigned')
            ->assertJsonMissingPath('data.0.data.report_id')
            ->assertJsonMissingPath('data.0.data.case_id')
            ->assertJsonMissingPath('data.0.data.status_code')
            ->assertJsonMissing(['title' => 'Notifikasi Lain']);

        $this->patchJson('/api/v1/portal/notifications/read-all')->assertNotFound();
        $this->patchJson('/api/v1/portal/notifications/'.$reporter->notifications()->first()->id.'/read')->assertNotFound();
    }

    public function test_non_reporters_cannot_access_portal(): void
    {
        foreach ([
            $this->makeUser('admin', 'admin@example.test'),
            $this->makeUser('super_admin', 'super@example.test'),
            $this->makeUser('satgas_ppks', 'satgas@example.test'),
        ] as $user) {
            Sanctum::actingAs($user, ['*']);

            $this->getJson('/api/v1/portal/summary')->assertForbidden();
            $this->getJson('/api/v1/portal/reports')->assertForbidden();
            $this->getJson('/api/v1/portal/reports/SLP-20260611-0001')->assertForbidden();
            $this->getJson('/api/v1/portal/notifications')->assertForbidden();
        }
    }

    public function test_guests_cannot_access_portal(): void
    {
        $this->getJson('/api/v1/portal/summary')->assertUnauthorized();
        $this->getJson('/api/v1/portal/reports')->assertUnauthorized();
        $this->getJson('/api/v1/portal/reports/SLP-20260611-0001')->assertUnauthorized();
        $this->getJson('/api/v1/portal/notifications')->assertUnauthorized();
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

    private function makeReport(?User $reporter, string $registrationNumber, ReportStatus $status, string $reportType = 'confidential'): Report
    {
        return Report::query()->create([
            'reporter_id' => $reporter?->id,
            'registration_number' => $registrationNumber,
            'tracking_code' => $reportType === 'anonymous' ? $this->trackingCode($registrationNumber) : null,
            'report_type' => $reportType,
            'category_code' => 'RCAT-01',
            'chronology' => 'Sensitive chronology must never be exposed in portal responses.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Sensitive incident location',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Sensitive respondent',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-01',
            'respondent_details' => 'Sensitive respondent details',
            'witness_info' => 'Sensitive witness info',
            'reporter_phone_encrypted' => $reportType === 'confidential' ? '081234567890' : null,
            'status' => $status->value,
            'priority' => 'PRIO-03',
            'admin_notes' => 'Internal admin notes must not be exposed.',
            'rejection_reason' => 'Internal rejection reason must not be exposed.',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'forwarded_at' => $status === ReportStatus::Forwarded ? now() : null,
        ]);
    }

    private function makeCase(Report $report, CaseStatusEnum $statusName): CaseRecord
    {
        $status = CaseStatus::query()->where('name', $statusName->value)->firstOrFail();

        return CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.str_replace('SLP-', '', $report->registration_number),
            'status_code' => $status->code,
            'priority_code' => 'PRIO-03',
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => now(),
            'closed_at' => $statusName === CaseStatusEnum::Closed ? now() : null,
        ]);
    }

    private function trackingCode(string $registrationNumber): string
    {
        return implode('-', str_split(strtoupper(substr(md5($registrationNumber), 0, 16)), 4));
    }
}
