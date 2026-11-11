<?php

use App\Models\Subject;
use App\Support\Tint;
use Illuminate\Support\Facades\Blade;

it('shows the first letter on the tint of the subject', function (string $colour) {
    $tint = Tint::of($colour);

    $html = Blade::render('<x-monogram :subject="$subject" />', [
        'subject' => Subject::factory()->make(['title' => 'machine learning', 'colour' => $colour]),
    ]);

    expect($html)
        ->toContain('bg-(--tint-bg) text-(color:--tint-ink)')
        ->toContain("--tint-bg: {$tint->background}; --tint-ink: {$tint->ink}; --tint-shade: {$tint->shade}")
        ->toContain('aria-hidden="true"')
        ->and(trim(strip_tags($html)))->toBe('M');
})->with([...array_values(Tint::PRESETS), '#e63946']);
