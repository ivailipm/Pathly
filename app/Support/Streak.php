<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Consecutive local days with at least one completed session.
 */
final class Streak
{
    /**
     * Counted back from today when today has a session, otherwise from yesterday (so the streak isn't lost
     * before the day is over). 0 when neither today nor yesterday has one.
     *
     * @param  array<int, string>  $studyDates  local "Y-m-d" dates with at least one completed session
     * @return array{length: int, includesToday: bool}
     */
    public static function calculate(array $studyDates, CarbonImmutable $today): array
    {
        $days = array_flip($studyDates);
        $includesToday = isset($days[$today->toDateString()]);

        $length = 0;
        $cursor = $includesToday ? $today : $today->subDay();

        while (isset($days[$cursor->toDateString()])) {
            $length++;
            $cursor = $cursor->subDay();
        }

        return ['length' => $length, 'includesToday' => $includesToday];
    }
}
