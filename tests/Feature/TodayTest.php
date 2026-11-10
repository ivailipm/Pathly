<?php

use App\Livewire\Sessions\LogSession;
use App\Livewire\Today;
use App\Models\Milestone;
use App\Models\Note;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    config(['app.display_timezone' => 'Europe/Sofia']);

    // Friday 23 October 2026, 13:00 in Sofia (UTC+3)
    $this->travelTo('2026-10-23 10:00:00');
});

function todayPage(User $user): Testable
{
    return Livewire::actingAs($user)->test(Today::class);
}

/**
 * A completed session for the user on the subject, at a UTC time.
 */
function studied(Subject $subject, string $startedAt, int $minutes = 30): StudySession
{
    return StudySession::factory()->forSubject($subject)->create(['started_at' => $startedAt, 'minutes' => $minutes]);
}

it('renders at /dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeLivewire(Today::class)
        ->assertSeeLivewire(LogSession::class);
});

it('greets by the time of day', function (string $localTime, string $greeting) {
    $this->travelTo(CarbonImmutable::parse("2026-10-23 {$localTime}", 'Europe/Sofia'));

    todayPage(User::factory()->create())->assertSet('greeting', $greeting);
})->with([
    ['04:59', 'Good evening'],
    ['05:00', 'Good morning'],
    ['11:59', 'Good morning'],
    ['12:00', 'Good afternoon'],
    ['17:59', 'Good afternoon'],
    ['18:00', 'Good evening'],
]);

it('uses the first name', function () {
    todayPage(User::factory()->create(['name' => 'Ivayla Pernikova']))
        ->assertSet('firstName', 'Ivayla')
        ->assertSee('Good afternoon')
        ->assertSeeHtml('<em>Ivayla.</em>');
});

it('shows the local date and the ISO week', function () {
    todayPage(User::factory()->create())
        ->assertSet('weekNumber', 43)
        ->assertSee('Friday, 23 October 2026')
        ->assertSee('Week 43');
});

it('counts the streak across local midnight', function () {
    $subject = Subject::factory()->create();
    studied($subject, '2026-10-22 21:30:00'); // 00:30 on the 23rd in Sofia: today
    studied($subject, '2026-10-22 08:00:00'); // the 22nd
    studied($subject, '2026-10-20 21:30:00'); // 00:30 on the 21st
    studied($subject, '2026-10-19 12:00:00'); // the 19th: after a gap

    todayPage($subject->user)
        ->assertSet('streak', 3)
        ->assertSet('streakIncludesToday', true)
        ->assertSee('Day 3 of your streak');
});

it('asks to study today to keep a streak that ended yesterday', function () {
    $subject = Subject::factory()->create();
    studied($subject, '2026-10-22 08:00:00');
    studied($subject, '2026-10-21 08:00:00');

    todayPage($subject->user)
        ->assertSet('streak', 2)
        ->assertSet('streakIncludesToday', false)
        ->assertSee('2-day streak, study today to keep it');
});

it('ignores running sessions in the streak', function () {
    $subject = Subject::factory()->create();
    StudySession::factory()->forSubject($subject)->running()->create(['started_at' => now()]);

    todayPage($subject->user)->assertSet('streak', 0)->assertDontSee('streak');
});

it('picks the subject of the latest session for now studying', function () {
    $user = User::factory()->create();
    $older = Subject::factory()->for($user)->create(['title' => 'Laravel']);
    $current = Milestone::factory()->for($older)->create(['title' => 'Real-time with Reverb']);
    Task::factory()->for($current)->done()->create();
    Task::factory()->for($current)->inProgress()->create();
    Task::factory()->for($current)->create();
    $newer = Subject::factory()->for($user)->create();
    Milestone::factory()->for($newer)->create();

    studied($older, '2026-10-23 08:00:00');
    studied($newer, '2026-10-22 08:00:00');

    $now = todayPage($user)->instance()->nowStudying;

    expect($now['subject']->id)->toBe($older->id)
        ->and($now['milestone']->id)->toBe($current->id)
        ->and($now['milestone']->number)->toBe(1)
        ->and([$now['done'], $now['in_progress'], $now['total']])->toBe([1, 1, 3]);

    todayPage($user)
        ->assertSee('Now studying · Laravel')
        ->assertSee('Real-time with Reverb')
        ->assertSee('1 / 3 done')
        ->assertSet('remainingTasks', 2);
});

