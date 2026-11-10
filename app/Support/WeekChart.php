<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The seven bars of "This week" on Today: Monday to Sunday of today's ISO week.
 */
final class WeekChart
{
    /**
     * @param  array<string, int>  $minutesByDate  local "Y-m-d" => minutes
     * @return list<array{date: CarbonImmutable, minutes: int, percent: int, is_today: bool, is_future: bool}>
     */
    public static function build(array $minutesByDate, CarbonImmutable $today): array
    {
        $monday = $today->startOfDay()->startOfWeek(CarbonInterface::MONDAY);

        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $monday->addDays($i);
            $days[] = ['date' => $date, 'minutes' => (int) ($minutesByDate[$date->toDateString()] ?? 0)];
        }

        // Bars are scaled to the week's busiest day; max(1, …) keeps an empty week at 0%
        $highest = max(1, ...array_column($days, 'minutes'));

        return array_map(fn (array $day) => [
            'date' => $day['date'],
            'minutes' => $day['minutes'],
            'percent' => (int) round($day['minutes'] / $highest * 100),
            'is_today' => $day['date']->isSameDay($today),
            'is_future' => $day['date']->greaterThan($today->startOfDay()),
        ], $days);
    }
}
