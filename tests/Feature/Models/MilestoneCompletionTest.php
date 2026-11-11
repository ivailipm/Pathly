<?php

use App\Livewire\Milestones\Show;
use App\Livewire\Tasks\Panel;
use App\Models\Milestone;
use App\Models\Task;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The milestone's board, as its owner.
 */
function board(Milestone $milestone): Testable
{
    return Livewire::actingAs($milestone->subject->user)
        ->test(Show::class, ['subject' => $milestone->subject, 'milestone' => $milestone]);
}

it('completes when the last task moves to done', function () {
    $milestone = Milestone::factory()->create();
    Task::factory()->for($milestone)->done()->create(['position' => 1]);
    $last = Task::factory()->for($milestone)->create(['position' => 2]);

    $this->travelTo('2026-10-08 10:00:00');

    board($milestone)->call('moveTask', $last->id, 1, 'done');

    expect($milestone->fresh()?->completed_at?->toDateTimeString())->toBe('2026-10-08 10:00:00');
});

it('reopens when a task leaves done', function () {
    $milestone = Milestone::factory()->completed()->create();
    $task = Task::factory()->for($milestone)->done()->create();

    board($milestone)->call('moveTask', $task->id, 0, 'in_progress');

    expect($milestone->fresh()?->completed_at)->toBeNull();
});

it('reopens when a task is added to a completed milestone', function () {
    $milestone = Milestone::factory()->completed()->create();
    Task::factory()->for($milestone)->done()->create();

    board($milestone)->set('taskForm.title', 'One more thing')->call('addTask');

    expect($milestone->fresh()?->completed_at)->toBeNull();
});

it('stays complete when a task is added straight to done', function () {
    $this->travelTo('2026-10-08 10:00:00');
    $milestone = Milestone::factory()->completed()->create();
    Task::factory()->for($milestone)->done()->create();

    $this->travelTo('2026-10-09 10:00:00');

    board($milestone)
        ->call('openAddTask', 'done')
        ->set('taskForm.title', 'Already did this')
        ->call('addTask');

    expect($milestone->fresh()?->completed_at?->toDateTimeString())->toBe('2026-10-08 10:00:00');
});

it('completes when the last not-done task is deleted', function () {
    $milestone = Milestone::factory()->create();
    Task::factory()->for($milestone)->done()->create(['position' => 1]);
    $leftover = Task::factory()->for($milestone)->create(['position' => 2]);

    board($milestone)->call('deleteTask', $leftover->id);

    expect($milestone->fresh()?->completed_at)->not->toBeNull();
});

it('is not complete with no tasks, even after deleting the last task', function () {
    $milestone = Milestone::factory()->completed()->create();
    $only = Task::factory()->for($milestone)->done()->create();

    board($milestone)->call('deleteTask', $only->id);

    expect($milestone->fresh()?->completed_at)->toBeNull();
});

it('keeps the latest completion date after reopening', function () {
    $milestone = Milestone::factory()->create();
    $task = Task::factory()->for($milestone)->create();

    $this->travelTo('2026-10-08 10:00:00');
    board($milestone)->call('moveTask', $task->id, 0, 'done');

    $this->travelTo('2026-10-09 10:00:00');
    board($milestone)->call('moveTask', $task->id, 0, 'todo');

    $this->travelTo('2026-10-12 18:00:00');
    board($milestone)->call('moveTask', $task->id, 0, 'done');

    expect($milestone->fresh()?->completed_at?->toDateTimeString())->toBe('2026-10-12 18:00:00');
});

it('keeps the completion date when done tasks are reordered or edited', function () {
    $this->travelTo('2026-10-08 10:00:00');
    $milestone = Milestone::factory()->completed()->create();
    $first = Task::factory()->for($milestone)->done()->create(['position' => 1]);
    Task::factory()->for($milestone)->done()->create(['position' => 2]);

    $this->travelTo('2026-10-09 10:00:00');

    board($milestone)->call('moveTask', $first->id, 1, 'done');

    Livewire::actingAs($milestone->subject->user)
        ->test(Panel::class, ['subject' => $milestone->subject])
        ->call('openTask', $first->id, true)
        ->set('form.title', 'Renamed')
        ->call('save');

    expect($milestone->fresh()?->completed_at?->toDateTimeString())->toBe('2026-10-08 10:00:00');
});

it('leaves completion to syncCompletion, not to factories', function () {
    $milestone = Milestone::factory()->create();
    Task::factory()->for($milestone)->done()->create();

    expect($milestone->fresh()?->completed_at)->toBeNull();

    $milestone->syncCompletion();

    expect($milestone->fresh()?->completed_at)->not->toBeNull();
});