it('falls back to the newest active subject with a current milestone', function () {
    $user = User::factory()->create();
    $withMilestone = Subject::factory()->for($user)->create();
    $milestone = Milestone::factory()->for($withMilestone)->create();
    $finished = Subject::factory()->for($user)->create();
    Milestone::factory()->for($finished)->completed()->create();
    Subject::factory()->for($user)->create();

    // The latest session is on a subject with nothing left to study
    studied($finished, '2026-10-23 08:00:00');

    expect(todayPage($user)->instance()->nowStudying['milestone']->id)->toBe($milestone->id);
});

it('skips paused and archived subjects for now studying and the desk', function () {
    $user = User::factory()->create();

    foreach (['paused', 'archived'] as $state) {
        $subject = Subject::factory()->for($user)->{$state}()->create();
        Task::factory()->for(Milestone::factory()->for($subject))->inProgress()->create(['title' => "{$state} task"]);
        studied($subject, '2026-10-23 08:00:00');
    }

    todayPage($user)
        ->assertSet('nowStudying', null)
        ->assertSet('deskCount', 0)
        ->assertDontSee('paused task')
        ->assertDontSee('archived task');
});

it('picks up where you left off with the up next task', function () {
    $user = User::factory()->create();
    $milestone = Milestone::factory()->for(Subject::factory()->for($user))->create();
    Task::factory()->for($milestone)->create(['position' => 1, 'title' => 'First to do']);
    $started = Task::factory()->for($milestone)->inProgress()->create(['position' => 2, 'title' => 'Presence channel', 'type' => 'exercise']);
    StudySession::factory()->forTask($started)->create(['minutes' => 50, 'started_at' => '2026-10-22 08:00:00']);

    $page = todayPage($user);

    expect($page->instance()->upNext?->id)->toBe($started->id);

    $page->assertSeeInOrder(['Up next', '· Exercise', '· ', '50 min', 'logged'])
        ->assertSee('Presence channel')
        ->assertSee('Continue')
        ->assertSee('Pick up where you left off.');
});

it('invites adding tasks to an empty current milestone', function () {
    $user = User::factory()->create();
    Milestone::factory()->for(Subject::factory()->for($user))->create(['title' => 'Scales and axes']);

    todayPage($user)
        ->assertSet('upNext', null)
        ->assertSeeHtml('Nothing is on your desk yet, and <em>Scales and axes</em> has no tasks yet. Pick a task to start.')
        ->assertSee('No tasks yet. Add some to this milestone.');
});

it('describes the desk and the milestone in the summary', function () {
    $user = User::factory()->create();
    $milestone = Milestone::factory()->for(Subject::factory()->for($user))->create(['title' => 'Reverb']);
    Task::factory()->count(2)->for($milestone)->inProgress()->create();
    Task::factory()->for($milestone)->create();

    todayPage($user)->assertSeeHtml('2 tasks are on your desk, and <em>Reverb</em> is 3 steps from done. Pick up where you left off.');
});

it('says when the milestone is ready to finish', function () {
    $user = User::factory()->create();
    $subject = Subject::factory()->for($user)->create();
    // All done but not completed: possible for data from before completion dates existed
    $milestone = Milestone::factory()->for($subject)->create(['title' => 'Wrap up']);
    Task::factory()->for($milestone)->done()->create();

    todayPage($user)->assertSeeHtml('Nothing is on your desk yet, and <em>Wrap up</em> is ready to finish.');
});

it('caps the desk at five but counts all in-progress tasks', function () {
    $user = User::factory()->create();
    $milestone = Milestone::factory()->for(Subject::factory()->for($user))->create();

    foreach (range(1, 7) as $i) {
        Task::factory()->for($milestone)->inProgress()->create([
            'title' => "Desk task {$i}",
            'updated_at' => now()->subMinutes(10 - $i),
            'url' => null,
        ]);
    }
    Task::factory()->inProgress()->create(['title' => 'Someone else\'s']);

    $page = todayPage($user)->assertSet('deskCount', 7);

    expect($page->instance()->desk->pluck('title')->all())
        ->toBe(['Desk task 7', 'Desk task 6', 'Desk task 5', 'Desk task 4', 'Desk task 3']);

    $page->assertSee('and 2 more in progress')->assertDontSee('Someone else');
});

it('shows each desk task\'s link, else its subject and type', function () {
    $user = User::factory()->create();
    $subject = Subject::factory()->for($user)->create(['title' => 'Spanish']);
    $milestone = Milestone::factory()->for($subject)->create();
    Task::factory()->for($milestone)->inProgress()->create(['url' => 'https://github.com/laravel/echo']);
    Task::factory()->for($milestone)->inProgress()->create(['url' => null, 'type' => 'exercise']);

    // assertSeeText: Livewire marks @if blocks with HTML comments, which split the raw text
    todayPage($user)->assertSee('github.com/laravel/echo')->assertSeeText('Spanish · Exercise');
});

