<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
        Cache::clear();
    }

    public function test_unauthenticated_report_submission_is_rejected(): void
    {
        $this->postJson('/api/v1/reports', $this->payload([
            'report_type' => 'anonymous',
        ]))->assertUnauthorized();
    }

    public function test_authenticated_anonymous_report_submission_stores_reporter_and_no_phone(): void
    {
        $reporter = $this->makeUser('reporter');
        Sanctum::actingAs($reporter, ['*']);

        $response = $this->postJson('/api/v1/reports', $this->payload([
            'report_type' => 'anonymous',
            'reporter_phone' => '6281234567890',
        ]));

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);

        $response = $this->postJson('/api/v1/reports', $this->payload([
                'report_type' => 'anonymous',
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', ReportStatus::Submitted->value)
            ->assertJsonPath('data.report_type', 'anonymous')
            ->assertJsonStructure(['data' => ['registration_number', 'tracking_code', 'submitted_at']]);

        $trackingCode = $response->json('data.tracking_code');
        $this->assertSame(16, strlen(str_replace('-', '', $trackingCode)));

        $report = Report::query()->firstOrFail();

        $this->assertSame($reporter->id, $report->reporter_id);
        $this->assertNull($report->reporter_phone_encrypted);
        $this->assertArrayNotHasKey('ip_address', $report->getAttributes());
        $this->assertArrayNotHasKey('device_data', $report->getAttributes());
    }

    public function test_identified_report_requires_authentication(): void
    {
        $this->postJson('/api/v1/reports', $this->payload([
            'report_type' => 'open',
        ]))->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_identified_report_submission_with_token_stores_reporter(): void
    {
        $user = $this->makeUser('reporter');
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/reports', $this->payload([
                'report_type' => 'open',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.tracking_code', null)
            ->assertJsonPath('data.status', ReportStatus::Submitted->value);

        $this->assertDatabaseHas('reports', [
            'id' => $response->json('data.id'),
            'reporter_id' => $user->id,
            'tracking_code' => null,
        ]);
    }

    public function test_confidential_report_may_store_encrypted_phone(): void
    {
        $user = $this->makeUser('reporter');
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/reports', $this->payload([
                'report_type' => 'confidential',
                'reporter_phone' => '6281234567890',
        ]));

        $response->assertCreated();

        $report = Report::query()->firstOrFail();

        $this->assertSame('6281234567890', $report->reporter_phone_encrypted);
        $this->assertNotSame('6281234567890', $report->getRawOriginal('reporter_phone_encrypted'));
    }

    public function test_tracking_endpoint_returns_limited_metadata_and_excludes_soft_deleted_reports(): void
    {
        $report = $this->createAnonymousReport();

        $this->getJson("/api/v1/reports/track/{$report->tracking_code}")
            ->assertOk()
            ->assertJsonPath('data.registration_number', $report->registration_number)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonMissingPath('data.chronology')
            ->assertJsonMissingPath('data.incident_location')
            ->assertJsonMissingPath('data.reporter_phone')
            ->assertJsonMissingPath('data.case');

        $report->delete();

        $this->getJson("/api/v1/reports/track/{$report->tracking_code}")
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'tracking_not_found');
    }

    public function test_reporter_can_read_own_reports_only(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter-a@university.ac.id');
        $otherReporter = $this->makeUser('reporter', 'reporter-b@university.ac.id');
        $ownReport = $this->createIdentifiedReport($reporter);
        $otherReport = $this->createIdentifiedReport($otherReporter);
        $anonymousReport = $this->createAnonymousReport($reporter);
        Sanctum::actingAs($reporter, ['*']);

        $this->getJson('/api/v1/reports')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $ownReport->id);

        $this->getJson("/api/v1/reports/{$ownReport->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ownReport->id);

        $this->getJson("/api/v1/reports/{$otherReport->id}")
            ->assertForbidden();

        $this->getJson("/api/v1/reports/{$anonymousReport->id}")
            ->assertForbidden();
    }

    public function test_admin_reads_metadata_with_masked_identity_for_anonymous_reports(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $reporter = $this->makeUser('reporter', 'anonymous-owner@university.ac.id');
        $report = $this->createAnonymousReport($reporter);
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/v1/reports')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.is_anonymous', true)
            ->assertJsonPath('data.0.reporter.masked', true)
            ->assertJsonMissingPath('data.0.reporter.id')
            ->assertJsonMissingPath('data.0.reporter.name')
            ->assertJsonMissingPath('data.0.chronology')
            ->assertJsonMissingPath('data.0.incident_location');

        $this->getJson("/api/v1/reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('data.is_anonymous', true)
            ->assertJsonPath('data.reporter.masked', true)
            ->assertJsonMissingPath('data.reporter.id')
            ->assertJsonMissingPath('data.reporter.name')
            ->assertJsonMissingPath('data.chronology')
            ->assertJsonMissingPath('data.incident_location')
            ->assertJsonMissingPath('data.respondent_name');
    }

    public function test_admin_reads_non_anonymous_reporter_minimal_identity(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $reporter = $this->makeUser('reporter', 'identified-owner@university.ac.id');
        $report = $this->createIdentifiedReport($reporter);
        Sanctum::actingAs($admin, ['*']);

        $this->getJson("/api/v1/reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('data.is_anonymous', false)
            ->assertJsonPath('data.reporter.id', $reporter->id)
            ->assertJsonPath('data.reporter.name', 'reporter User')
            ->assertJsonMissingPath('data.reporter.email');
    }

    public function test_satgas_cannot_access_anonymous_report_identity_through_report_api(): void
    {
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $reporter = $this->makeUser('reporter', 'anonymous-owner@university.ac.id');
        $report = $this->createAnonymousReport($reporter);
        Sanctum::actingAs($satgas, ['*']);

        $this->getJson('/api/v1/reports')->assertForbidden();
        $this->getJson("/api/v1/reports/{$report->id}")->assertForbidden();
    }

    public function test_invalid_master_data_returns_validation_error(): void
    {
        $reporter = $this->makeUser('reporter');
        Sanctum::actingAs($reporter, ['*']);

        $this->postJson('/api/v1/reports', $this->payload([
            'report_type' => 'anonymous',
            'category_code' => 'UNKNOWN',
        ]))->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_incident_time_validation_uses_the_requested_api_locale(): void
    {
        $reporter = $this->makeUser('reporter');
        Sanctum::actingAs($reporter, ['*']);

        $this->withHeader('Accept-Language', 'id')
            ->postJson('/api/v1/reports', $this->payload(['incident_time' => '76:76']))
            ->assertUnprocessable()
            ->assertHeader('Content-Language', 'id')
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonPath('errors.incident_time.0', 'Format waktu tidak valid.');

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/reports', $this->payload(['incident_time' => '76:76']))
            ->assertUnprocessable()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonPath('errors.incident_time.0', 'Invalid time format.');
    }

    public function test_partial_respondent_information_requires_complete_respondent_context(): void
    {
        $reporter = $this->makeUser('reporter');
        Sanctum::actingAs($reporter, ['*']);

        $this->withHeader('Accept-Language', 'id')->postJson('/api/v1/reports', $this->payload([
            'respondent_name' => 'Nama Terlapor',
            'respondent_campus_status' => null,
            'respondent_relation' => null,
            'respondent_details' => null,
            'witness_info' => null,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors([
                'respondent_campus_status',
                'respondent_relation',
                'respondent_details',
            ])
            ->assertJsonPath(
                'errors.respondent_campus_status.0',
                'Lengkapi nama, status kampus, relasi, dan detail pihak terlapor jika informasi pihak terlapor diisi.',
            );
    }

    public function test_witness_information_without_respondent_context_is_allowed(): void
    {
        $reporter = $this->makeUser('reporter');
        Sanctum::actingAs($reporter, ['*']);

        $this->postJson('/api/v1/reports', $this->payload([
            'respondent_name' => null,
            'respondent_campus_status' => null,
            'respondent_relation' => null,
            'respondent_details' => null,
            'witness_info' => 'Saksi mengetahui kejadian dan dapat dihubungi oleh petugas.',
        ]))->assertCreated();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'report_type' => 'anonymous',
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi laporan ini sengaja dibuat cukup panjang untuk memenuhi batas minimum validasi.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Gedung utama kampus lantai dua',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Nama Terlapor',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'respondent_details' => 'Detail singkat mengenai terlapor.',
            'witness_info' => 'Ada saksi yang mengetahui kejadian.',
        ], $overrides);
    }

    private function makeUser(string $roleCode, ?string $email = null): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => "{$roleCode} User",
            'email' => $email ?? "{$roleCode}@university.ac.id",
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);
    }

    private function createAnonymousReport(?User $reporter = null): Report
    {
        $reporter ??= $this->makeUser('reporter', 'anonymous-reporter@university.ac.id');
        Sanctum::actingAs($reporter, ['*']);

        $response = $this->postJson('/api/v1/reports', $this->payload([
            'report_type' => 'anonymous',
        ]));

        $response->assertCreated();

        return Report::query()->findOrFail($response->json('data.id'));
    }

    private function createIdentifiedReport(User $user): Report
    {
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/reports', $this->payload([
            'report_type' => 'open',
        ]));

        $response->assertCreated();

        return Report::query()->findOrFail($response->json('data.id'));
    }
}
