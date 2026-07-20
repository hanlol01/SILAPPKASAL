<?php

namespace App\Support;

use App\Models\Report;

final class ReportInputProjection
{
    /**
     * Build the submitted three-stage report projection without internal IDs.
     *
     * Reporter account values intentionally reflect the current account. The
     * reports table does not store an account snapshot.
     *
     * @return array<string, mixed>
     */
    public static function make(Report $report, bool $includeReporterIdentity): array
    {
        $reporter = $includeReporterIdentity ? $report->reporter : null;

        return [
            'identification' => [
                'report_type' => $report->report_type,
                'category' => self::reference($report->category),
            ],
            'incident' => [
                'chronology' => $report->chronology,
                'incident_date' => $report->incident_date?->toDateString(),
                'incident_time' => $report->incident_time,
                'incident_location' => $report->incident_location,
                'location_type' => self::reference($report->locationType),
            ],
            'respondent' => [
                'name' => $report->respondent_name,
                'campus_status' => self::reference($report->campusStatus),
                'relation' => self::reference($report->relation),
                'details' => $report->respondent_details,
                'witness_information' => $report->witness_info,
                'confidential_reporter_contact' => $report->report_type === 'confidential'
                    ? $report->reporter_phone_encrypted
                    : null,
            ],
            'reporter_account' => $reporter === null ? [
                'source' => 'current_account',
                'masked' => true,
            ] : [
                'source' => 'current_account',
                'masked' => false,
                'name' => $reporter->name,
                'nim' => $reporter->nim,
                'email' => $reporter->email,
                'phone_number' => $reporter->phone_number,
                'faculty' => self::reference($reporter->faculty),
                'study_program' => self::reference($reporter->studyProgram),
            ],
        ];
    }

    /**
     * @return array{code: string, name: string}|null
     */
    private static function reference(?object $model): ?array
    {
        if ($model === null) {
            return null;
        }

        return [
            'code' => (string) $model->code,
            'name' => (string) $model->name,
        ];
    }
}
