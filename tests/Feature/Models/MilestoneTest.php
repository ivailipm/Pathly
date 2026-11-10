<?php

use App\Models\Milestone;
use App\Models\Subject;
use App\Models\Task;

it('numbers milestones by position, then id', function () {
    $subject = Subject::factory()->create();
    $third = Milestone::factory()->for($subject)->create(['position' => 2]);
    $first = Milestone::factory()->for($subject)->create(['position' => 1]);
    $fourth = Milestone::factory()->for($subject)->create(['position' => 2]);
    $second = Milestone::factory()->for($subject)->create(['position' => 1]);

    // Another subject's milestones don't count
    Milestone::factory()->create(['position' => 1]);

    $numbers = Milestone::query()->whereBelongsTo($subject)->withNumber()->get()->pluck('number', 'id');

    expect($numbers[$first->id])->toBe(1)
        ->and($numbers[$second->id])->toBe(2)
        ->and($numbers[$third->id])->toBe(3)
        ->and($numbers[$fourth->id])->toBe(4);
});

it('numbers over all siblings even inside a filtered query', function () {
    $subject = Subject::factory()->create();

    foreach (range(1, 6) as $position) {
        Milestone::factory()->for($subject)->create(['position' => $position, 'title' => "Step {$position}"]);
    }

    $fourth = Milestone::query()->where('title', 'Step 4')->withNumber()->sole();

    expect($fourth->number)->toBe(4);
});

it('is done once it has a completion date', function () {
    expect(Milestone::factory()->make()->is_done)->toBeFalse()
        ->and(Milestone::factory()->completed()->make()->is_done)->toBeTrue();
});

it('counts tasks by status', function () {
    $milestone = Milestone::factory()->create();
    Task::factory()->for($milestone)->create();
    Task::factory()->for($milestone)->inProgress()->create();
    Task::factory()->count(2)->for($milestone)->done()->create();

    $counted = Milestone::query()->withTaskCounts()->sole();

    expect($counted->tasks_count)->toBe(4)
        ->and($counted->done_tasks_count)->toBe(2)
        ->and($counted->in_progress_tasks_count)->toBe(1);
});
