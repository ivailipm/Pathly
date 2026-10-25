<?php

use App\Enums\TaskStatus;
use App\Livewire\Milestones\Show;
use App\Models\Milestone;
use App\Models\Subject;
use App\Models\Task;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Mount the milestone page as the subject's owner.
 */
function milestonePage(Milestone $milestone): Testable
{
    return Livewire::actingAs($milestone->subject->user)
        ->test(Show::class, ['subject' => $milestone->subject, 'milestone' => $milestone]);
}

// Step D: viewing, editing and deleting the milestone, adding tasks

it('shows the milestone to the owner', function () {
    $milestone = Milestone::factory()->create(['title' => 'Routing']);

    $this->actingAs($milestone->subject->user)
        ->get(route('milestones.show', [$milestone->subject, $milestone]))
        ->assertOk()
        ->assertSee('Routing');
});

it('answers 404 to other users', function () {
    $milestone = Milestone::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->get(route('milestones.show', [$milestone->subject, $milestone]))
        ->assertNotFound();

    Livewire::actingAs($otherUser)
        ->test(Show::class, ['subject' => $milestone->subject, 'milestone' => $milestone])
        ->assertNotFound();
});

it('answers 404 when the milestone belongs to a different subject', function () {
    $milestone = Milestone::factory()->create();
    $otherSubject = Subject::factory()->for($milestone->subject->user)->create();

    $this->actingAs($milestone->subject->user)
        ->get(route('milestones.show', [$otherSubject, $milestone]))
        ->assertNotFound();
});

it('adds a task as todo after the highest position', function () {
    $milestone = Milestone::factory()->create();
    Task::factory()->for($milestone)->create(['position' => 5]);

    milestonePage($milestone)
        ->set('taskForm.title', 'Read the routing docs')
        ->set('taskForm.url', 'https://laravel.com/docs/routing')
        ->call('addTask')
        ->assertHasNoErrors()
        ->assertSet('taskForm.title', '');

    $task = $milestone->tasks()->where('title', 'Read the routing docs')->sole();

    expect($task->status)->toBe(TaskStatus::Todo)
        ->and($task->position)->toBe(6)
        ->and($task->url)->toBe('https://laravel.com/docs/routing');
});

it('only accepts http and https links', function () {
    $milestone = Milestone::factory()->create();

    milestonePage($milestone)
        ->set('taskForm.title', 'Sneaky')
        ->set('taskForm.url', 'javascript:alert(1)')
        ->call('addTask')
        ->assertHasErrors(['taskForm.url' => 'url']);

    expect($milestone->tasks()->count())->toBe(0);
});

it('limits the task title and link length', function () {
    milestonePage(Milestone::factory()->create())
        ->set('taskForm.title', str_repeat('a', 256))
        ->set('taskForm.url', 'https://example.com/'.str_repeat('a', 236))
        ->call('addTask')
        ->assertHasErrors(['taskForm.title' => 'max', 'taskForm.url' => 'max']);
});

it('authorizes every write action, not just the page load', function () {
    $milestone = Milestone::factory()->create();
    $task = Task::factory()->for($milestone)->create();
    $otherUser = User::factory()->create();

    $moving = milestonePage($milestone);
    $adding = milestonePage($milestone)->set('taskForm.title', 'Hijacked');

    $this->actingAs($otherUser);

    $moving->call('moveTask', $task->id, 0, 'done')->assertNotFound();
    $adding->call('addTask')->assertNotFound();

    expect($task->fresh()->status)->toBe(TaskStatus::Todo)
        ->and($milestone->tasks()->count())->toBe(1);
});

it('lets the owner edit the milestone', function () {
    $milestone = Milestone::factory()->create(['title' => 'Old title']);

    milestonePage($milestone)
        ->call('edit')
        ->assertSet('form.title', 'Old title')
        ->set('form.title', 'New title')
        ->call('update')
        ->assertHasNoErrors()
        ->assertSet('editing', false);

    expect($milestone->fresh()->title)->toBe('New title');
});

it('deletes the milestone with its tasks and goes back to the subject', function () {
    $task = Task::factory()->create();
    $milestone = $task->milestone;

    milestonePage($milestone)
        ->call('delete')
        ->assertRedirect(route('subjects.show', $milestone->subject));

    expect(Milestone::count())->toBe(0)
        ->and(Task::count())->toBe(0)
        ->and(Subject::count())->toBe(1);
});

// Step E: editing and deleting tasks

it('lets the owner edit a task', function () {
    $milestone = Milestone::factory()->create();
    $task = Task::factory()->for($milestone)->create(['title' => 'Old title', 'url' => null]);

    milestonePage($milestone)
        ->call('editTask', $task->id)
        ->assertSet('editingTaskId', $task->id)
        ->assertSet('editTaskForm.title', 'Old title')
        ->set('editTaskForm.title', 'New title')
        ->set('editTaskForm.url', 'https://laravel.com')
        ->call('updateTask')
        ->assertHasNoErrors()
        ->assertSet('editingTaskId', null);

    expect($task->fresh())
        ->title->toBe('New title')
        ->url->toBe('https://laravel.com');
});

