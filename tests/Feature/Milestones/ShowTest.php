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

// Step D: viewing, editing and deleting the milestone, adding and ticking off tasks

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

it('lists tasks in position order', function () {
    $milestone = Milestone::factory()->create();
    Task::factory()->for($milestone)->create(['title' => 'Second task', 'position' => 2]);
    Task::factory()->for($milestone)->create(['title' => 'First task', 'position' => 1]);

    milestonePage($milestone)->assertSeeInOrder(['First task', 'Second task']);
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

it('ticks a task off and back on', function () {
    $milestone = Milestone::factory()->create();
    $task = Task::factory()->for($milestone)->create();

    $page = milestonePage($milestone)->call('toggle', $task->id);
    expect($task->fresh()->status)->toBe(TaskStatus::Done);

    $page->call('toggle', $task->id);
    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

it('marks an in-progress task as done when ticked', function () {
    $milestone = Milestone::factory()->create();
    $task = Task::factory()->for($milestone)->inProgress()->create();

    milestonePage($milestone)->call('toggle', $task->id);

    expect($task->fresh()->status)->toBe(TaskStatus::Done);
});

it('cannot toggle a task from another milestone', function () {
    $milestone = Milestone::factory()->create();
    $foreignTask = Task::factory()->create();

    milestonePage($milestone)
        ->call('toggle', $foreignTask->id)
        ->assertNotFound();

    expect($foreignTask->fresh()->status)->toBe(TaskStatus::Todo);
});

it('authorizes every write action, not just the page load', function () {
    $milestone = Milestone::factory()->create();
    $task = Task::factory()->for($milestone)->create();
    $otherUser = User::factory()->create();

    $toggling = milestonePage($milestone);
    $adding = milestonePage($milestone)->set('taskForm.title', 'Hijacked');

    $this->actingAs($otherUser);

    $toggling->call('toggle', $task->id)->assertNotFound();
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

it('shows an empty state when the milestone has no tasks', function () {
    milestonePage(Milestone::factory()->create())->assertSee('No tasks yet.');
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
