<?php

use App\Enums\TaskStatus;

it('labels the statuses in sentence case', function () {
    expect(TaskStatus::Todo->label())->toBe('To do')
        ->and(TaskStatus::InProgress->label())->toBe('In progress')
        ->and(TaskStatus::Done->label())->toBe('Done');
});
