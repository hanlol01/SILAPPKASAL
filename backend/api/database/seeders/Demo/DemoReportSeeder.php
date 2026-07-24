<?php

namespace Database\Seeders\Demo;

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Database\Seeder;

class DemoReportSeeder extends Seeder
{
    public const CASE_REPORTS = [
        ['SLP-DEMO-202606-0101', 'CASE-DEMO-202606-0101', 'assessment', 'STAI-SA', 18],
        ['SLP-DEMO-202606-0102', 'CASE-DEMO-202606-0102', 'investigation', 'STAI-AMG', 17],
        ['SLP-DEMO-202606-0103', 'CASE-DEMO-202606-0103', 'recommendation', 'UIKHIR', 16],
        ['SLP-DEMO-202606-0104', 'CASE-DEMO-202606-0104', 'recommendation', 'INU-TSM', 15],
        ['SLP-DEMO-202606-0105', 'CASE-DEMO-202606-0105', 'decision', 'UID-CMS', 14],
        ['SLP-DEMO-202606-0106', 'CASE-DEMO-202606-0106', 'decided', 'STITNU-AF', 13],
        ['SLP-DEMO-202606-0107', 'CASE-DEMO-202606-0107', 'recovery', 'IMA-BJR', 12],
        ['SLP-DEMO-202606-0108', 'CASE-DEMO-202606-0108', 'monitoring', 'STAI-SA', 11],
        ['SLP-DEMO-202606-0109', 'CASE-DEMO-202606-0109', 'closed', 'STAI-AMG', 10],
    ];

    public function run(): void
    {
        $this->report('SLP-DEMO-202606-0001', 'STAI-SA', ReportStatus::Submitted->value, 'confidential', 4, false);
        $this->report('SLP-DEMO-202606-0002', 'STAI-AMG', ReportStatus::UnderReview->value, 'open', 5, false);
        $this->report('SLP-DEMO-202606-0003', 'UIKHIR', ReportStatus::NeedInfo->value, 'confidential', 6, false);
        $this->report('SLP-DEMO-202606-0004', 'INU-TSM', ReportStatus::Rejected->value, 'open', 7, false);
        $this->report('SLP-DEMO-202606-0005', 'UID-CMS', ReportStatus::Submitted->value, 'anonymous', 3, true);

        foreach (self::CASE_REPORTS as [$registrationNumber, $caseNumber, $caseStatus, $universityCode, $daysAgo]) {
            $this->report($registrationNumber, $universityCode, ReportStatus::Forwarded->value, 'confidential', $daysAgo, false);
        }
    }

    private function report(
        string $registrationNumber,
        string $universityCode,
        string $status,
        string $reportType,
        int $daysAgo,
        bool $anonymous
    ): Report {
        $reporter = DemoSeed::user(DemoSeed::campusEmail('reporter', $universityCode));
        $categoryOffset = crc32($registrationNumber) % 5;

        return Report::query()->updateOrCreate(
            ['registration_number' => $registrationNumber],
            [
                'reporter_id' => $reporter->id,
                'tracking_code' => $anonymous ? 'TRK-DEMO-'.substr(str_replace('-', '', $registrationNumber), -8) : null,
                'report_type' => $reportType,
                'category_code' => DemoSeed::masterCode('report_categories', $categoryOffset),
                'chronology' => 'Pengaduan demo ini menggambarkan situasi fiktif di lingkungan kampus untuk kebutuhan pelatihan, QA, dan UAT. Narasi dibuat realistis namun tidak merujuk pada identitas, tempat, atau kejadian nyata.',
                'incident_date' => DemoSeed::date($daysAgo + 1)->toDateString(),
                'incident_time' => '14:30',
                'incident_location' => 'Area kampus pada skenario demo internal',
                'location_type' => DemoSeed::masterCode('location_types', $categoryOffset % 3),
                'respondent_name' => $anonymous ? null : 'Terlapor Demo',
                'respondent_campus_status' => DemoSeed::masterCode('campus_statuses', $categoryOffset % 4),
                'respondent_relation' => DemoSeed::masterCode('relations', $categoryOffset % 5),
                'respondent_details' => 'Keterangan terlapor fiktif untuk simulasi alur verifikasi dan penanganan.',
                'witness_info' => 'Saksi demo tersedia sebagai konteks simulasi tanpa data pribadi nyata.',
                'reporter_phone_encrypted' => $reportType === 'confidential' ? $reporter->phone_number : null,
                'status' => $status,
                'priority' => DemoSeed::masterCode('priority_levels', $categoryOffset % 3),
                'admin_notes' => $status === ReportStatus::Rejected->value ? 'Pengaduan demo ditolak karena data tidak lengkap.' : null,
                'rejection_reason' => $status === ReportStatus::Rejected->value ? 'Informasi awal pada pengaduan demo belum memenuhi syarat tindak lanjut.' : null,
                'submitted_at' => DemoSeed::date($daysAgo),
                'reviewed_at' => in_array($status, [ReportStatus::UnderReview->value, ReportStatus::NeedInfo->value, ReportStatus::Rejected->value, ReportStatus::Forwarded->value], true)
                    ? DemoSeed::date(max(1, $daysAgo - 1))
                    : null,
                'forwarded_at' => $status === ReportStatus::Forwarded->value ? DemoSeed::date(max(1, $daysAgo - 2)) : null,
            ]
        );
    }
}