it('validates a task edit', function () {
    $milestone = Milestone::factory()->create();
    $task = Task::factory()->for($milestone)->create(['title' => 'Old title']);

    milestonePage($milestone)
        ->call('editTask', $task->id)
        ->set('editTaskForm.title', '')
        ->call('updateTask')
        ->assertHasErrors(['editTaskForm.title' => 'required'])
        ->call('cancelTaskEdit')
        ->assertHasNoErrors()
        ->assertSet('editingTaskId', null);

    expect($task->fresh()->title)->toBe('Old title');
});

it('deletes a task', function () {
    $milestone = Milestone::factory()->create();
    $task = Task::factory()->for($milestone)->create();

    milestonePage($milestone)->call('deleteTask', $task->id);

    expect(Task::count())->toBe(0);
});

it('stops editing when the task being edited is deleted', function () {
    $milestone = Milestone::factory()->create();
    $task = Task::factory()->for($milestone)->create();

    milestonePage($milestone)
        ->call('editTask', $task->id)
        ->call('deleteTask', $task->id)
        ->assertSet('editingTaskId', null)
        ->assertSet('editTaskForm.title', '');
});

it('cannot edit or delete a task from another milestone', function () {
    $milestone = Milestone::factory()->create();
    $foreignTask = Task::factory()->create(['title' => 'Not yours']);

    milestonePage($milestone)->call('editTask', $foreignTask->id)->assertNotFound();
    milestonePage($milestone)->call('deleteTask', $foreignTask->id)->assertNotFound();

    expect($foreignTask->fresh()->title)->toBe('Not yours');
});

// Phase 4: the board

/**
 * T1 and T2 to do, T3 in progress, T4 done, at positions 1 to 4.
 *
 * @return array<string, Task>
 */
function sampleBoard(Milestone $milestone): array
{
    return [
        'T1' => Task::factory()->for($milestone)->create(['title' => 'T1', 'position' => 1]),
        'T2' => Task::factory()->for($milestone)->create(['title' => 'T2', 'position' => 2]),
        'T3' => Task::factory()->for($milestone)->inProgress()->create(['title' => 'T3', 'position' => 3]),
        'T4' => Task::factory()->for($milestone)->done()->create(['title' => 'T4', 'position' => 4]),
    ];
}

/**
 * Each task's updated_at as a string, keyed by title.
 *
 * @return array<string, string|null>
 */
function updatedAt(Milestone $milestone): array
{
    return $milestone->tasks()->get()
        ->mapWithKeys(fn (Task $task) => [$task->title => $task->updated_at?->toDateTimeString()])
        ->sortKeys()
        ->all();
}

it('groups the tasks into board columns in position order', function () {
    $milestone = Milestone::factory()->create();
    Task::factory()->for($milestone)->create(['title' => 'Second', 'position' => 2]);
    Task::factory()->for($milestone)->create(['title' => 'First', 'position' => 1]);
    Task::factory()->for($milestone)->done()->create(['title' => 'Finished', 'position' => 3]);

    $columns = milestonePage($milestone)->instance()->columns;

    expect(array_keys($columns))->toBe(['todo', 'in_progress', 'done'])
        ->and($columns['todo']->pluck('title')->all())->toBe(['First', 'Second'])
        ->and($columns['in_progress']->all())->toBe([])
        ->and($columns['done']->pluck('title')->all())->toBe(['Finished']);
});

it('shows an empty board when the milestone has no tasks', function () {
    $page = milestonePage(Milestone::factory()->create())
        ->assertSee('No tasks yet. Add a resource or exercise below.')
        ->assertDontSeeHtml('data-flux-progress');

    expect(substr_count($page->html(), 'Nothing here yet'))->toBe(3);
});

it('shows the milestone progress', function () {
    $milestone = Milestone::factory()->create();
    $first = Task::factory()->for($milestone)->create(['position' => 1]);
    Task::factory()->for($milestone)->create(['position' => 2]);

    milestonePage($milestone)->assertSee('0 of 2 tasks done');

    $first->update(['status' => TaskStatus::Done]);

    milestonePage($milestone)->assertSee('1 of 2 tasks done');
});

