<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Timestamps are stored in UTC; "today", day grouping and displayed times use the display timezone (R1).
 */
final class LocalTime
{
    public static function zone(): string
    {
        return config()->string('app.display_timezone');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zone());
    }

    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }

    /**
     * A stored UTC timestamp in the display timezone.
     */
    public static function of(CarbonInterface $time): CarbonImmutable
    {
        return CarbonImmutable::instance($time)->setTimezone(self::zone());
    }

    /**
     * The local date ("2026-10-23") a UTC timestamp falls on: the grouping key for streaks, the week and notes.
     */
    public static function dateOf(CarbonInterface $time): string
    {
        return self::of($time)->toDateString();
    }

    /**
     * The first and last UTC moment of a local day, for whereBetween() on UTC columns.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function utcRangeOfDay(CarbonInterface|string $localDate): array
    {
        $day = $localDate instanceof CarbonInterface
            ? self::of($localDate)
            : CarbonImmutable::parse($localDate, self::zone());

        return [$day->startOfDay()->utc(), $day->endOfDay()->utc()];
    }

    /**
     * Noon of a local date in UTC: where manually logged sessions are stored, so they land on that day.
     */
    public static function noonUtc(string $localDate): CarbonImmutable
    {
        return CarbonImmutable::parse($localDate.' 12:00', self::zone())->utc();
    }
}
