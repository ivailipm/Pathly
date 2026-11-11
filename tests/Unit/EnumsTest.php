<?php

use App\Enums\SubjectStatus;
use App\Enums\TaskType;

it('labels subject statuses and task types', function () {
    expect(array_map(fn (SubjectStatus $status) => $status->label(), SubjectStatus::cases()))->toBe(['Active', 'Paused', 'Archived'])
        ->and(array_map(fn (TaskType $type) => $type->label(), TaskType::cases()))->toBe(['Docs', 'Video', 'Exercise', 'Notebook']);
});
