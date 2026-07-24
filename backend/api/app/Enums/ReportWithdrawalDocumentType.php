<?php

namespace App\Enums;

enum ReportWithdrawalDocumentType: string
{
    case SignedWithdrawalStatement = 'signed_withdrawal_statement';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
