<?php

use App\Support\WeekChart;
use Carbon\CarbonImmutable;

// Friday 23 October 2026
function weekOn(array $minutes, string $today = '2026-10-23'): array
{
    return WeekChart::build($minutes, CarbonImmutable::parse($today));
}

it('builds monday to sunday', function () {
    $dates = array_map(fn (array $day) => $day['date']->toDateString(), weekOn([]));

    expect($dates)->toBe(['2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23', '2026-10-24', '2026-10-25']);
});

it('starts on monday when today is sunday or monday', function () {
    expect(weekOn([], '2026-10-25')[0]['date']->toDateString())->toBe('2026-10-19')
        ->and(weekOn([], '2026-10-19')[0]['date']->toDateString())->toBe('2026-10-19');
});

it('scales bars to the highest day', function () {
    $week = weekOn(['2026-10-19' => 45, '2026-10-20' => 80, '2026-10-22' => 30, '2026-10-18' => 500]);

    expect(array_column($week, 'minutes'))->toBe([45, 80, 0, 30, 0, 0, 0])
        ->and(array_column($week, 'percent'))->toBe([56, 100, 0, 38, 0, 0, 0]);
});

it('marks today and future days', function () {
    $week = weekOn([]);

    expect(array_column($week, 'is_today'))->toBe([false, false, false, false, true, false, false])
        ->and(array_column($week, 'is_future'))->toBe([false, false, false, false, false, true, true]);
});

it('gives zero percent when nothing was studied', function () {
    expect(array_column(weekOn([]), 'percent'))->toBe([0, 0, 0, 0, 0, 0, 0]);
});
