<?php

use App\Enums\SubjectColour;
use App\Enums\SubjectStatus;
use App\Enums\TaskType;

it('labels subject statuses, colours and task types', function () {
    expect(array_map(fn (SubjectStatus $status) => $status->label(), SubjectStatus::cases()))->toBe(['Active', 'Paused', 'Archived'])
        ->and(array_map(fn (SubjectColour $colour) => $colour->label(), SubjectColour::cases()))->toBe(['Clay', 'Slate', 'Moss', 'Plum'])
        ->and(array_map(fn (TaskType $type) => $type->label(), TaskType::cases()))->toBe(['Docs', 'Video', 'Exercise', 'Notebook']);
});

it('rotates colours by index', function (int $index, SubjectColour $colour) {
    expect(SubjectColour::forIndex($index))->toBe($colour);
})->with([
    [0, SubjectColour::Clay],
    [1, SubjectColour::Slate],
    [2, SubjectColour::Moss],
    [3, SubjectColour::Plum],
    [4, SubjectColour::Clay],
    [9, SubjectColour::Slate],
]);
