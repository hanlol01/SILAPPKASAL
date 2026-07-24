<?php

namespace App\Services;

use App\Exceptions\DecisionNumberSequenceExhausted;
use App\Models\Decision;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DecisionNumberGenerator
{
    private const MAX_SEQUENCE = 999;

    public function issue(CarbonInterface $issuanceTimestamp): string
    {
        $year = (int) $issuanceTimestamp
            ->copy()
            ->setTimezone((string) config('app.timezone'))
            ->format('Y');
        $now = now();

        DB::table('decision_number_sequences')->insertOrIgnore([
            'year' => $year,
            'last_value' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sequence = DB::table('decision_number_sequences')
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();
        $nextValue = (int) $sequence->last_value;

        do {
            $nextValue++;

            if ($nextValue > self::MAX_SEQUENCE) {
                throw new DecisionNumberSequenceExhausted($year);
            }

            $decisionNumber = sprintf('SK/PPKS/%04d/%03d', $year, $nextValue);
        } while (Decision::query()->where('decision_number', $decisionNumber)->exists());

        DB::table('decision_number_sequences')
            ->where('year', $year)
            ->update([
                'last_value' => $nextValue,
                'updated_at' => $now,
            ]);

        return $decisionNumber;
    }
}
