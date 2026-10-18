<?php

use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Models\Subject;
use App\Models\Task;

it('returns milestones sorted by position', function () {
    $subject = Subject::factory()->create();

    Milestone::factory()->for($subject)->create(['title' => 'Second', 'position' => 2]);
    Milestone::factory()->for($subject)->create(['title' => 'First', 'position' => 1]);

    expect($subject->milestones->pluck('title')->all())->toBe(['First', 'Second']);
});

it('gives a new task the todo status by default', function () {
    $milestone = Milestone::factory()->create();

    $task = $milestone->tasks()->create([
        'title' => 'Read the docs',
        'position' => 1,
    ]);

    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

it('deletes milestones and tasks when their subject is deleted', function () {
    $task = Task::factory()->create();

    $task->milestone->subject->delete();

    expect(Milestone::count())->toBe(0)
        ->and(Task::count())->toBe(0);
});
