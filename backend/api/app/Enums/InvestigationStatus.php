<?php

namespace App\Enums;

enum InvestigationStatus: string
{
    case Planning = 'planning';
    case EvidenceCollection = 'evidence_collection';
    case VictimInterview = 'victim_interview';
    case WitnessInterview = 'witness_interview';
    case RespondentInterview = 'respondent_interview';
    case EvidenceAnalysis = 'evidence_analysis';
    case ReportDrafting = 'report_drafting';
    case Completed = 'completed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases()
        );
    }
}
