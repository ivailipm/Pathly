<?php

use App\Livewire\Settings\Profile;
use App\Livewire\Today;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\FinishWhatYouStart;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Store a reminder for the user about the subject, as SendNudges would.
 */
function remind(User $user, Subject $subject, int $days = 4): string
{
    return $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => FinishWhatYouStart::class,
        'data' => ['subjects' => [['id' => $subject->id, 'title' => $subject->title, 'days' => $days, 'milestone' => 'Linear models']]],
    ])->id;
}

it('shows the latest unread reminder on Today', function () {
    $subject = Subject::factory()->create(['title' => 'Machine learning']);
    remind($subject->user, $subject);

    Livewire::actingAs($subject->user)
        ->test(Today::class)
        ->assertSee('Finish what you start')
        ->assertSee('Machine learning')
        ->assertSee('has been quiet for 4 days')
        ->assertSee('Linear models')
        ->assertSee(route('subjects.show', $subject));
});

it('hides the reminder once dismissed', function () {
    $subject = Subject::factory()->create();
    $id = remind($subject->user, $subject);

    Livewire::actingAs($subject->user)
        ->test(Today::class)
        ->call('dismissNudge', $id)
        ->assertDontSee('Finish what you start');

    expect($subject->user->unreadNotifications()->count())->toBe(0);
});

it('shows nothing without an unread reminder', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Today::class)
        ->assertDontSee('Finish what you start');
});

it('cannot dismiss another user\'s reminder', function () {
    $subject = Subject::factory()->create();
    $id = remind($subject->user, $subject);

    Livewire::actingAs(User::factory()->create())
        ->test(Today::class)
        ->call('dismissNudge', $id)
        ->assertNotFound();

    expect($subject->user->unreadNotifications()->count())->toBe(1);
});

it('lets the user turn reminders off and on in profile settings', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->assertSet('wantsNudges', true)
        ->assertSee('Remind me when a started subject goes quiet')
        ->set('wantsNudges', false)
        ->assertDispatched('toast-show');

    expect($user->fresh()?->wants_nudges)->toBeFalse();

    Livewire::actingAs($user->fresh())
        ->test(Profile::class)
        ->assertSet('wantsNudges', false)
        ->set('wantsNudges', true);

    expect($user->fresh()?->wants_nudges)->toBeTrue();
});

it('does not let the profile form change the reminder setting', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('name', 'New name')
        ->call('updateProfileInformation');

    expect($user->fresh()?->wants_nudges)->toBeTrue();
});
