<?php

use App\Livewire\Subjects\Index;
use App\Models\Subject;
use App\Models\User;
use Livewire\Livewire;

it('redirects guests to the login page', function () {
    $this->get(route('subjects.index'))->assertRedirect(route('login'));
});

it('shows the subjects page to a logged in user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('subjects.index'))
        ->assertOk();
});

it('lets a user create a subject', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('form.title', 'Learn Laravel')
        ->set('form.description', 'From routes to queues')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.title', '')
        ->assertSee('Learn Laravel');

    $subject = Subject::sole();

    expect($subject->user_id)->toBe($user->id)
        ->and($subject->title)->toBe('Learn Laravel')
        ->and($subject->description)->toBe('From routes to queues');
});

it('requires a title', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->call('save')
        ->assertHasErrors(['form.title' => 'required']);

    expect(Subject::count())->toBe(0);
});

it('limits the title and description length', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->set('form.title', str_repeat('a', 256))
        ->set('form.description', str_repeat('a', 2001))
        ->call('save')
        ->assertHasErrors(['form.title' => 'max', 'form.description' => 'max']);
});

it('only shows the user their own subjects', function () {
    $user = User::factory()->create();
    Subject::factory()->for($user)->create(['title' => 'Mine']);
    Subject::factory()->create(['title' => 'Someone else']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('Mine')
        ->assertDontSee('Someone else');
});

it('lists subjects newest first', function () {
    $user = User::factory()->create();
    Subject::factory()->for($user)->create(['title' => 'Older']);
    Subject::factory()->for($user)->create(['title' => 'Newer']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSeeInOrder(['Newer', 'Older']);
});

it('shows an empty state when the user has no subjects', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->assertSee('No subjects yet. Add your first one above.');
});
