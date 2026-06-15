<?php

namespace Database\Seeders;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\InvestigationActivityType;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Enums\ReportStatus;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus as CaseStatusModel;
use App\Models\Investigation;
use App\Models\InvestigationActivity;
use App\Models\InvestigationStatus;
use App\Models\Recommendation;
use App\Models\RecommendationStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DeploymentDemoReportSeeder extends Seeder
{
    private const PASSWORD = 'DemoPass123!';

    /**
     * @var array<string, array{
     *     registration_number: string,
     *     report_type: string,
     *     category_code: string,
     *     chronology: string,
     *     incident_date: string,
     *     incident_time: string,
     *     incident_location: string,
     *     location_type: string,
     *     respondent_name: string,
     *     respondent_campus_status: string,
     *     respondent_relation: string,
     *     respondent_details: string,
     *     witness_info: string,
     *     status: ReportStatus,
     *     priority: string,
     *     submitted_days_ago: int,
     *     reviewed_days_ago: int|null,
     *     forwarded_days_ago: int|null,
     *     case_number?: string,
     *     case_status?: CaseStatusEnum,
     *     risk_level_code?: string,
     *     investigation_status?: InvestigationStatusEnum,
     *     recommendation_status?: RecommendationStatusEnum
     * }>
     */
    private array $scenarios = [
        'new_report' => [
            'registration_number' => 'SLP-20260615-0001',
            'report_type' => 'confidential',
            'category_code' => 'RCAT-05',
            'chronology' => 'Pelapor menyampaikan adanya pesan berulang bernuansa seksual dari seseorang yang dikenal melalui kegiatan kampus. Pelapor meminta perlindungan identitas dan arahan tindak lanjut karena komunikasi tersebut mulai mengganggu aktivitas akademik.',
            'incident_date' => '2026-06-08',
            'incident_time' => '20:15',
            'incident_location' => 'Media komunikasi daring terkait kegiatan kampus',
            'location_type' => 'LOC-04',
            'respondent_name' => 'Terlapor Simulasi A',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-06',
            'respondent_details' => 'Mahasiswa yang berada dalam lingkup kegiatan organisasi kampus yang sama.',
            'witness_info' => 'Pelapor menyebut ada satu rekan yang mengetahui adanya pesan lanjutan.',
            'status' => ReportStatus::Submitted,
            'priority' => 'PRIO-02',
            'submitted_days_ago' => 2,
            'reviewed_days_ago' => null,
            'forwarded_days_ago' => null,
        ],
        'admin_review' => [
            'registration_number' => 'SLP-20260615-0002',
            'report_type' => 'open',
            'category_code' => 'RCAT-01',
            'chronology' => 'Pelapor melaporkan komentar seksual yang berulang dalam ruang diskusi akademik. Pelapor bersedia dihubungi admin untuk verifikasi kronologi dan kelengkapan bukti pendukung.',
            'incident_date' => '2026-06-05',
            'incident_time' => '13:40',
            'incident_location' => 'Ruang diskusi fakultas',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Terlapor Simulasi B',
            'respondent_campus_status' => 'CAMP-02',
            'respondent_relation' => 'REL-01',
            'respondent_details' => 'Pihak terlapor berada dalam relasi akademik dengan pelapor.',
            'witness_info' => 'Dua peserta diskusi disebut mengetahui sebagian kejadian.',
            'status' => ReportStatus::UnderReview,
            'priority' => 'PRIO-03',
            'submitted_days_ago' => 5,
            'reviewed_days_ago' => 4,
            'forwarded_days_ago' => null,
        ],
        'assigned_assessment' => [
            'registration_number' => 'SLP-20260615-0003',
            'report_type' => 'confidential',
            'category_code' => 'RCAT-03',
            'chronology' => 'Pelapor melaporkan kontak fisik yang tidak diinginkan setelah kegiatan kampus. Pelapor meminta pendampingan karena masih harus bertemu terlapor dalam lingkungan akademik.',
            'incident_date' => '2026-06-01',
            'incident_time' => '17:30',
            'incident_location' => 'Area koridor gedung perkuliahan',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Terlapor Simulasi C',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'respondent_details' => 'Mahasiswa dari program studi berbeda yang mengikuti kegiatan kampus yang sama.',
            'witness_info' => 'Pelapor menyebut satu saksi berada di sekitar lokasi setelah kejadian.',
            'status' => ReportStatus::Forwarded,
            'priority' => 'PRIO-01',
            'submitted_days_ago' => 9,
            'reviewed_days_ago' => 8,
            'forwarded_days_ago' => 7,
            'case_number' => 'CASE-20260615-0001',
            'case_status' => CaseStatusEnum::Assessment,
            'risk_level_code' => 'RISK-03',
        ],
        'assigned_investigation' => [
            'registration_number' => 'SLP-20260615-0004',
            'report_type' => 'confidential',
            'category_code' => 'RCAT-10',
            'chronology' => 'Pelapor menyampaikan dugaan penyalahgunaan posisi dalam kegiatan kampus untuk meminta interaksi pribadi yang membuat pelapor merasa tertekan. Pelapor meminta kasus diproses dengan pendampingan Satgas.',
            'incident_date' => '2026-05-28',
            'incident_time' => '16:05',
            'incident_location' => 'Ruang konsultasi kegiatan kemahasiswaan',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Terlapor Simulasi D',
            'respondent_campus_status' => 'CAMP-03',
            'respondent_relation' => 'REL-03',
            'respondent_details' => 'Pihak terlapor memiliki posisi koordinasi dalam kegiatan yang diikuti pelapor.',
            'witness_info' => 'Pelapor menyebut riwayat komunikasi dan jadwal pertemuan dapat diverifikasi.',
            'status' => ReportStatus::Forwarded,
            'priority' => 'PRIO-02',
            'submitted_days_ago' => 13,
            'reviewed_days_ago' => 12,
            'forwarded_days_ago' => 11,
            'case_number' => 'CASE-20260615-0002',
            'case_status' => CaseStatusEnum::Investigation,
            'risk_level_code' => 'RISK-02',
            'investigation_status' => InvestigationStatusEnum::VictimInterview,
        ],
        'recommendation_ready' => [
            'registration_number' => 'SLP-20260615-0005',
            'report_type' => 'open',
            'category_code' => 'RCAT-02',
            'chronology' => 'Pelapor melaporkan gestur dan perilaku berulang yang membuat pelapor tidak nyaman selama kegiatan laboratorium. Satgas telah melakukan penelaahan awal dan membutuhkan keputusan lanjutan dari pengelola.',
            'incident_date' => '2026-05-20',
            'incident_time' => '10:20',
            'incident_location' => 'Laboratorium kampus',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Terlapor Simulasi E',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'respondent_details' => 'Mahasiswa yang berada dalam kelompok praktikum yang sama.',
            'witness_info' => 'Asisten praktikum dan satu anggota kelompok disebut dapat dimintai keterangan.',
            'status' => ReportStatus::Forwarded,
            'priority' => 'PRIO-03',
            'submitted_days_ago' => 21,
            'reviewed_days_ago' => 20,
            'forwarded_days_ago' => 19,
            'case_number' => 'CASE-20260615-0003',
            'case_status' => CaseStatusEnum::Recommendation,
            'risk_level_code' => 'RISK-02',
            'investigation_status' => InvestigationStatusEnum::Completed,
            'recommendation_status' => RecommendationStatusEnum::SubmittedToLeader,
        ],
    ];

    public function run(): void
    {
        if (! $this->isAllowed()) {
            throw new RuntimeException('DeploymentDemoReportSeeder requires SILAPPKASAL_ALLOW_DEMO_REPORT_SEED=true.');
        }

        DB::transaction(function (): void {
            $superAdmin = $this->user('super_admin', 'Demo Super Admin', 'demo.superadmin@silappkasal.test');
            $admin = $this->user('admin', 'Demo Admin', 'demo.admin@silappkasal.test');
            $satgas = $this->user('satgas_ppks', 'Demo Satgas', 'demo.satgas@silappkasal.test');
            $reporter = $this->user('reporter', 'Demo Reporter', 'demo.reporter@silappkasal.test', [
                'phone_number' => '081200000001',
            ]);

            foreach ($this->scenarios as $scenario) {
                $report = $this->report($scenario, $reporter);

                if (! isset($scenario['case_number'], $scenario['case_status'])) {
                    continue;
                }

                $case = $this->caseRecord($report, $scenario);
                $this->assignment($case, $satgas, $admin);

                if (isset($scenario['investigation_status'])) {
                    $investigation = $this->investigation($case, $satgas, $scenario);
                    $this->activity($investigation, $satgas, $scenario);

                    if (isset($scenario['recommendation_status'])) {
                        $this->recommendation($case, $investigation, $satgas, $scenario);
                    }
                }
            }

            $superAdmin->refresh();
        });
    }

    private function isAllowed(): bool
    {
        return filter_var(env('SILAPPKASAL_ALLOW_DEMO_REPORT_SEED', false), FILTER_VALIDATE_BOOL);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function user(string $roleCode, string $name, string $email, array $extra = []): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->updateOrCreate(
            ['email' => $email],
            array_merge([
                'role_id' => $role->id,
                'name' => $name,
                'password' => Hash::make(self::PASSWORD),
                'is_active' => true,
            ], $extra)
        );
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function report(array $scenario, User $reporter): Report
    {
        return Report::query()->updateOrCreate(
            ['registration_number' => $scenario['registration_number']],
            [
                'reporter_id' => $reporter->id,
                'tracking_code' => null,
                'report_type' => $scenario['report_type'],
                'category_code' => $scenario['category_code'],
                'chronology' => $scenario['chronology'],
                'incident_date' => $scenario['incident_date'],
                'incident_time' => $scenario['incident_time'],
                'incident_location' => $scenario['incident_location'],
                'location_type' => $scenario['location_type'],
                'respondent_name' => $scenario['respondent_name'],
                'respondent_campus_status' => $scenario['respondent_campus_status'],
                'respondent_relation' => $scenario['respondent_relation'],
                'respondent_details' => $scenario['respondent_details'],
                'witness_info' => $scenario['witness_info'],
                'reporter_phone_encrypted' => $reporter->phone_number,
                'status' => $scenario['status']->value,
                'priority' => $scenario['priority'],
                'admin_notes' => 'Data simulasi deployment untuk rekap progress aplikasi. Semua identitas dan kronologi bersifat fiktif.',
                'rejection_reason' => null,
                'submitted_at' => now()->subDays($scenario['submitted_days_ago']),
                'reviewed_at' => isset($scenario['reviewed_days_ago']) ? now()->subDays($scenario['reviewed_days_ago']) : null,
                'forwarded_at' => isset($scenario['forwarded_days_ago']) ? now()->subDays($scenario['forwarded_days_ago']) : null,
            ]
        );
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function caseRecord(Report $report, array $scenario): CaseRecord
    {
        $status = CaseStatusModel::query()->where('name', $scenario['case_status']->value)->firstOrFail();

        return CaseRecord::query()->updateOrCreate(
            ['case_number' => $scenario['case_number']],
            [
                'report_id' => $report->id,
                'registration_number' => $report->registration_number,
                'status_code' => $status->code,
                'risk_level_code' => $scenario['risk_level_code'],
                'priority_code' => $report->priority,
                'current_stage' => $status->workflow_stage,
                'forwarded_at' => $report->forwarded_at,
                'assessment_at' => now()->subDays(max(1, $scenario['forwarded_days_ago'] - 1)),
                'investigation_started_at' => $scenario['case_status'] === CaseStatusEnum::Assessment ? null : now()->subDays(max(1, $scenario['forwarded_days_ago'] - 3)),
                'recommendation_at' => $scenario['case_status'] === CaseStatusEnum::Recommendation ? now()->subDays(3) : null,
                'decision_at' => null,
                'closed_at' => null,
                'escalated_at' => null,
                'escalation_type' => null,
            ]
        );
    }

    private function assignment(CaseRecord $case, User $satgas, User $admin): CaseAssignment
    {
        return CaseAssignment::query()->updateOrCreate(
            [
                'case_id' => $case->id,
                'satgas_id' => $satgas->id,
            ],
            [
                'assigned_by' => $admin->id,
                'is_lead' => true,
                'is_active' => true,
                'assigned_at' => $case->forwarded_at,
                'unassigned_at' => null,
            ]
        );
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function investigation(CaseRecord $case, User $satgas, array $scenario): Investigation
    {
        $status = InvestigationStatus::query()->where('name', $scenario['investigation_status']->value)->firstOrFail();
        $completedAt = $scenario['investigation_status'] === InvestigationStatusEnum::Completed ? now()->subDays(4) : null;

        return Investigation::query()->updateOrCreate(
            ['case_id' => $case->id],
            [
                'lead_investigator_id' => $satgas->id,
                'status_code' => $status->code,
                'plan_summary' => 'Rencana simulasi: verifikasi kronologi, telaah bukti awal, dan wawancara pihak terkait dengan pendekatan trauma-informed.',
                'findings' => $completedAt ? 'Temuan simulasi: terdapat konsistensi keterangan awal dan kebutuhan tindak lanjut kelembagaan.' : null,
                'conclusion' => $completedAt ? 'Kesimpulan simulasi: kasus layak masuk tahap rekomendasi untuk keputusan lanjutan.' : null,
                'started_at' => $case->investigation_started_at ?? now()->subDays(6),
                'completed_at' => $completedAt,
            ]
        );
    }

    private function activity(Investigation $investigation, User $satgas, array $scenario): InvestigationActivity
    {
        return InvestigationActivity::query()->updateOrCreate(
            [
                'investigation_id' => $investigation->id,
                'activity_type' => InvestigationActivityType::CaseReview->value,
            ],
            [
                'investigator_id' => $satgas->id,
                'activity_date' => now()->subDays(5)->toDateString(),
                'description' => 'Satgas melakukan review awal terhadap kronologi, status risiko, dan kebutuhan pendampingan.',
                'findings' => 'Data simulasi menunjukkan kasus perlu diproses sesuai alur workflow aplikasi.',
                'notes' => 'Catatan ini dibuat oleh seed deployment demo dan tidak merujuk pada kejadian nyata.',
            ]
        );
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function recommendation(CaseRecord $case, Investigation $investigation, User $satgas, array $scenario): Recommendation
    {
        $status = RecommendationStatus::query()->where('name', $scenario['recommendation_status']->value)->firstOrFail();

        return Recommendation::query()->updateOrCreate(
            ['case_id' => $case->id],
            [
                'investigation_id' => $investigation->id,
                'author_id' => $satgas->id,
                'status_code' => $status->code,
                'conclusion' => 'Rekomendasi simulasi disusun berdasarkan temuan internal dan kebutuhan perlindungan pelapor.',
                'recommended_actions' => 'Melanjutkan review pimpinan, memastikan pendampingan pelapor, dan membatasi interaksi yang berisiko selama proses berjalan.',
                'sanction_recommendation' => 'Placeholder rekomendasi sanksi kelembagaan sesuai hasil pemeriksaan lanjutan.',
                'recovery_recommendation' => 'Placeholder pendampingan psikologis dan akademik sesuai kebutuhan pelapor.',
                'prevention_recommendation' => 'Placeholder edukasi ulang kode etik dan kanal pelaporan aman di unit terkait.',
                'submitted_at' => now()->subDays(3),
            ]
        );
    }
}
