<?php

use App\Enums\TaskStatus;
use App\Events\BoardChanged;
use App\Livewire\Subjects\Board;
use App\Livewire\Subjects\Show;
use App\Livewire\Tasks\Panel;
use App\Models\Milestone;
use App\Models\Subject;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Event::fake([BoardChanged::class]);
});

function subjectBoard(Subject $subject): Testable
{
    return Livewire::actingAs($subject->user)->test(Board::class, ['subject' => $subject]);
}

/**
 * Two milestones with two to-do tasks each: A1, A2 in the first, B1, B2 in the second.
 *
 * @return array{0: Subject, 1: array<string, Task>}
 */
function twoMilestones(): array
{
    $subject = Subject::factory()->create();
    $first = Milestone::factory()->for($subject)->create(['title' => 'First steps', 'position' => 1]);
    $second = Milestone::factory()->for($subject)->create(['title' => 'Next steps', 'position' => 2]);

    $tasks = [];

    foreach ([[$first, 'A'], [$second, 'B']] as [$milestone, $letter]) {
        foreach ([1, 2] as $position) {
            $tasks["{$letter}{$position}"] = Task::factory()->for($milestone)->create(['title' => "Task {$letter}{$position}", 'position' => $position]);
        }
    }

    return [$subject, $tasks];
}

/**
 * Titles of a milestone's tasks in board order, with their statuses.
 *
 * @return list<string>
 */
function boardOrder(Milestone $milestone): array
{
    return $milestone->tasks()->get()->map(fn (Task $task) => "{$task->title}:{$task->status->value}")->all();
}

// The tab

it('opens the board tab from the subject page', function () {
    $subject = Subject::factory()->create();

    Livewire::actingAs($subject->user)
        ->test(Show::class, ['subject' => $subject])
        ->assertSet('view', 'path')
        ->assertSeeText('The path')
        ->assertDontSeeLivewire(Board::class)
        ->set('view', 'board')
        ->assertSeeLivewire(Board::class)
        ->assertDontSeeText('The path');
});

it('remembers the tab in the address', function () {
    $subject = Subject::factory()->create();

    $this->actingAs($subject->user)
        ->get(route('subjects.show', ['subject' => $subject, 'view' => 'board']))
        ->assertOk()
        ->assertSeeLivewire(Board::class);
});

it('falls back to the path for an unknown tab', function () {
    $subject = Subject::factory()->create();

    Livewire::withQueryParams(['view' => 'kanban'])
        ->actingAs($subject->user)
        ->test(Show::class, ['subject' => $subject])
        ->assertSet('view', 'path');
});

// Showing

it('shows every task in its column, grouped by milestone in path order', function () {
    [$subject, $tasks] = twoMilestones();
    $tasks['B1']->update(['status' => TaskStatus::InProgress]);

    subjectBoard($subject)
        ->assertSeeInOrder(['To do', '01 · First steps', 'Task A1', 'Task A2', '02 · Next steps', 'Task B2', 'In progress', '02 · Next steps', 'Task B1', 'Done', 'Nothing here yet'])
        ->assertSeeHtml('wire:sort:group="subject-'.$subject->id.'"')
        ->assertSeeHtml("\$dispatch('open-task', { id: {$tasks['A1']->id} })");
});

it('folds a finished milestone\'s tasks until opened', function () {
    $subject = Subject::factory()->create();
    $finished = Milestone::factory()->for($subject)->completed()->create(['title' => 'Basics', 'position' => 1]);
    Task::factory()->for($finished)->done()->create(['title' => 'Hidden away', 'position' => 1]);
    Task::factory()->for($finished)->done()->create(['title' => 'Also hidden', 'position' => 2]);

    subjectBoard($subject)
        ->assertSeeText('01 · Basics')
        ->assertSeeText('Show 2 tasks')
        ->assertDontSeeText('Hidden away')
        ->call('toggle', $finished->id)
        ->assertSeeText('Hidden away')
        ->assertSeeText('Hide')
        ->call('toggle', $finished->id)
        ->assertDontSeeText('Hidden away');
});

it('says when the subject has no milestones or no tasks', function () {
    $subject = Subject::factory()->create();

    subjectBoard($subject)->assertSeeText('No milestones yet.');

    Milestone::factory()->for($subject)->create();

    subjectBoard($subject)->assertSeeText('No tasks yet.');
});

it('loads the board with the same queries however many milestones there are', function () {
    $count = function (int $milestones): int {
        $subject = Subject::factory()->create();
        Milestone::factory()->for($subject)->count($milestones)
            ->sequence(fn ($sequence) => ['position' => $sequence->index + 1])
            ->has(Task::factory()->count(2)->sequence(['status' => TaskStatus::Todo], ['status' => TaskStatus::Done]))
            ->create();

        DB::enableQueryLog();
        DB::flushQueryLog();
        subjectBoard($subject);

        return count(DB::getQueryLog());
    };

    expect($count(5))->toBe($count(1));
});

// Moving

