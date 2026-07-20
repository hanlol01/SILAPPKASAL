<?php

namespace App\Enums;

enum CaseFinalOutcome: string
{
    case Resolved = 'resolved';
    case PartiallyResolved = 'partially_resolved';
    case Discontinued = 'discontinued';
    case InsufficientInformation = 'insufficient_information';
    case ReferredExternal = 'referred_external';
    case Withdrawn = 'withdrawn';
    case ClosedOther = 'closed_other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return list<self> */
    public static function compatibleWithRecovery(RecoveryStatus $status): array
    {
        return match ($status) {
            RecoveryStatus::Completed => [
                self::Resolved,
                self::PartiallyResolved,
                self::ReferredExternal,
                self::ClosedOther,
            ],
            RecoveryStatus::Discontinued => [
                self::Discontinued,
                self::InsufficientInformation,
                self::ReferredExternal,
                self::Withdrawn,
                self::PartiallyResolved,
                self::ClosedOther,
            ],
            default => [],
        };
    }

    public function isCompatibleWithRecovery(RecoveryStatus $status): bool
    {
        return in_array($this, self::compatibleWithRecovery($status), true);
    }

    public function label(string $locale): string
    {
        return __("case_final_outcomes.{$this->value}", [], $locale);
    }
}
