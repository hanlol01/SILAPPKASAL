<?php

namespace Tests\Feature;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\ReportStatus;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalReportTimelineTest extends TestCase
{
    use RefreshDatabase;

    private const SAFE_STAGES = [
        'laporan_dikirim',
        'laporan_ditinjau',
        'proses_penanganan',
        'selesai',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
    }

    public function test_reporter_can_view_own_report_timeline_with_safe_ordered_stages(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $report = $this->makeReport($reporter, 'SLP-20260706-0001', ReportStatus::UnderReview, [
            'submitted_at' => now()->subDays(3),
            'reviewed_at' => now()->subDays(2),
        ]);

        Sanctum::actingAs($reporter, ['*']);

        $response = $this->getJson("/api/v1/portal/reports/{$report->registration_number}/timeline")
            ->assertOk()
            ->assertJsonPath('data.registration_number', $report->registration_number)
            ->assertJsonPath('data.portal_status', 'Under Review')
            ->assertJsonPath('data.is_completed', false)
            ->assertJsonCount(2, 'data.events')
            ->assertJsonPath('data.events.0.stage', 'laporan_dikirim')
            ->assertJsonPath('data.events.1.stage', 'laporan_ditinjau');

        foreach ($response->json('data.events') as $event) {
            $this->assertContains($event['stage'], self::SAFE_STAGES);
            $this->assertSame(['stage', 'occurred_at'], array_keys($event));
        }
    }

    public function test_completed_case_timeline_includes_safe_completion_stage(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $report = $this->makeReport($reporter, 'SLP-20260706-0002', ReportStatus::Forwarded, [
            'submitted_at' => now()->subDays(10),
            'reviewed_at' => now()->subDays(9),
            'forwarded_at' => now()->subDays(8),
        ]);
        $this->makeCase($report, CaseStatusEnum::Closed, now()->subDay());

        Sanctum::actingAs($reporter, ['*']);

        $this->getJson("/api/v1/portal/reports/{$report->registration_number}/timeline")
            ->assertOk()
            ->assertJsonPath('data.portal_status', 'Completed')
            ->assertJsonPath('data.is_completed', true)
            ->assertJsonCount(4, 'data.events')
            ->assertJsonPath('data.events.0.stage', 'laporan_dikirim')
            ->assertJsonPath('data.events.1.stage', 'laporan_ditinjau')
            ->assertJsonPath('data.events.2.stage', 'proses_penanganan')
            ->assertJsonPath('data.events.3.stage', 'selesai');
    }

    public function test_reporter_cannot_access_another_reporters_timeline(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $otherReporter = $this->makeUser('reporter', 'other@example.test');
        $otherReport = $this->makeReport($otherReporter, 'SLP-20260706-0003', ReportStatus::Submitted);

        Sanctum::actingAs($reporter, ['*']);

        $this->getJson("/api/v1/portal/reports/{$otherReport->registration_number}/timeline")
            ->assertNotFound();

        $this->getJson('/api/v1/portal/reports/SLP-DOES-NOT-EXIST/timeline')
            ->assertNotFound();
    }

    public function test_guests_and_non_reporters_cannot_access_timeline(): void
    {
        $this->getJson('/api/v1/portal/reports/SLP-20260706-0004/timeline')
            ->assertUnauthorized();

        foreach ([
            $this->makeUser('admin', 'admin@example.test'),
            $this->makeUser('super_admin', 'super@example.test'),
            $this->makeUser('satgas_ppks', 'satgas@example.test'),
        ] as $user) {
            Sanctum::actingAs($user, ['*']);

            $this->getJson('/api/v1/portal/reports/SLP-20260706-0004/timeline')
                ->assertForbidden();
        }
    }

    public function test_timeline_response_contains_no_sensitive_fields_or_internal_codes(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-handler@example.test', 'Satgas Handler Name');
        $report = $this->makeReport($reporter, 'SLP-20260706-0005', ReportStatus::Forwarded, [
            'submitted_at' => now()->subDays(6),
            'reviewed_at' => now()->subDays(5),
            'forwarded_at' => now()->subDays(4),
        ]);
        $case = $this->makeCase($report, CaseStatusEnum::Closed, now()->subDay());
        $case->assignments()->create([
            'satgas_id' => $satgas->id,
            'assigned_by' => null,
            'is_lead' => true,
            'is_active' => true,
            'assigned_at' => now()->subDays(4),
        ]);

        Sanctum::actingAs($reporter, ['*']);

        $response = $this->getJson("/api/v1/portal/reports/{$report->registration_number}/timeline")
            ->assertOk()
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.case')
            ->assertJsonMissingPath('data.assignments')
            ->assertJsonMissingPath('data.report')
            ->assertJsonMissingPath('data.chronology')
            ->assertJsonMissingPath('data.admin_notes')
            ->assertJsonMissing(['stage' => 'forwarded'])
            ->assertJsonMissing(['stage' => 'closed'])
            ->assertJsonMissing(['stage' => 'investigation']);

        foreach ($response->json('data.events') as $event) {
            $this->assertContains($event['stage'], self::SAFE_STAGES);
            $this->assertSame(['stage', 'occurred_at'], array_keys($event));
        }

        $raw = $response->getContent();
        $this->assertStringNotContainsString('Satgas Handler Name', $raw);
        $this->assertStringNotContainsString('Sensitive', $raw);
        $this->assertStringNotContainsString('Internal admin notes', $raw);
    }

    public function test_case_detail_exposes_safe_report_submitted_timestamp_for_internal_timeline(): void
    {
        $admin = $this->makeUser('admin', 'admin@example.test');
        $reporter = $this->makeUser('reporter', 'reporter@example.test');
        $report = $this->makeReport($reporter, 'SLP-20260706-0006', ReportStatus::Forwarded, [
            'submitted_at' => now()->subDays(3),
            'forwarded_at' => now()->subDays(2),
        ]);
        $case = $this->makeCase($report, CaseStatusEnum::Investigation);

        Sanctum::actingAs($admin, ['*']);

        $this->getJson("/api/v1/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.report_submitted_at', $report->submitted_at->toJSON());
    }

    private function makeUser(string $roleCode, string $email, ?string $name = null): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => $name ?? "{$roleCode} User",
            'email' => $email,
            'password' => 'secret-password',
            'is_active' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeReport(?User $reporter, string $registrationNumber, ReportStatus $status, array $overrides = []): Report
    {
        return Report::query()->create(array_merge([
            'reporter_id' => $reporter?->id,
            'registration_number' => $registrationNumber,
            'tracking_code' => null,
            'report_type' => 'confidential',
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
            'status' => $status->value,
            'priority' => 'PRIO-03',
            'admin_notes' => 'Internal admin notes must not be exposed.',
            'rejection_reason' => null,
            'submitted_at' => now(),
            'reviewed_at' => null,
            'forwarded_at' => null,
        ], $overrides));
    }

    private function makeCase(Report $report, CaseStatusEnum $statusName, ?\DateTimeInterface $closedAt = null): CaseRecord
    {
        $status = CaseStatus::query()->where('name', $statusName->value)->firstOrFail();

        return CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.str_replace('SLP-', '', $report->registration_number),
            'status_code' => $status->code,
            'priority_code' => 'PRIO-03',
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => $report->forwarded_at ?? now(),
            'closed_at' => $statusName === CaseStatusEnum::Closed ? ($closedAt ?? now()) : null,
        ]);
    }
}
