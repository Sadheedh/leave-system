<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

final class WorkingDayCalculator
{
    /**
     * Count the working days between two dates, both ends inclusive.
     * The Maldivian weekend is Friday and Saturday, so those days are skipped.
     * Returns 0 when the end date is before the start date.
     */
    public function count(CarbonInterface $start, CarbonInterface $end): int
    {
        $startDay = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        if ($endDay->lt($startDay)) {
            return 0;
        }

        $days = 0;

        foreach (CarbonPeriod::create($startDay, $endDay) as $day) {
            if (! $day->isFriday() && ! $day->isSaturday()) {
                $days++;
            }
        }

        return $days;
    }
}
