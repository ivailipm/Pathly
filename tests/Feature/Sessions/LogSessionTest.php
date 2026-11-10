<?php

use App\Livewire\Milestones\Show as MilestonePage;
use App\Livewire\Sessions\LogSession;
use App\Models\Milestone;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['app.display_timezone' => 'Europe/Sofia']);
    $this->travelTo('2026-10-23 10:00:00');
});

it('logs a session on a task of the milestone', function () {
    $task = Task::factory()->create();
    $milestone = $task->milestone;

    Livewire::actingAs($milestone->subject->user)
        ->test(LogSession::class, ['milestone' => $milestone])
        ->assertSet('form.subject_id', $milestone->subject_id)
        ->assertSet('form.date', '2026-10-23')
        ->set('form.task_id', $task->id)
        ->set('form.minutes', 45)
        ->call('save')
        ->assertHasNoErrors();

    $session = StudySession::sole();

    expect($session->user_id)->toBe($milestone->subject->user_id)
        ->and($session->subject_id)->toBe($milestone->subject_id)
        ->and($session->task_id)->toBe($task->id)
        ->and($session->minutes)->toBe(45);
});

it('logs a session on the subject without a task', function () {
    $subject = Subject::factory()->create();

    Livewire::actingAs($subject->user)
        ->test(LogSession::class)
        ->set('form.subject_id', $subject->id)
        ->set('form.task_id', '')
        ->set('form.minutes', '30')
        ->call('save')
        ->assertHasNoErrors();

    expect(StudySession::sole())
        ->subject_id->toBe($subject->id)
        ->task_id->toBeNull()
        ->minutes->toBe(30);
});

it('logs a session on a task of the chosen subject from Today', function () {
    $task = Task::factory()->create();
    $subject = $task->milestone->subject;

    Livewire::actingAs($subject->user)
        ->test(LogSession::class)
        ->set('form.subject_id', $subject->id)
        ->set('form.task_id', $task->id)
        ->set('form.minutes', 20)
        ->call('save')
        ->assertHasNoErrors();

    expect(StudySession::sole()->task_id)->toBe($task->id);
});

it('rejects a task from another milestone with 404', function () {
    $milestone = Milestone::factory()->create();
    $elsewhere = Task::factory()->for(Milestone::factory()->for($milestone->subject))->create();

    Livewire::actingAs($milestone->subject->user)
        ->test(LogSession::class, ['milestone' => $milestone])
        ->set('form.task_id', $elsewhere->id)
        ->set('form.minutes', 30)
        ->call('save')
        ->assertNotFound();

    expect(StudySession::count())->toBe(0);
});

it('rejects another user\'s subject or task with 404', function () {
    $user = User::factory()->create();
    $mine = Subject::factory()->for($user)->create();
    $theirs = Task::factory()->create();

    Livewire::actingAs($user)
        ->test(LogSession::class)
        ->set('form.subject_id', $theirs->milestone->subject_id)
        ->set('form.minutes', 30)
        ->call('save')
        ->assertNotFound();

    Livewire::actingAs($user)
        ->test(LogSession::class)
        ->set('form.subject_id', $mine->id)
        ->set('form.task_id', $theirs->id)
        ->set('form.minutes', 30)
        ->call('save')
        ->assertNotFound();

    expect(StudySession::count())->toBe(0);
});

it('rejects an archived subject from Today with 404', function () {
    $subject = Subject::factory()->archived()->create();

    Livewire::actingAs($subject->user)
        ->test(LogSession::class)
        ->set('form.subject_id', $subject->id)
        ->set('form.minutes', 30)
        ->call('save')
        ->assertNotFound();
});

it('answers 404 when mounted with another user\'s milestone', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(LogSession::class, ['milestone' => Milestone::factory()->create()])
        ->assertNotFound();
});

it('checks ownership again when saving', function () {
    $milestone = Milestone::factory()->create();
    $page = Livewire::actingAs($milestone->subject->user)
        ->test(LogSession::class, ['milestone' => $milestone])
        ->set('form.minutes', 30);

    $this->actingAs(User::factory()->create());

    $page->call('save')->assertNotFound();

    expect(StudySession::count())->toBe(0);
});

it('keeps the milestone\'s subject however the form is changed', function () {
    $milestone = Milestone::factory()->create();
    $otherSubject = Subject::factory()->for($milestone->subject->user)->create();

    Livewire::actingAs($milestone->subject->user)
        ->test(LogSession::class, ['milestone' => $milestone])
        ->set('form.subject_id', $otherSubject->id)
        ->set('form.minutes', 30)
        ->call('save');

    expect(StudySession::sole()->subject_id)->toBe($milestone->subject_id);
});

