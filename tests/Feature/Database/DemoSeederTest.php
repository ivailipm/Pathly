<?php

use App\Jobs\SendNudges;
use App\Livewire\Subjects\Index;
use App\Livewire\Today;
use App\Models\Milestone;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\FinishWhatYouStart;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    config(['app.display_timezone' => 'Europe/Sofia', 'app.nudge_after_days' => 3]);

    // Friday 23 October 2026, 13:00 in Sofia
    $this->travelTo('2026-10-23 10:00:00');

    $this->seed(DatabaseSeeder::class);
    $this->user = User::query()->sole();
});

it('creates the demo user', function () {
    expect($this->user->email)->toBe('test@example.com')
        ->and(Hash::check('password', $this->user->password))->toBeTrue()
        ->and($this->user->email_verified_at)->not->toBeNull();
});

it('builds the library from the mockups', function () {
    Livewire::actingAs($this->user)
        ->test(Index::class)
        ->assertSeeInOrder(['4 subjects', '23 milestones'])
        ->assertSeeInOrder(['Laravel &amp; Livewire', 'Machine learning foundations', 'Spanish to B2', 'Data visualisation with D3'], false);

    $subjects = $this->user->subjects()->withLibraryData()->get()->keyBy('title');

    expect($subjects['Laravel & Livewire']->current_milestone?->title)->toBe('Real-time with Reverb')
        ->and($subjects['Laravel & Livewire']->current_milestone?->done_tasks_count)->toBe(4)
        ->and($subjects['Laravel & Livewire']->done_milestones_count)->toBe(3)
        ->and($subjects['Spanish to B2']->done_milestones_count)->toBe(4)
        ->and($subjects['Data visualisation with D3']->is_started)->toBeFalse();
});

it('records sessions on the subject of their task', function () {
    $sessions = StudySession::query()->with('task.milestone')->get();

    expect($sessions)->toHaveCount(28)
        ->and($sessions->every(fn (StudySession $session) => $session->task?->milestone->subject_id === $session->subject_id))->toBeTrue()
        ->and($sessions->every(fn (StudySession $session) => $session->started_at->lessThan(now())))->toBeTrue();
});

it('shows a five-day streak and the Reverb milestone on Today', function () {
    Livewire::actingAs($this->user)
        ->test(Today::class)
        ->assertSeeText('Day 5 of your streak')
        ->assertSeeText('Real-time with Reverb')
        ->assertSeeText('Build a presence channel for live cursors')
        ->assertSeeText('Broadcasting clicked once I drew the flow on paper');
});

it('leaves one reminder about the quiet subject, and the daily job does not send it again', function () {
    $reminder = $this->user->unreadNotifications()->sole();

    expect($reminder->type)->toBe(FinishWhatYouStart::class)
        ->and($reminder->data['subjects'])->toBe([[
            'id' => Subject::query()->where('title', 'Machine learning foundations')->value('id'),
            'title' => 'Machine learning foundations',
            'days' => 4,
            'milestone' => 'Linear models',
        ]]);

    Notification::fake();
    (new SendNudges)->handle();
    Notification::assertNothingSent();
});

it('renders every page of the demo without errors', function () {
    $subject = Subject::query()->where('title', 'Laravel & Livewire')->sole();
    $milestone = Milestone::query()->where('title', 'Real-time with Reverb')->sole();

    $this->actingAs($this->user);

    $this->get(route('dashboard'))->assertOk();
    $this->get(route('subjects.index'))->assertOk();
    $this->get(route('notes.index'))->assertOk();
    $this->get(route('subjects.show', $subject))->assertOk();
    $this->get(route('milestones.show', [$subject, $milestone]))->assertOk();
});
