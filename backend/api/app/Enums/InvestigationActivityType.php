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
}
