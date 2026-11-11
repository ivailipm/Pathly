<?php

use App\Jobs\SendNudges;
use App\Models\Milestone;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Task;
use App\Models\User;
use App\Notifications\FinishWhatYouStart;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['app.display_timezone' => 'Europe/Sofia', 'app.nudge_after_days' => 3]);

    // Friday 23 October 2026, 13:00 in Sofia
    $this->travelTo('2026-10-23 10:00:00');
});

/**
 * A started subject whose only activity is one in-progress task last touched at $touchedAt (UTC).
 */
function quietSubject(?User $user = null, string $touchedAt = '2026-10-19 10:00:00', array $attributes = []): Subject
{
    $subject = Subject::factory()->for($user ?? User::factory())->create($attributes);
    $milestone = Milestone::factory()->for($subject)->create(['title' => 'Linear models']);
    Task::factory()->for($milestone)->inProgress()->create(['updated_at' => $touchedAt]);

    return $subject;
}

function runNudges(): void
{
    (new SendNudges)->handle();
}

it('reminds about a started subject that has been quiet for three days', function () {
    Notification::fake();
    $subject = quietSubject(attributes: ['title' => 'Machine learning']);

    runNudges();

    Notification::assertSentTo($subject->user, FinishWhatYouStart::class, function (FinishWhatYouStart $notification) use ($subject) {
        return $notification->subjects === [
            ['id' => $subject->id, 'title' => 'Machine learning', 'days' => 4, 'milestone' => 'Linear models'],
        ];
    });

    expect($subject->fresh()?->nudged_at?->toDateTimeString())->toBe('2026-10-23 10:00:00');
});

it('counts quiet days in the display timezone', function (string $touchedAt, bool $reminded) {
    Notification::fake();
    $subject = quietSubject(touchedAt: $touchedAt);

    runNudges();

    $reminded
        ? Notification::assertSentTo($subject->user, FinishWhatYouStart::class)
        : Notification::assertNothingSent();
})->with([
    '23:30 on the 20th in Sofia: 3 days' => ['2026-10-20 20:30:00', true],
    '00:30 on the 21st in Sofia: 2 days' => ['2026-10-20 21:30:00', false],
    'yesterday' => ['2026-10-22 10:00:00', false],
]);

it('counts a recent study session as activity', function () {
    Notification::fake();
    $subject = quietSubject();
    StudySession::factory()->forSubject($subject)->create(['started_at' => '2026-10-22 10:00:00']);

    runNudges();

    Notification::assertNothingSent();
});

it('leaves out subjects that were never started, are finished, paused or archived', function () {
    Notification::fake();
    $user = User::factory()->create();

    $neverStarted = Subject::factory()->for($user)->create();
    Task::factory()->for(Milestone::factory()->for($neverStarted))->create(['updated_at' => '2026-10-10 10:00:00']);

    $finished = Subject::factory()->for($user)->create();
    Task::factory()->for(Milestone::factory()->for($finished)->completed())->done()->create(['updated_at' => '2026-10-10 10:00:00']);

    quietSubject($user, attributes: ['status' => 'paused']);
    quietSubject($user, attributes: ['status' => 'archived']);

    runNudges();

    Notification::assertNothingSent();
});

it('sends one reminder per user listing every quiet subject', function () {
    Notification::fake();
    $user = User::factory()->create();
    quietSubject($user, attributes: ['title' => 'Spanish']);
    quietSubject($user, attributes: ['title' => 'Data viz']);
    $other = quietSubject();

    runNudges();

    Notification::assertSentToTimes($user, FinishWhatYouStart::class, 1);
    Notification::assertSentTo($user, FinishWhatYouStart::class, fn (FinishWhatYouStart $notification) => collect($notification->subjects)->pluck('title')->sort()->values()->all() === ['Data viz', 'Spanish']);
    Notification::assertSentTo($other->user, FinishWhatYouStart::class);
});

it('reminds once per quiet spell', function () {
    Notification::fake();
    $subject = quietSubject();

    runNudges();
    $this->travel(1)->day();
    runNudges();

    Notification::assertSentToTimes($subject->user, FinishWhatYouStart::class, 1);

    // Studied again, then quiet for another three days: a new spell
    StudySession::factory()->forSubject($subject)->create(['started_at' => now()]);
    $this->travel(3)->days();
    runNudges();

    Notification::assertSentToTimes($subject->user, FinishWhatYouStart::class, 2);
});

it('respects the opt-out and unverified emails', function () {
    Notification::fake();
    $optedOut = User::factory()->create(['wants_nudges' => false]);
    $unverified = User::factory()->unverified()->create();
    quietSubject($optedOut);
    quietSubject($unverified);

    runNudges();

    Notification::assertNothingSent();
});

it('uses the configured number of quiet days', function () {
    Notification::fake();
    config(['app.nudge_after_days' => 7]);
    $subject = quietSubject();

    runNudges();
    Notification::assertNothingSent();

    $this->travel(3)->days();
    runNudges();
    Notification::assertSentTo($subject->user, FinishWhatYouStart::class);
});

it('stores the reminder and emails it', function () {
    $subject = quietSubject(attributes: ['title' => 'Machine learning']);
    $user = $subject->user;

    runNudges();

    $stored = $user->notifications()->sole();

    expect($stored->type)->toBe(FinishWhatYouStart::class)
        ->and($stored->data['subjects'][0]['title'])->toBe('Machine learning');

    $notification = new FinishWhatYouStart(collect());
    expect($notification->via($user))->toBe(['database', 'mail']);
});

it('writes the reminder email', function () {
    $subject = quietSubject(User::factory()->create(['name' => 'Ivayla Pernikova']), attributes: ['title' => 'Machine learning']);
    $loaded = Subject::query()->whereKey($subject->id)->withActivity()->get();

    $mail = (new FinishWhatYouStart($loaded))->toMail($subject->user);

    expect($mail)->toBeInstanceOf(MailMessage::class)
        ->and($mail->subject)->toBe('Finish what you start')
        ->and($mail->greeting)->toBe('Hi Ivayla,')
        ->and($mail->introLines)->toContain('Machine learning: next up is "Linear models", quiet for 4 days.')
        ->and($mail->actionUrl)->toBe(route('dashboard'));
});

it('runs daily at 09:00 local time as a queued job', function () {
    $event = collect(app(Schedule::class)->events())->firstWhere('description', 'send-nudges');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 9 * * *')
        ->and($event->timezone)->toBe('Europe/Sofia')
        ->and(new SendNudges)->toBeInstanceOf(ShouldQueue::class);
});

it('checks every subject of a user without a query per subject', function () {
    Notification::fake();
    $one = User::factory()->create();
    $three = User::factory()->create();
    quietSubject($one);
    collect(range(1, 3))->each(fn () => quietSubject($three));

    DB::enableQueryLog();
    (new SendNudges)->handle();
    $queries = count(DB::getQueryLog());

    // One user's three subjects cost the same as one subject: compare against a run with only $one
    Subject::query()->update(['nudged_at' => null]);
    $three->forceFill(['email_verified_at' => null])->save();
    DB::flushQueryLog();
    (new SendNudges)->handle();
    $queriesForOne = count(DB::getQueryLog());

    // Each notified user adds the same fixed work (the update of nudged_at), never a query per subject
    expect($queries - $queriesForOne)->toBe(1);
});
