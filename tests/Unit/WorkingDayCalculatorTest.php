<?php

use App\Services\WorkingDayCalculator;
use Carbon\Carbon;

it('counts working days excluding Friday and Saturday', function (string $start, string $end, int $expected) {
    $days = (new WorkingDayCalculator)->count(Carbon::parse($start), Carbon::parse($end));

    expect($days)->toBe($expected);
})->with([
    'Sunday to Thursday' => ['2026-11-15', '2026-11-19', 5],
    'Thursday to Sunday' => ['2026-11-19', '2026-11-22', 2],
    'Friday and Saturday only' => ['2026-11-20', '2026-11-21', 0],
    'single working day' => ['2026-11-15', '2026-11-15', 1],
    'one full week' => ['2026-11-15', '2026-11-21', 5],
    'two full weeks' => ['2026-11-15', '2026-11-28', 10],
    'end before start' => ['2026-11-19', '2026-11-15', 0],
]);
