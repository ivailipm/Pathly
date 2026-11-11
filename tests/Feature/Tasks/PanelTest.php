<?php

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Events\BoardChanged;
use App\Livewire\Milestones\Show;
use App\Livewire\Tasks\Panel;
use App\Models\Milestone;
use App\Models\Note;
use App\Models\Source;
use App\Models\StudySession;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Event::fake([BoardChanged::class]);
});

/**
 * The task panel on the task's subject, as its owner, with the task open.
 */
function panelFor(Task $task, bool $edit = false): Testable
{
    return Livewire::actingAs($task->milestone->subject->user)
        ->test(Panel::class, ['subject' => $task->milestone->subject])
        ->call('openTask', $task->id, $edit);
}

// Opening and showing

it('opens a task with its milestone, type, link and time', function () {
    $milestone = Milestone::factory()->create(['title' => 'Real-time with Reverb', 'position' => 4]);
    $task = Task::factory()->for($milestone)->inProgress()->create([
        'title' => 'Build a presence channel',
        'type' => TaskType::Exercise,
        'url' => 'https://www.laravel.com/docs/broadcasting',
    ]);
    StudySession::factory()->forTask($task)->create(['minutes' => 50]);

    panelFor($task)
        ->assertSet('open', true)
        ->assertSet('taskId', $task->id)
        ->assertSeeText('01 · Real-time with Reverb')
        ->assertSeeText('Build a presence channel')
        ->assertSeeText('Exercise')
        ->assertSeeText('50 min')
        ->assertSeeText('laravel.com/docs/broadcasting')
        ->assertSeeHtml('aria-pressed="true"');
});

it('shows the description, keeping its line breaks', function () {
    $task = Task::factory()->create(['description' => "Start with the member list.\n\nCursors later."]);

    panelFor($task)
        ->assertSeeText('Start with the member list.')
        ->assertSeeHtml('whitespace-pre-line');
});

it('invites a description when there is none', function () {
    panelFor(Task::factory()->create(['description' => null]))
        ->assertSeeText('Add a description: what to do, page numbers, where you stopped.');
});

it('lists the field notes linked to the task, newest first, and no others', function () {
    $task = Task::factory()->create();
    Note::factory()->forTask($task)->create(['body' => 'Older note', 'created_at' => now()->subDays(2)]);
    Note::factory()->forTask($task)->create(['body' => 'Newer note', 'created_at' => now()->subDay()]);
    Note::factory()->for($task->milestone)->create(['body' => 'Unlinked note']);

    panelFor($task)
        ->assertSeeInOrder(['Newer note', 'Older note'])
        ->assertDontSee('Unlinked note');
});

it('says when a task has no field notes', function () {
    panelFor(Task::factory()->create())->assertSeeText('No field notes on this task yet.');
});

it('opens from a card on the board', function () {
    $task = Task::factory()->create(['title' => 'Read the docs']);

    Livewire::actingAs($task->milestone->subject->user)
        ->test(Show::class, ['subject' => $task->milestone->subject, 'milestone' => $task->milestone])
        ->assertSeeHtml("\$dispatch('open-task', { id: {$task->id} })")
        ->assertSeeHtml("\$dispatch('open-task', { id: {$task->id}, edit: true })")
        ->assertSeeLivewire(Panel::class);
});

it('closes and forgets the task', function () {
    panelFor(Task::factory()->create())
        ->call('close')
        ->assertSet('open', false)
        ->assertSet('taskId', null);
});

it('says so when the open task was deleted elsewhere', function () {
    $task = Task::factory()->create(['title' => 'Gone soon']);
    $panel = panelFor($task);

    $task->delete();

    $panel->call('$refresh')->assertSeeText('This task was deleted.');
});

// Editing

it('edits the title, link, type and description', function () {
    $task = Task::factory()->create(['title' => 'Old title', 'url' => null, 'type' => TaskType::Video, 'description' => null]);

    panelFor($task)
        ->call('edit')
        ->assertSet('editing', true)
        ->assertSet('form.title', 'Old title')
        ->assertSet('form.type', 'video')
        ->set('form.title', 'New title')
        ->set('form.url', 'https://laravel.com')
        ->set('form.type', 'exercise')
        ->set('form.description', "  Pages 12 to 20.\nStopped at 15.  ")
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', false)
        ->assertDispatched('task-changed', milestoneId: $task->milestone_id);

    expect($task->fresh())
        ->title->toBe('New title')
        ->url->toBe('https://laravel.com')
        ->type->toBe(TaskType::Exercise)
        ->description->toBe("Pages 12 to 20.\nStopped at 15.");
});

it('opens straight into the edit form from the card menu', function () {
    $task = Task::factory()->create(['title' => 'Old title', 'description' => 'Some notes']);

    panelFor($task, edit: true)
        ->assertSet('editing', true)
        ->assertSet('form.title', 'Old title')
        ->assertSet('form.description', 'Some notes');
});

it('clears a description', function () {
    $task = Task::factory()->create(['description' => 'Old']);

    panelFor($task, edit: true)->set('form.description', '   ')->call('save')->assertHasNoErrors();

    expect($task->fresh()?->description)->toBeNull();
});

it('validates an edit', function (string $field, mixed $value) {
    $task = Task::factory()->create(['title' => 'Old title']);

    panelFor($task, edit: true)
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors($field);

    expect($task->fresh()?->title)->toBe('Old title');
})->with([
    'no title' => ['form.title', ''],
    'not a link' => ['form.url', 'javascript:alert(1)'],
    'unknown type' => ['form.type', 'podcast'],
    'description too long' => ['form.description', str_repeat('a', 5001)],
]);

