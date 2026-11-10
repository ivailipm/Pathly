<?php

use App\Enums\SubjectColour;
use App\Models\Subject;
use Illuminate\Support\Facades\Blade;

it('shows the first letter on the tint of the subject', function (SubjectColour $colour) {
    $html = Blade::render('<x-monogram :subject="$subject" />', [
        'subject' => Subject::factory()->make(['title' => 'machine learning', 'colour' => $colour]),
    ]);

    expect($html)
        ->toContain("bg-{$colour->value} text-{$colour->value}-ink")
        ->toContain('aria-hidden="true"')
        ->and(trim(strip_tags($html)))->toBe('M');
})->with(SubjectColour::cases());