it('moves a card to another column, keeping it in its milestone', function () {
    [$subject, $tasks] = twoMilestones();

    // To do shows A1, A2, B1, B2; dropping B1 first in In progress
    subjectBoard($subject)
        ->call('moveTask', $tasks['B1']->id, 0, 'in_progress')
        ->assertDispatched('task-changed', milestoneId: $tasks['B1']->milestone_id);

    expect(boardOrder($tasks['B1']->milestone))->toBe(['Task B2:todo', 'Task B1:in_progress'])
        ->and(boardOrder($tasks['A1']->milestone))->toBe(['Task A1:todo', 'Task A2:todo']);

    Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event) => $event->milestoneId === $tasks['B1']->milestone_id);
});

it('works out the place in the milestone from the drop position in the column', function (string $task, int $position, string $milestone, array $order) {
    [$subject, $tasks] = twoMilestones();

    subjectBoard($subject)->call('moveTask', $tasks[$task]->id, $position, 'todo');

    expect(boardOrder($tasks[$milestone]->milestone))->toBe($order);
})->with([
    'B2 to the top of the column: first in its milestone' => ['B2', 0, 'B1', ['Task B2:todo', 'Task B1:todo']],
    'A1 below A2' => ['A1', 1, 'A1', ['Task A2:todo', 'Task A1:todo']],
    'A1 dropped among the second milestone: last in its own' => ['A1', 3, 'A1', ['Task A2:todo', 'Task A1:todo']],
    'B1 below everything' => ['B1', 3, 'B1', ['Task B2:todo', 'Task B1:todo']],
    'B1 back where it was' => ['B1', 2, 'B1', ['Task B1:todo', 'Task B2:todo']],
]);

it('moves a card to the end of its milestone in a column from the menu', function () {
    [$subject, $tasks] = twoMilestones();
    $tasks['A2']->update(['status' => TaskStatus::Done, 'position' => 2]);
    $tasks['B2']->update(['status' => TaskStatus::Done]);

    // Done shows A2, B2; the menu passes the column's size
    subjectBoard($subject)->call('moveTask', $tasks['A1']->id, 2, 'done');

    expect(boardOrder($tasks['A1']->milestone))->toBe(['Task A2:done', 'Task A1:done']);
});

it('ignores folded cards when working out the drop position', function () {
    $subject = Subject::factory()->create();
    $finished = Milestone::factory()->for($subject)->completed()->create(['position' => 1]);
    Task::factory()->for($finished)->done()->count(3)->create();
    $current = Milestone::factory()->for($subject)->create(['position' => 2]);
    $done = Task::factory()->for($current)->done()->create(['title' => 'Done one', 'position' => 1]);
    $task = Task::factory()->for($current)->create(['title' => 'Now done', 'position' => 2]);

    // Done shows only "Done one" (the finished milestone is folded): dropping at 0 puts the task above it
    subjectBoard($subject)->call('moveTask', $task->id, 0, 'done');

    expect(boardOrder($current))->toBe(['Now done:done', 'Done one:done']);
});

it('finishes a milestone when its last card reaches Done, and reopens it', function () {
    $subject = Subject::factory()->create();
    $milestone = Milestone::factory()->for($subject)->create();
    $task = Task::factory()->for($milestone)->create();

    $board = subjectBoard($subject)->call('moveTask', $task->id, 0, 'done');
    expect($milestone->fresh()?->completed_at)->not->toBeNull();

    $board->call('moveTask', $task->id, 0, 'todo');
    expect($milestone->fresh()?->completed_at)->toBeNull();
});

it('rejects an unknown column', function () {
    [$subject, $tasks] = twoMilestones();

    subjectBoard($subject)->call('moveTask', $tasks['A1']->id, 0, 'archived')->assertStatus(422);
});

// Staying in sync

it('reloads when a task changes in the panel or another tab', function () {
    [$subject, $tasks] = twoMilestones();

    $board = subjectBoard($subject)->assertSeeText('Task A1');

    $tasks['A1']->update(['title' => 'Renamed in the panel']);

    $board->dispatch('task-changed', milestoneId: $tasks['A1']->milestone_id)->assertSeeText('Renamed in the panel');
});

it('listens to every milestone\'s board channel', function () {
    [$subject, $tasks] = twoMilestones();

    $listeners = (fn () => $this->getListeners())->call(subjectBoard($subject)->instance());

    expect($listeners)
        ->toHaveKey("echo-private:milestones.{$tasks['A1']->milestone_id},.board.changed")
        ->toHaveKey("echo-private:milestones.{$tasks['B1']->milestone_id},.board.changed")
        ->toHaveKey('task-changed');
});

it('updates the subject\'s progress after a change', function () {
    [$subject, $tasks] = twoMilestones();

    $page = Livewire::actingAs($subject->user)->test(Show::class, ['subject' => $subject])->assertSee('0 / 4');

    $tasks['A1']->update(['status' => TaskStatus::Done]);

    $page->dispatch('task-changed', milestoneId: $tasks['A1']->milestone_id)->assertSee('1 / 4');
});

it('opens tasks in the panel', function () {
    [$subject] = twoMilestones();

    subjectBoard($subject)->assertSeeLivewire(Panel::class);
});

// Authorization

it('keeps other users out', function () {
    $subject = Subject::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(Board::class, ['subject' => $subject])
        ->assertNotFound();
});

it('cannot move another subject\'s task', function () {
    [$subject] = twoMilestones();
    $theirs = Task::factory()->create();

    subjectBoard($subject)->call('moveTask', $theirs->id, 0, 'done')->assertNotFound();

    expect($theirs->fresh()?->status)->toBe(TaskStatus::Todo);
});
