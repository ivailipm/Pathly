<?php

use Illuminate\Support\Facades\Blade;

function renderSegments(int $done, int $inProgress, int $total): string
{
    return Blade::render(
        '<x-progress-segments :done="$done" :in-progress="$inProgress" :total="$total" />',
        ['done' => $done, 'inProgress' => $inProgress, 'total' => $total],
    );
}

it('draws one segment per task, coloured by status', function () {
    $html = renderSegments(2, 1, 4);

    expect(substr_count($html, 'bg-ink'))->toBe(2)
        ->and(substr_count($html, 'bg-vermilion'))->toBe(1)
        ->and(substr_count($html, 'bg-control'))->toBe(1);
});

it('describes the progress in its label', function () {
    expect(renderSegments(2, 1, 4))
        ->toContain('role="img"')
        ->toContain('aria-label="2 of 4 tasks done, 1 in progress"')
        ->and(renderSegments(1, 0, 1))->toContain('aria-label="1 of 1 task done"');
});

it('switches to three parts sized by count past 20 tasks', function () {
    $html = renderSegments(10, 5, 30);

    expect(substr_count($html, '<span'))->toBe(3)
        ->and($html)->toContain('flex: 10 1 0%')
        ->toContain('flex: 5 1 0%')
        ->toContain('flex: 15 1 0%');
});

it('renders nothing without tasks', function () {
    expect(trim(renderSegments(0, 0, 0)))->toBe('');
});
