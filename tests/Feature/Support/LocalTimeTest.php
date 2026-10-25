<?php

use App\Support\LocalTime;
use Carbon\CarbonImmutable;

// Sofia is UTC+3 until the clocks go back on 25 October 2026, then UTC+2
beforeEach(function () {
    config(['app.display_timezone' => 'Europe/Sofia']);
});

it('gives today in the display timezone', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-23 22:30', 'UTC'));

    expect(LocalTime::today()->toDateString())->toBe('2026-10-24')
        ->and(LocalTime::today()->toTimeString())->toBe('00:00:00')
        ->and(LocalTime::now()->timezoneName)->toBe('Europe/Sofia');
});

it('converts a UTC timestamp to its local date', function () {
    $lateEvening = CarbonImmutable::parse('2026-10-20 21:15', 'UTC');

    expect(LocalTime::of($lateEvening)->format('Y-m-d H:i'))->toBe('2026-10-21 00:15')
        ->and(LocalTime::dateOf($lateEvening))->toBe('2026-10-21')
        ->and(LocalTime::dateOf(CarbonImmutable::parse('2026-10-20 20:59', 'UTC')))->toBe('2026-10-20');
});

it('gives the UTC range of a local day', function () {
    [$from, $to] = LocalTime::utcRangeOfDay('2026-10-23');

    expect($from->toDateTimeString())->toBe('2026-10-22 21:00:00')
        ->and($to->toDateTimeString())->toBe('2026-10-23 20:59:59')
        ->and($from->timezoneName)->toBe('UTC');

    // A timestamp stands for the local day it falls on: 22:30 UTC on the 23rd is the 24th in Sofia
    [$from, $to] = LocalTime::utcRangeOfDay(CarbonImmutable::parse('2026-10-23 22:30', 'UTC'));

    expect($from->toDateTimeString())->toBe('2026-10-23 21:00:00')
        ->and($to->toDateTimeString())->toBe('2026-10-24 20:59:59');

    // The day the clocks go back is 25 hours long
    [$from, $to] = LocalTime::utcRangeOfDay('2026-10-25');

    expect($from->toDateTimeString())->toBe('2026-10-24 21:00:00')
        ->and($to->toDateTimeString())->toBe('2026-10-25 21:59:59');
});

it('stores manual logs at local noon', function () {
    expect(LocalTime::noonUtc('2026-10-23')->toDateTimeString())->toBe('2026-10-23 09:00:00')
        ->and(LocalTime::noonUtc('2026-11-02')->toDateTimeString())->toBe('2026-11-02 10:00:00')
        ->and(LocalTime::noonUtc('2026-10-23')->timezoneName)->toBe('UTC');
});
