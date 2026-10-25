<?php

use App\Models\Subject;
use Illuminate\Support\Facades\Blade;

// R8: clay, slate, moss, plum by id. Step C switches the tint to the subject's colour column.
it('shows the first letter on the tint of the subject', function (int $id, string $tint) {
    $html = Blade::render('<x-monogram :subject="$subject" />', [
        'subject' => Subject::factory()->make(['id' => $id, 'title' => 'machine learning']),
    ]);

    expect($html)
        ->toContain("bg-{$tint} text-{$tint}-ink")
        ->toContain('aria-hidden="true"')
        ->and(trim(strip_tags($html)))->toBe('M');
})->with([
    [1, 'clay'],
    [2, 'slate'],
    [3, 'moss'],
    [4, 'plum'],
    [5, 'clay'],
]);
