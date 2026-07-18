<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class BusinessDayClock
{
    public function elapsedSeconds(DateTimeInterface|string $startedAt, DateTimeInterface|string $endedAt): int
    {
        $start = $this->date($startedAt);
        $end = $this->date($endedAt);

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        $seconds = 0;
        $day = $start->startOfDay();

        while ($day->lessThanOrEqualTo($end->startOfDay())) {
            if ($day->isWeekday()) {
                $segmentStart = $start->greaterThan($day) ? $start : $day;
                $nextDay = $day->addDay();
                $segmentEnd = $end->lessThan($nextDay) ? $end : $nextDay;

                if ($segmentEnd->greaterThan($segmentStart)) {
                    $seconds += $segmentEnd->getTimestamp() - $segmentStart->getTimestamp();
                }
            }

            $day = $day->addDay();
        }

        return $seconds;
    }

    public function dueAt(DateTimeInterface|string $startedAt, int $businessSeconds): CarbonImmutable
    {
        $cursor = $this->date($startedAt);
        $remaining = max(0, $businessSeconds);

        while ($remaining > 0) {
            if (! $cursor->isWeekday()) {
                $cursor = $cursor->nextWeekday()->startOfDay();
                continue;
            }

            $nextDay = $cursor->startOfDay()->addDay();
            $available = $nextDay->getTimestamp() - $cursor->getTimestamp();

            if ($remaining <= $available) {
                return $cursor->addSeconds($remaining);
            }

            $remaining -= $available;
            $cursor = $nextDay;
        }

        return $cursor;
    }

    private function date(DateTimeInterface|string $value): CarbonImmutable
    {
        $timezone = (string) config('audit.oversight.timezone', 'Asia/Jakarta');

        return CarbonImmutable::parse($value)->setTimezone($timezone);
    }
}
