<?php

use App\Events\BoardChanged;
use App\Livewire\Milestones\Show;
use App\Livewire\Sessions\LogSession;
use App\Models\Milestone;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Task;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Event::fake([BoardChanged::class]);
});

function liveBoard(Milestone $milestone): Testable
{
    return Livewire::actingAs($milestone->subject->user)
        ->test(Show::class, ['subject' => $milestone->subject, 'milestone' => $milestone]);
}

function assertBroadcastFor(Milestone $milestone, int $times = 1): void
{
    Event::assertDispatchedTimes(BoardChanged::class, $times);
    Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event) => $event->milestoneId === $milestone->id);
}

it('broadcasts after each board action', function (Closure $act) {
    $milestone = Milestone::factory()->create();
    $task = Task::factory()->for($milestone)->create();

    $act(liveBoard($milestone), $task);

    assertBroadcastFor($milestone);
})->with([
    'update' => [fn (Testable $page) => $page->call('edit')->set('form.title', 'Renamed')->call('update')],
    'addTask' => [fn (Testable $page) => $page->set('taskForm.title', 'New task')->call('addTask')],
    'deleteTask' => [fn (Testable $page, Task $task) => $page->call('deleteTask', $task->id)],
    'moveTask' => [fn (Testable $page, Task $task) => $page->call('moveTask', $task->id, 0, 'done')],
    'startTimer' => [fn (Testable $page, Task $task) => $page->call('startTimer', $task->id)],
    'addNote' => [fn (Testable $page) => $page->set('noteForm.body', 'A note')->call('addNote')],
]);

it('broadcasts after stopping a timer', function () {
    $task = Task::factory()->create();
    StudySession::factory()->forTask($task)->running()->create(['started_at' => now()->subMinutes(5)]);

    liveBoard($task->milestone)->call('stopTimer');

    assertBroadcastFor($task->milestone);
});

it('does not broadcast when nothing changed', function () {
    $milestone = Milestone::factory()->create();

    liveBoard($milestone)
        ->call('addTask')
        ->assertHasErrors(['taskForm.title'])
        ->call('openAddTask', 'done');

    Event::assertNotDispatched(BoardChanged::class);
});

it('broadcasts after logging a session on a milestone', function () {
    $milestone = Milestone::factory()->create();

    Livewire::actingAs($milestone->subject->user)
        ->test(LogSession::class, ['milestone' => $milestone])
        ->set('form.minutes', 30)
        ->call('save');

    assertBroadcastFor($milestone);
});

it('does not broadcast when logging from Today', function () {
    $subject = Subject::factory()->create();

    Livewire::actingAs($subject->user)
        ->test(LogSession::class)
        ->set('form.subject_id', $subject->id)
        ->set('form.minutes', 30)
        ->call('save')
        ->assertHasNoErrors();

    Event::assertNotDispatched(BoardChanged::class);
});

it('broadcasts on the milestone\'s private channel as board.changed', function () {
    $event = new BoardChanged(42);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-milestones.42')
        ->and($event->broadcastAs())->toBe('board.changed');
});

it('reloads the board when another tab changes it', function () {
    $milestone = Milestone::factory()->create(['title' => 'Before']);
    $page = liveBoard($milestone)->assertDontSee('Added elsewhere');

    // Another tab adds a task and renames the milestone
    Task::factory()->for($milestone)->create(['title' => 'Added elsewhere']);
    $milestone->update(['title' => 'After']);

    $page->dispatch('echo-private:milestones.'.$milestone->id.',.board.changed')
        ->assertSee('Added elsewhere')
        ->assertSee('After');
});

it('shows the live indicator on the board', function () {
    liveBoard(Milestone::factory()->create())
        ->assertSeeHtml('data-test="live-indicator"')
        ->assertSee('Offline');
});
