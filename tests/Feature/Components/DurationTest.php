<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

function renderDuration(int $minutes): string
{
    return Str::squish(strip_tags(Blade::render('<x-duration :minutes="$m" />', ['m' => $minutes])));
}

it('formats minutes under an hour', function () {
    expect(renderDuration(0))->toBe('0 min')
        ->and(renderDuration(1))->toBe('1 min')
        ->and(renderDuration(59))->toBe('59 min');
});

it('formats hours with zero-padded minutes', function () {
    expect(renderDuration(60))->toBe('1 h 00 min')
        ->and(renderDuration(180))->toBe('3 h 00 min')
        ->and(renderDuration(250))->toBe('4 h 10 min');
});

it('mutes the minutes after the hours', function () {
    expect(Blade::render('<x-duration :minutes="250" />'))
        ->toContain('<span class="text-muted">10 min</span>');
});
