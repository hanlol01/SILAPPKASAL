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

    public function test_anonymous_report_submission_does_not_store_identity_or_phone(): void
    {
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

        $this->assertNull($report->reporter_id);
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
        $token = $user->createToken('web-login', ['*'], now()->addDay())->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/reports', $this->payload([
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
        $token = $user->createToken('web-login', ['*'], now()->addDay())->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/reports', $this->payload([
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
            ->assertJsonMissingPath('data.chronology')
            ->assertJsonMissingPath('data.incident_location')
            ->assertJsonMissingPath('data.reporter_phone');

        $report->delete();

        $this->getJson("/api/v1/reports/track/{$report->tracking_code}")
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_reporter_can_read_own_reports_only(): void
    {
        $reporter = $this->makeUser('reporter', 'reporter-a@university.ac.id');
        $otherReporter = $this->makeUser('reporter', 'reporter-b@university.ac.id');
        $ownReport = $this->createIdentifiedReport($reporter);
        $otherReport = $this->createIdentifiedReport($otherReporter);
        $anonymousReport = $this->createAnonymousReport();
        $token = $reporter->createToken('web-login', ['*'], now()->addDay())->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/reports')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $ownReport->id);

        $this->withToken($token)
            ->getJson("/api/v1/reports/{$ownReport->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ownReport->id);

        $this->withToken($token)
            ->getJson("/api/v1/reports/{$otherReport->id}")
            ->assertForbidden();

        $this->withToken($token)
            ->getJson("/api/v1/reports/{$anonymousReport->id}")
            ->assertForbidden();
    }

    public function test_admin_reads_metadata_without_sensitive_fields(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $report = $this->createAnonymousReport();
        $token = $admin->createToken('web-login', ['*'], now()->addDay())->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/reports')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('data.0.chronology')
            ->assertJsonMissingPath('data.0.incident_location');

        $this->withToken($token)
            ->getJson("/api/v1/reports/{$report->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.chronology')
            ->assertJsonMissingPath('data.incident_location')
            ->assertJsonMissingPath('data.respondent_name');
    }

    public function test_invalid_master_data_returns_validation_error(): void
    {
        $this->postJson('/api/v1/reports', $this->payload([
            'report_type' => 'anonymous',
            'category_code' => 'UNKNOWN',
        ]))->assertUnprocessable()
            ->assertJsonPath('success', false);
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

    private function createAnonymousReport(): Report
    {
        $response = $this->postJson('/api/v1/reports', $this->payload([
            'report_type' => 'anonymous',
        ]));

        $response->assertCreated();

        return Report::query()->findOrFail($response->json('data.id'));
    }

    private function createIdentifiedReport(User $user): Report
    {
        $token = $user->createToken('web-login', ['*'], now()->addDay())->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/reports', $this->payload([
                'report_type' => 'open',
            ]));

        $response->assertCreated();

        return Report::query()->findOrFail($response->json('data.id'));
    }
}
