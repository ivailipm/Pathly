<?php

use App\Livewire\Milestones\Show as MilestonePage;
use App\Livewire\Subjects\Index;
use App\Livewire\Subjects\Show as SubjectPage;
use App\Models\Milestone;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * The subject as the Library loads it.
 */
function studiedSubject(Subject $subject): Subject
{
    return Subject::query()->whereKey($subject->id)->withLibraryData()->sole();
}

it('adds a session to the task, milestone and subject totals', function () {
    $task = Task::factory()->create();
    $milestone = $task->milestone;
    $subject = $milestone->subject;
    StudySession::factory()->forTask($task)->create(['minutes' => 45]);

    $board = Livewire::actingAs($subject->user)
        ->test(MilestonePage::class, ['subject' => $subject, 'milestone' => $milestone]);

    expect($board->instance()->columns['todo']->sole()->minutes_logged)->toBe(45)
        ->and($board->instance()->minutesLogged)->toBe(45);

    Livewire::actingAs($subject->user)
        ->test(SubjectPage::class, ['subject' => $subject])
        ->assertSet('minutesLogged', 45)
        ->assertSeeText('45 min');
});

it('adds a task-less session to the subject only', function () {
    $task = Task::factory()->create();
    $subject = $task->milestone->subject;
    StudySession::factory()->forSubject($subject)->create(['minutes' => 30]);

    $board = Livewire::actingAs($subject->user)
        ->test(MilestonePage::class, ['subject' => $subject, 'milestone' => $task->milestone]);

    expect($board->instance()->minutesLogged)->toBe(0)
        ->and($board->instance()->columns['todo']->sole()->minutes_logged)->toBeNull();

    Livewire::actingAs($subject->user)
        ->test(SubjectPage::class, ['subject' => $subject])
        ->assertSet('minutesLogged', 30);
});

it('ignores running sessions in totals', function () {
    $task = Task::factory()->create();
    StudySession::factory()->forTask($task)->running()->create();

    $subject = $task->milestone->subject;

    Livewire::actingAs($subject->user)
        ->test(SubjectPage::class, ['subject' => $subject])
        ->assertSet('minutesLogged', 0);

    expect(studiedSubject($subject)->last_studied_at)->toBeNull();
});

it('keeps the subject total after the task is deleted', function () {
    $task = Task::factory()->create();
    $subject = $task->milestone->subject;
    StudySession::factory()->forTask($task)->create(['minutes' => 50]);

    $task->delete();

    Livewire::actingAs($subject->user)
        ->test(SubjectPage::class, ['subject' => $subject])
        ->assertSet('minutesLogged', 50);
});

it('shows time logged on the milestone page', function () {
    $task = Task::factory()->create();
    StudySession::factory()->forTask($task)->create(['minutes' => 250]);
    $milestone = $task->milestone;

    Livewire::actingAs($milestone->subject->user)
        ->test(MilestonePage::class, ['subject' => $milestone->subject, 'milestone' => $milestone])
        ->assertSee('Time logged')
        ->assertSeeText('4 h 10 min');
});

it('refreshes the totals when a session is logged', function () {
    $task = Task::factory()->create();
    $milestone = $task->milestone;

    $board = Livewire::actingAs($milestone->subject->user)
        ->test(MilestonePage::class, ['subject' => $milestone->subject, 'milestone' => $milestone]);

    StudySession::factory()->forTask($task)->create(['minutes' => 15]);

    $board->dispatch('session-logged')->assertSeeText('15 min');
});

it('counts days since last studied in the display timezone', function (string $startedAt, int $days) {
    config(['app.display_timezone' => 'Europe/Sofia']);

    // 00:30 on 24 October in Sofia
    $this->travelTo('2026-10-23 21:30:00');

    $subject = Subject::factory()->create();
    StudySession::factory()->forSubject($subject)->create(['started_at' => $startedAt]);

    expect(studiedSubject($subject)->days_since_studied)->toBe($days);
})->with([
    'just after local midnight' => ['2026-10-23 21:05:00', 0],
    'just before local midnight' => ['2026-10-23 20:55:00', 1],
    'the day before' => ['2026-10-22 08:00:00', 2],
    'a week ago' => ['2026-10-17 12:00:00', 7],
]);

it('has no days since studied without sessions', function () {
    expect(studiedSubject(Subject::factory()->create())->days_since_studied)->toBeNull();
});

it('counts a studied subject as started', function () {
    $subject = Subject::factory()->create();
    Task::factory()->for(Milestone::factory()->for($subject))->create();
    StudySession::factory()->forSubject($subject)->create();

    expect(studiedSubject($subject)->is_started)->toBeTrue();
});

it('labels library cards with when the subject was last studied', function () {
    config(['app.display_timezone' => 'Europe/Sofia']);
    $this->travelTo('2026-10-23 10:00:00');

    $user = User::factory()->create();
    StudySession::factory()->forSubject(Subject::factory()->for($user)->create())->create(['started_at' => now()]);
    StudySession::factory()->forSubject(Subject::factory()->for($user)->create())->create(['started_at' => now()->subDay()]);
    StudySession::factory()->forSubject(Subject::factory()->for($user)->create())->create(['started_at' => now()->subDays(3)]);
    Subject::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('Studied today')
        ->assertSee('Yesterday')
        ->assertSee('3 days ago')
        ->assertSee('Not started');
});

it('loads last studied without a query per subject', function () {
    $one = User::factory()->create();
    $three = User::factory()->create();

    foreach ([[$one, 1], [$three, 3]] as [$user, $count]) {
        Subject::factory()->count($count)->for($user)->create()
            ->each(fn (Subject $subject) => StudySession::factory()->forSubject($subject)->count(2)->create());
    }

    DB::enableQueryLog();

    Livewire::actingAs($one)->test(Index::class);
    $queriesForOne = count(DB::getQueryLog());

    DB::flushQueryLog();

    Livewire::actingAs($three)->test(Index::class);

    expect(count(DB::getQueryLog()))->toBe($queriesForOne);
});

it('loads task minutes without a query per task', function () {
    $one = Milestone::factory()->create();
    $three = Milestone::factory()->create();
    StudySession::factory()->forTask(Task::factory()->for($one)->create())->create();
    Task::factory()->count(3)->for($three)->create()
        ->each(fn (Task $task) => StudySession::factory()->forTask($task)->create());

    DB::enableQueryLog();

    Livewire::actingAs($one->subject->user)->test(MilestonePage::class, ['subject' => $one->subject, 'milestone' => $one]);
    $queriesForOne = count(DB::getQueryLog());

    DB::flushQueryLog();

    Livewire::actingAs($three->subject->user)->test(MilestonePage::class, ['subject' => $three->subject, 'milestone' => $three]);

    expect(count(DB::getQueryLog()))->toBe($queriesForOne);
});