it('cancels an edit without saving', function () {
    $task = Task::factory()->create(['title' => 'Old title']);

    panelFor($task, edit: true)
        ->set('form.title', 'Changed')
        ->call('cancelEdit')
        ->assertSet('editing', false)
        ->assertHasNoErrors();

    expect($task->fresh()?->title)->toBe('Old title');
});

it('keeps the completion date when a done task is edited', function () {
    $this->travelTo('2026-10-08 10:00:00');
    $milestone = Milestone::factory()->completed()->create();
    $task = Task::factory()->for($milestone)->done()->create();

    $this->travelTo('2026-10-09 10:00:00');
    panelFor($task, edit: true)->set('form.title', 'Renamed')->call('save');

    expect($milestone->fresh()?->completed_at?->toDateTimeString())->toBe('2026-10-08 10:00:00');
});

// Status, sources and deleting

it('moves the task to the end of another column', function () {
    $milestone = Milestone::factory()->create();
    $done = Task::factory()->for($milestone)->done()->create(['position' => 1]);
    $task = Task::factory()->for($milestone)->create(['position' => 2]);

    panelFor($task)
        ->call('move', 'done')
        ->assertDispatched('task-changed');

    expect($task->fresh()?->status)->toBe(TaskStatus::Done)
        ->and($milestone->tasks()->pluck('id')->all())->toBe([$done->id, $task->id])
        ->and($milestone->fresh()?->completed_at)->not->toBeNull();
});

it('does nothing when the task is already in that column', function () {
    $task = Task::factory()->inProgress()->create();

    panelFor($task)->call('move', 'in_progress')->assertNotDispatched('task-changed');

    Event::assertNotDispatched(BoardChanged::class);
});

it('rejects an unknown status', function () {
    panelFor(Task::factory()->create())->call('move', 'archived')->assertStatus(422);
});

it('adds the task\'s link to the sources once', function () {
    $task = Task::factory()->create(['title' => 'Echo client API', 'url' => 'https://github.com/laravel/echo']);

    panelFor($task)
        ->assertSeeText('Add link to sources')
        ->call('pinSource')
        ->assertDispatched('toast-show')
        ->assertDontSeeText('Add link to sources')
        ->call('pinSource');

    expect(Source::query()->sole()->only(['title', 'url']))->toBe(['title' => 'Echo client API', 'url' => 'https://github.com/laravel/echo']);
});

it('deletes the task, closes and tells the board', function () {
    $task = Task::factory()->create();

    panelFor($task)
        ->call('delete')
        ->assertSet('open', false)
        ->assertDispatched('task-changed', milestoneId: $task->milestone_id);

    expect(Task::query()->count())->toBe(0);
});

it('keeps the time of a timer running on a deleted task', function () {
    $this->travelTo('2026-10-23 10:00:00');
    $task = Task::factory()->create();
    StudySession::factory()->forTask($task)->running()->create(['started_at' => now()->subMinutes(25)]);

    panelFor($task)->call('delete');

    expect(StudySession::query()->sole()->minutes)->toBe(25);
});

it('broadcasts each change to other tabs on the task\'s milestone', function (Closure $act) {
    $task = Task::factory()->create(['url' => 'https://example.com']);

    $act(panelFor($task));

    Event::assertDispatchedTimes(BoardChanged::class, 1);
    Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event) => $event->milestoneId === $task->milestone_id);
})->with([
    'save' => [fn (Testable $panel) => $panel->call('edit')->set('form.title', 'Renamed')->call('save')],
    'move' => [fn (Testable $panel) => $panel->call('move', 'done')],
    'delete' => [fn (Testable $panel) => $panel->call('delete')],
]);

it('does not broadcast for opening, a failed edit or adding a source', function () {
    $task = Task::factory()->create(['url' => 'https://example.com']);

    panelFor($task)
        ->call('edit')
        ->set('form.title', '')
        ->call('save')
        ->call('pinSource');

    Event::assertNotDispatched(BoardChanged::class);
});

it('reloads the board when the panel changes a task', function () {
    $task = Task::factory()->create(['title' => 'Old title']);

    $board = Livewire::actingAs($task->milestone->subject->user)
        ->test(Show::class, ['subject' => $task->milestone->subject, 'milestone' => $task->milestone])
        ->assertSee('Old title');

    $task->update(['title' => 'New title']);

    $board->dispatch('task-changed', milestoneId: $task->milestone_id)->assertSee('New title');
});

// Authorization

it('keeps other users out', function () {
    $task = Task::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(Panel::class, ['subject' => $task->milestone->subject])
        ->assertNotFound();
});

it('only opens tasks of its own subject', function () {
    $mine = Milestone::factory()->create();
    $theirs = Task::factory()->create(['title' => 'Not yours']);

    Livewire::actingAs($mine->subject->user)
        ->test(Panel::class, ['subject' => $mine->subject])
        ->call('openTask', $theirs->id)
        ->assertNotFound();
});

it('cannot act on a task without opening one', function (string $action) {
    $milestone = Milestone::factory()->create();

    Livewire::actingAs($milestone->subject->user)
        ->test(Panel::class, ['subject' => $milestone->subject])
        ->call($action, ...($action === 'move' ? ['done'] : []))
        ->assertNotFound();
})->with(['save', 'move', 'pinSource', 'delete']);

it('cannot be pointed at another task by changing its locked id', function () {
    $task = Task::factory()->create();

    panelFor($task)->set('taskId', 999);
})->throws(CannotUpdateLockedPropertyException::class);
