<?php

use App\Support\Streak;
use Carbon\CarbonImmutable;

function streakOn(array $dates, string $today = '2026-10-23'): array
{
    return Streak::calculate($dates, CarbonImmutable::parse($today));
}

it('counts consecutive days ending today', function () {
    expect(streakOn(['2026-10-23', '2026-10-22', '2026-10-21']))
        ->toBe(['length' => 3, 'includesToday' => true]);
});

it('counts from yesterday when today has no session', function () {
    expect(streakOn(['2026-10-22', '2026-10-21']))
        ->toBe(['length' => 2, 'includesToday' => false]);
});

it('is zero when neither today nor yesterday has a session', function () {
    expect(streakOn(['2026-10-21', '2026-10-20']))
        ->toBe(['length' => 0, 'includesToday' => false])
        ->and(streakOn([]))->toBe(['length' => 0, 'includesToday' => false]);
});

it('stops at a gap', function () {
    expect(streakOn(['2026-10-23', '2026-10-22', '2026-10-20', '2026-10-19']))
        ->toBe(['length' => 2, 'includesToday' => true]);
});

it('counts a back-dated session', function () {
    // Logged today for yesterday: yesterday joins the streak
    expect(streakOn(['2026-10-23', '2026-10-21'])['length'])->toBe(1)
        ->and(streakOn(['2026-10-23', '2026-10-21', '2026-10-22'])['length'])->toBe(3);
});

it('crosses month boundaries', function () {
    expect(streakOn(['2026-11-01', '2026-10-31', '2026-10-30'], '2026-11-01')['length'])->toBe(3);
});
