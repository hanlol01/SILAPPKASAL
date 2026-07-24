<?php

namespace App\Enums;

enum ReportWithdrawalRequestType: string
{
    case EarlyCancellation = 'early_cancellation';
    case FormalWithdrawal = 'formal_withdrawal';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