it('moves a task to another column and renumbers the milestone', function () {
    $milestone = Milestone::factory()->create();
    $tasks = sampleBoard($milestone);

    milestonePage($milestone)->call('moveTask', $tasks['T1']->id, 1, 'in_progress');

    expect($tasks['T1']->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and($milestone->tasks()->pluck('position', 'title')->all())
        ->toBe(['T2' => 1, 'T3' => 2, 'T1' => 3, 'T4' => 4]);
});

it('reorders tasks inside a column', function () {
    $milestone = Milestone::factory()->create();
    $tasks = sampleBoard($milestone);

    milestonePage($milestone)->call('moveTask', $tasks['T2']->id, 0, 'todo');

    expect($milestone->tasks()->pluck('position', 'title')->all())
        ->toBe(['T2' => 1, 'T1' => 2, 'T3' => 3, 'T4' => 4])
        ->and($tasks['T2']->fresh()->status)->toBe(TaskStatus::Todo);
});

it('moves a task into an empty column', function () {
    $milestone = Milestone::factory()->create();
    $task = Task::factory()->for($milestone)->create(['position' => 1]);

    milestonePage($milestone)->call('moveTask', $task->id, 0, 'done');

    expect($task->fresh())
        ->status->toBe(TaskStatus::Done)
        ->position->toBe(1);
});

it('keeps the drop position inside the column', function () {
    $milestone = Milestone::factory()->create();
    $tasks = sampleBoard($milestone);

    milestonePage($milestone)
        ->call('moveTask', $tasks['T1']->id, 99, 'todo')
        ->call('moveTask', $tasks['T4']->id, -5, 'todo');

    expect($milestone->tasks()->pluck('position', 'title')->all())
        ->toBe(['T4' => 1, 'T2' => 2, 'T1' => 3, 'T3' => 4]);
});

it('closes gaps in the positions', function () {
    $milestone = Milestone::factory()->create();
    $first = Task::factory()->for($milestone)->create(['title' => 'A', 'position' => 1]);
    Task::factory()->for($milestone)->create(['title' => 'B', 'position' => 5]);
    Task::factory()->for($milestone)->create(['title' => 'C', 'position' => 9]);

    milestonePage($milestone)->call('moveTask', $first->id, 0, 'todo');

    expect($milestone->tasks()->pluck('position', 'title')->all())->toBe(['A' => 1, 'B' => 2, 'C' => 3]);
});

it('saves nothing when a move changes nothing', function () {
    $milestone = Milestone::factory()->create();
    $tasks = sampleBoard($milestone);
    $before = updatedAt($milestone);

    $this->travel(5)->minutes();

    milestonePage($milestone)->call('moveTask', $tasks['T1']->id, 0, 'todo');

    expect(updatedAt($milestone))->toBe($before);
});

it('only saves the tasks whose status or position changed', function () {
    $milestone = Milestone::factory()->create();
    $tasks = sampleBoard($milestone);
    $before = updatedAt($milestone);

    $this->travel(5)->minutes();

    milestonePage($milestone)->call('moveTask', $tasks['T1']->id, 1, 'in_progress');
    $after = updatedAt($milestone);

    expect($after['T1'])->not->toBe($before['T1'])
        ->and($after['T2'])->not->toBe($before['T2'])
        ->and($after['T3'])->not->toBe($before['T3'])
        ->and($after['T4'])->toBe($before['T4']);
});

it('rejects an unknown status', function () {
    $milestone = Milestone::factory()->create();
    $tasks = sampleBoard($milestone);

    milestonePage($milestone)
        ->call('moveTask', $tasks['T1']->id, 0, 'archived')
        ->assertStatus(422);

    expect($milestone->tasks()->pluck('position', 'title')->all())
        ->toBe(['T1' => 1, 'T2' => 2, 'T3' => 3, 'T4' => 4])
        ->and($tasks['T1']->fresh()->status)->toBe(TaskStatus::Todo);
});

it('cannot move a task from another milestone', function () {
    $milestone = Milestone::factory()->create();
    $foreignTask = Task::factory()->create();

    milestonePage($milestone)
        ->call('moveTask', $foreignTask->id, 0, 'done')
        ->assertNotFound();

    expect($foreignTask->fresh()->status)->toBe(TaskStatus::Todo);
});

it('updates the progress after a move', function () {
    $milestone = Milestone::factory()->create();
    $tasks = sampleBoard($milestone);

    milestonePage($milestone)
        ->assertSee('1 of 4 tasks done')
        ->call('moveTask', $tasks['T1']->id, 0, 'done')
        ->assertSee('2 of 4 tasks done');
});

it('keeps a task edit open when another task moves', function () {
    $milestone = Milestone::factory()->create();
    $tasks = sampleBoard($milestone);

    milestonePage($milestone)
        ->call('editTask', $tasks['T1']->id)
        ->call('moveTask', $tasks['T3']->id, 0, 'done')
        ->assertSet('editingTaskId', $tasks['T1']->id);
});

it('offers to move a task to the other columns', function () {
    $milestone = Milestone::factory()->create();
    Task::factory()->for($milestone)->create();

    milestonePage($milestone)
        ->assertSee('Move to In progress')
        ->assertSee('Move to Done')
        ->assertDontSee('Move to To do');
});

it('makes each column a sortable list of tasks', function () {
    $milestone = Milestone::factory()->create();
    $tasks = sampleBoard($milestone);

    $html = milestonePage($milestone)->html();

    expect(substr_count($html, 'wire:sort="moveTask"'))->toBe(3);

    foreach ($tasks as $task) {
        expect($html)->toContain('wire:sort:item="'.$task->id.'"');
    }
});