it('validates minutes between 1 and 600', function (mixed $minutes) {
    $subject = Subject::factory()->create();

    Livewire::actingAs($subject->user)
        ->test(LogSession::class)
        ->set('form.subject_id', $subject->id)
        ->set('form.minutes', $minutes)
        ->call('save')
        ->assertHasErrors(['form.minutes']);
})->with([0, 601, '', 'an hour']);

it('requires a subject on Today', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(LogSession::class)
        ->set('form.minutes', 30)
        ->call('save')
        ->assertHasErrors(['form.subject_id' => 'required']);
});

it('rejects a date in the future', function () {
    // 22:30 UTC on the 23rd is already the 24th in Sofia, so the 24th is today and the 25th is the future
    $this->travelTo('2026-10-23 22:30:00');
    $subject = Subject::factory()->create();

    Livewire::actingAs($subject->user)
        ->test(LogSession::class)
        ->assertSet('form.date', '2026-10-24')
        ->set('form.subject_id', $subject->id)
        ->set('form.minutes', 30)
        ->set('form.date', '2026-10-25')
        ->call('save')
        ->assertHasErrors(['form.date' => 'before_or_equal'])
        ->set('form.date', '2026-10-24')
        ->call('save')
        ->assertHasNoErrors();
});

it('stores a manual session at local noon', function () {
    $subject = Subject::factory()->create();

    Livewire::actingAs($subject->user)
        ->test(LogSession::class)
        ->set('form.subject_id', $subject->id)
        ->set('form.minutes', 30)
        ->set('form.date', '2026-10-20')
        ->call('save');

    // Noon in Sofia (UTC+3 in October) is 09:00 UTC
    expect(StudySession::sole()->started_at->toDateTimeString())->toBe('2026-10-20 09:00:00');
});

it('clears the task when the subject changes', function () {
    $task = Task::factory()->create();
    $subject = $task->milestone->subject;
    $other = Subject::factory()->for($subject->user)->create();

    Livewire::actingAs($subject->user)
        ->test(LogSession::class)
        ->set('form.subject_id', $subject->id)
        ->set('form.task_id', $task->id)
        ->set('form.subject_id', $other->id)
        ->assertSet('form.task_id', null);
});

it('offers the user\'s non-archived subjects and the chosen subject\'s tasks', function () {
    $user = User::factory()->create();
    $subject = Subject::factory()->for($user)->create(['title' => 'Spanish']);
    Subject::factory()->for($user)->archived()->create(['title' => 'Old']);
    Subject::factory()->create(['title' => 'Someone else']);
    Task::factory()->for(Milestone::factory()->for($subject))->create(['title' => 'Subjunctive drills']);

    $component = Livewire::actingAs($user)->test(LogSession::class);

    expect($component->instance()->subjectOptions->pluck('title')->all())->toBe(['Spanish'])
        ->and($component->instance()->taskOptions)->toBeEmpty();

    $component->set('form.subject_id', $subject->id);

    expect($component->instance()->taskOptions->pluck('title')->all())->toBe(['Subjunctive drills']);
});

it('offers only the milestone\'s tasks on a milestone page', function () {
    $milestone = Milestone::factory()->create();
    Task::factory()->for($milestone)->create(['title' => 'Here']);
    Task::factory()->for(Milestone::factory()->for($milestone->subject))->create(['title' => 'Elsewhere']);

    $component = Livewire::actingAs($milestone->subject->user)->test(LogSession::class, ['milestone' => $milestone]);

    expect($component->instance()->taskOptions->pluck('title')->all())->toBe(['Here']);

    $component->assertDontSee('Choose a subject');
});

it('allows logging on a milestone of an archived subject', function () {
    $milestone = Milestone::factory()->for(Subject::factory()->archived())->create();

    Livewire::actingAs($milestone->subject->user)
        ->test(LogSession::class, ['milestone' => $milestone])
        ->set('form.minutes', 30)
        ->call('save')
        ->assertHasNoErrors();

    expect(StudySession::count())->toBe(1);
});

it('closes the modal, resets the form and tells the page after saving', function () {
    $milestone = Milestone::factory()->create();

    Livewire::actingAs($milestone->subject->user)
        ->test(LogSession::class, ['milestone' => $milestone])
        ->set('form.minutes', 30)
        ->set('form.date', '2026-10-20')
        ->call('save')
        ->assertDispatched('session-logged')
        ->assertDispatched('modal-close', name: 'log-session')
        ->assertSet('form.minutes', null)
        ->assertSet('form.date', '2026-10-23')
        ->assertSet('form.subject_id', $milestone->subject_id);
});

it('is on the milestone page with a button that opens it', function () {
    $milestone = Milestone::factory()->create();

    Livewire::actingAs($milestone->subject->user)
        ->test(MilestonePage::class, ['subject' => $milestone->subject, 'milestone' => $milestone])
        ->assertSeeLivewire(LogSession::class)
        ->assertSee('Log a session');
});
