<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;

beforeEach(function () {
    config(['app.display_timezone' => 'Europe/Sofia']);
});

it('formats a UTC timestamp in the display timezone', function () {
    // 21:30 UTC on Tuesday is already Wednesday in Sofia
    $html = Blade::render('<x-local-date :value="$at" format="ddd D MMM, HH:mm" />', [
        'at' => CarbonImmutable::parse('2026-10-20 21:30', 'UTC'),
    ]);

    expect(trim(strip_tags($html)))->toBe('Wed 21 Oct, 00:30')
        ->and($html)->toContain('datetime="2026-10-20T21:30:00+00:00"');
});

it('formats as day and short month by default', function () {
    $html = Blade::render('<x-local-date :value="$at" />', [
        'at' => CarbonImmutable::parse('2026-10-08 09:00', 'UTC'),
    ]);

    expect(trim(strip_tags($html)))->toBe('8 Oct');
});
