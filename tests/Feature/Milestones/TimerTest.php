<?php

use App\Livewire\Milestones\Show;
use App\Models\Milestone;
use App\Models\StudySession;
use App\Models\Task;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The milestone page of a task, as the subject's owner.
 */
function timerPage(Task $task): Testable
{
    $milestone = $task->milestone;

    return Livewire::actingAs($milestone->subject->user)
        ->test(Show::class, ['subject' => $milestone->subject, 'milestone' => $milestone]);
}

beforeEach(function () {
    $this->travelTo('2026-10-23 10:00:00');
});

it('starts a timer on a task', function () {
    $task = Task::factory()->create();

    timerPage($task)
        ->call('startTimer', $task->id)
        ->assertSee('Stop timer');

    $session = StudySession::sole();

    expect($session->task_id)->toBe($task->id)
        ->and($session->subject_id)->toBe($task->milestone->subject_id)
        ->and($session->user_id)->toBe($task->milestone->subject->user_id)
        ->and($session->minutes)->toBeNull()
        ->and($session->started_at->toDateTimeString())->toBe('2026-10-23 10:00:00');
});

it('stops the running timer before starting another, on any milestone', function () {
    $first = Task::factory()->create();
    $second = Task::factory()->for(Milestone::factory()->for($first->milestone->subject))->create();

    timerPage($first)->call('startTimer', $first->id);

    $this->travel(25)->minutes();

    timerPage($second)->call('startTimer', $second->id);

    expect(StudySession::query()->where('task_id', $first->id)->sole()->minutes)->toBe(25)
        ->and(StudySession::query()->where('task_id', $second->id)->sole()->minutes)->toBeNull();
});

it('saves the started minutes, at least one, when stopped', function () {
    $task = Task::factory()->create();
    $page = timerPage($task)->call('startTimer', $task->id);

    $this->travel(20)->seconds();
    $page->call('stopTimer')->assertHasNoErrors();

    expect(StudySession::sole()->minutes)->toBe(1);

    $page->call('startTimer', $task->id);
    $this->travel(45)->minutes();
    $this->travel(30)->seconds();
    $page->call('stopTimer');

    expect(StudySession::query()->latest('id')->first()?->minutes)->toBe(46);
});

it('saves a timer of exactly 600 minutes', function () {
    $task = Task::factory()->create();
    $page = timerPage($task)->call('startTimer', $task->id);

    $this->travel(600)->minutes();
    $page->call('stopTimer');

    expect(StudySession::sole()->minutes)->toBe(600);
});

it('discards a timer that ran over 600 minutes', function () {
    $task = Task::factory()->create();
    $page = timerPage($task)->call('startTimer', $task->id);

    $this->travel(11)->hours();

    $page->call('stopTimer')
        ->assertDispatched('toast-show');

    expect(StudySession::count())->toBe(0);
});

it('discards a forgotten timer when another one starts', function () {
    $first = Task::factory()->create();
    $second = Task::factory()->for($first->milestone)->create();
    $page = timerPage($first)->call('startTimer', $first->id);

    $this->travel(11)->hours();

    $page->call('startTimer', $second->id)->assertDispatched('toast-show');

    expect(StudySession::sole()->task_id)->toBe($second->id);
});

it('answers 404 when stopping without a running timer here', function () {
    $task = Task::factory()->create();
    $elsewhere = Task::factory()->for(Milestone::factory()->for($task->milestone->subject))->create();

    timerPage($elsewhere)->call('startTimer', $elsewhere->id);

    timerPage($task)->call('stopTimer')->assertNotFound();

    expect(StudySession::sole()->minutes)->toBeNull();
});

it('rejects a task from another milestone', function () {
    $task = Task::factory()->create();
    $elsewhere = Task::factory()->for(Milestone::factory()->for($task->milestone->subject))->create();

    timerPage($task)->call('startTimer', $elsewhere->id)->assertNotFound();

    expect(StudySession::count())->toBe(0);
});

it('keeps the minutes on the subject when the timed task is deleted', function () {
    $task = Task::factory()->create();
    $page = timerPage($task)->call('startTimer', $task->id);

    $this->travel(30)->minutes();
    $page->call('deleteTask', $task->id);

    $session = StudySession::sole();

    expect($session->minutes)->toBe(30)
        ->and($session->task_id)->toBeNull()
        ->and($session->subject_id)->toBe($task->milestone->subject_id);
});

it('keeps timing when the task moves', function () {
    $task = Task::factory()->create();

    timerPage($task)
        ->call('startTimer', $task->id)
        ->call('moveTask', $task->id, 0, 'done')
        ->assertSet('runningSession.task_id', $task->id);

    expect(StudySession::sole()->minutes)->toBeNull();
});

it('authorizes starting and stopping', function () {
    $task = Task::factory()->create();
    $starting = timerPage($task);
    $stopping = timerPage($task)->call('startTimer', $task->id);

    $this->actingAs(User::factory()->create());

    $starting->call('startTimer', $task->id)->assertNotFound();
    $stopping->call('stopTimer')->assertNotFound();

    expect(StudySession::sole()->minutes)->toBeNull();
});

it('offers the timer on the up next card and on in-progress cards', function () {
    $milestone = Milestone::factory()->create();
    Task::factory()->for($milestone)->create(['position' => 1]);
    Task::factory()->for($milestone)->create(['position' => 2]);
    Task::factory()->for($milestone)->inProgress()->create(['position' => 3]);
    Task::factory()->for($milestone)->inProgress()->create(['position' => 4]);
    Task::factory()->for($milestone)->done()->create(['position' => 5]);

    $html = Livewire::actingAs($milestone->subject->user)
        ->test(Show::class, ['subject' => $milestone->subject, 'milestone' => $milestone])
        ->html();

    // Up next is the first in-progress task, so only the two in-progress cards offer it
    expect(substr_count($html, 'Start timer'))->toBe(2);
});

it('shows a ticking clock on the timed card', function () {
    $task = Task::factory()->create();

    timerPage($task)
        ->call('startTimer', $task->id)
        ->assertSeeHtml('role="timer"')
        ->assertSeeHtml('2026-10-23T10:00:00+00:00')
        ->assertDontSee('Start timer');
});