it('sums this week\'s minutes per local day', function () {
    $subject = Subject::factory()->create();
    studied($subject, '2026-10-19 08:00:00', 45); // Monday
    studied($subject, '2026-10-20 08:00:00', 50); // Tuesday
    studied($subject, '2026-10-20 15:00:00', 30); // Tuesday
    studied($subject, '2026-10-22 21:30:00', 25); // 00:30 on Friday in Sofia
    studied($subject, '2026-10-18 20:30:00', 99); // 23:30 on Sunday the 18th: last week
    StudySession::factory()->forSubject($subject)->running()->create(['started_at' => now()]);

    $page = todayPage($subject->user);

    expect(array_column($page->instance()->week, 'minutes'))->toBe([45, 80, 0, 0, 25, 0, 0]);

    $page->assertSet('weekMinutes', 150)
        ->assertSeeText('2 h 30 min')
        ->assertSeeHtml('aria-label="Minutes studied: Mon 45, Tue 80, Wed 0, Thu 0, Fri 25 so far"');
});

it('counts paused and archived subjects in the week and the streak', function () {
    $subject = Subject::factory()->archived()->create();
    studied($subject, '2026-10-23 08:00:00', 40);

    todayPage($subject->user)->assertSet('weekMinutes', 40)->assertSet('streak', 1);
});

it('finds the latest note', function () {
    $user = User::factory()->create();
    $milestone = Milestone::factory()->for(Subject::factory()->for($user))->create(['title' => 'Real-time with Reverb']);
    Note::factory()->for($milestone)->create(['body' => 'Older', 'created_at' => '2026-10-19 10:00:00']);
    Note::factory()->for($milestone)->create(['body' => 'Broadcasting clicked.', 'created_at' => '2026-10-20 10:00:00']);
    Note::factory()->create(['body' => 'Someone else', 'created_at' => '2026-10-22 10:00:00']);

    todayPage($user)
        ->assertSee('From your field notes')
        ->assertSee('“Broadcasting clicked.”', escape: false)
        ->assertSee('Tue 20 Oct')
        ->assertDontSee('Someone else');
});

it('lists the non-archived subjects in the table', function () {
    $user = User::factory()->create();
    Subject::factory()->for($user)->create(['title' => 'Laravel', 'target_date' => '2026-12-15']);
    Subject::factory()->for($user)->paused()->create(['title' => 'Spanish']);
    Subject::factory()->for($user)->archived()->create(['title' => 'Old one']);

    todayPage($user)
        ->assertSee('Across your subjects')
        ->assertSee('Laravel')
        ->assertSee('Spanish')
        ->assertSee('15 Dec')
        ->assertDontSee('Old one');
});

it('works for a brand-new user', function () {
    $page = todayPage(User::factory()->create());

    $page->assertSet('streak', 0)
        ->assertSet('nowStudying', null)
        ->assertSet('upNext', null)
        ->assertSet('remainingTasks', 0)
        ->assertSet('deskCount', 0)
        ->assertSet('weekMinutes', 0)
        ->assertSet('latestNote', null);

    expect($page->instance()->desk)->toBeEmpty()
        ->and($page->instance()->subjects)->toBeEmpty();

    $page->assertSee('Nothing is on your desk yet. Pick a task to start.')
        ->assertSee('Start a subject in your library.')
        ->assertSee('Nothing in progress.')
        ->assertSee('No subjects yet.')
        ->assertDontSee('From your field notes')
        ->assertDontSee('streak');
});

it('refreshes after a session is logged', function () {
    $subject = Subject::factory()->create();
    $page = todayPage($subject->user)->assertSet('weekMinutes', 0);

    studied($subject, '2026-10-23 08:00:00', 35);

    $page->dispatch('session-logged')
        ->assertSet('weekMinutes', 35)
        ->assertSet('streak', 1);
});

it('loads the desk and the subjects without a query per row', function () {
    $one = User::factory()->create();
    $three = User::factory()->create();

    foreach ([[$one, 1], [$three, 3]] as [$user, $count]) {
        foreach (range(1, $count) as $i) {
            $milestone = Milestone::factory()->for(Subject::factory()->for($user))->create();
            Task::factory()->for($milestone)->inProgress()->create();
            Note::factory()->for($milestone)->create();
            studied($milestone->subject, '2026-10-22 08:00:00');
        }
    }

    DB::enableQueryLog();

    todayPage($one);
    $queriesForOne = count(DB::getQueryLog());

    DB::flushQueryLog();

    todayPage($three);

    expect(count(DB::getQueryLog()))->toBe($queriesForOne);
});
