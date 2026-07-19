<?php

namespace App\Enums;

enum InvestigationActivityType: string
{
    case CaseReview = 'case_review';
    case DocumentReview = 'document_review';
    case TimelineReview = 'timeline_review';
    case VictimInterview = 'victim_interview';
    case WitnessInterview = 'witness_interview';
    case RespondentInterview = 'respondent_interview';
    case EvidenceAnalysis = 'evidence_analysis';
    case ReportDrafting = 'report_drafting';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases()
        );
    }

    /**
     * @return list<string>
     */
    public function permittedStages(): array
    {
        return match ($this) {
            self::CaseReview => [InvestigationStatus::Planning->value],
            self::DocumentReview, self::TimelineReview => [
                InvestigationStatus::Planning->value,
                InvestigationStatus::EvidenceCollection->value,
                InvestigationStatus::EvidenceAnalysis->value,
            ],
            self::VictimInterview => [InvestigationStatus::VictimInterview->value],
            self::WitnessInterview => [InvestigationStatus::WitnessInterview->value],
            self::RespondentInterview => [InvestigationStatus::RespondentInterview->value],
            self::EvidenceAnalysis => [InvestigationStatus::EvidenceAnalysis->value],
            self::ReportDrafting => [InvestigationStatus::ReportDrafting->value],
        };
    }
}
