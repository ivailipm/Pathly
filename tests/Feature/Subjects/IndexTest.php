<?php

use App\Livewire\Subjects\Index;
use App\Models\Milestone;
use App\Models\Subject;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        ->assertSee('No subjects yet. Start your first one.');
});

it('shows how many tasks of each subject are done', function () {
    $user = User::factory()->create();

    $laravel = Subject::factory()->for($user)->create();
    $basics = Milestone::factory()->for($laravel)->create(['position' => 1]);
    $routing = Milestone::factory()->for($laravel)->create(['position' => 2]);
    Task::factory()->for($basics)->done()->create();
    Task::factory()->for($basics)->inProgress()->create();
    Task::factory()->for($routing)->done()->create();

    $single = Milestone::factory()->for(Subject::factory()->for($user))->create();
    Task::factory()->for($single)->done()->create();

    Subject::factory()->for($user)->create();

    // Another user's done task must not change the counts
    Task::factory()->done()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('2 of 3 tasks done')
        ->assertSee('1 of 1 task done')
        ->assertSee('No tasks yet');
});

it('counts tasks without a query per subject', function () {
    $userWithOne = User::factory()->create();
    $userWithThree = User::factory()->create();
    Subject::factory()->for($userWithOne)->has(Milestone::factory()->has(Task::factory()))->create();
    Subject::factory()->count(3)->for($userWithThree)->has(Milestone::factory()->has(Task::factory()))->create();

    DB::enableQueryLog();

    Livewire::actingAs($userWithOne)->test(Index::class);
    $queriesForOne = count(DB::getQueryLog());

    DB::flushQueryLog();

    Livewire::actingAs($userWithThree)->test(Index::class);

    expect(count(DB::getQueryLog()))->toBe($queriesForOne);
});

// Step B: the Library

it('exposes milestone counts and the current milestone for each subject', function () {
    $user = User::factory()->create();
    $subject = Subject::factory()->for($user)->create();
    $finished = Milestone::factory()->for($subject)->create(['position' => 1]);
    Task::factory()->for($finished)->done()->create();
    $current = Milestone::factory()->for($subject)->create(['position' => 2, 'title' => 'Routing']);
    Task::factory()->for($current)->create();

    $loaded = Livewire::actingAs($user)->test(Index::class)->instance()->subjects->sole();

    expect($loaded->milestones_count)->toBe(2)
        ->and($loaded->done_milestones_count)->toBe(1)
        ->and($loaded->current_milestone?->title)->toBe('Routing')
        ->and($loaded->current_milestone?->number)->toBe(2)
        ->and($loaded->is_started)->toBeTrue();
});

it('adds up the milestones of every subject', function () {
    $user = User::factory()->create();
    Subject::factory()->for($user)->has(Milestone::factory()->count(2))->create();
    Subject::factory()->for($user)->has(Milestone::factory()->count(3))->create();
    Milestone::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSet('milestoneTotal', 5)
        ->assertSee('2 subjects')
        ->assertSee('5 milestones');
});

it('describes where each subject is on its path', function () {
    $user = User::factory()->create();

    Subject::factory()->for($user)->create();

    $finished = Subject::factory()->for($user)->create();
    Task::factory()->for(Milestone::factory()->for($finished))->done()->create();

    $started = Subject::factory()->for($user)->create();
    Task::factory()->for(Milestone::factory()->for($started)->create(['title' => 'Linear models']))->inProgress()->create();

    $fresh = Subject::factory()->for($user)->create();
    Task::factory()->for(Milestone::factory()->for($fresh)->create(['title' => 'Scales and axes']))->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('No milestones yet')
        ->assertSee('All milestones done')
        ->assertSeeInOrder(['First up', 'Scales and axes'])
        ->assertSeeInOrder(['Now on', 'Linear models']);
});

it('loads library data without a query per subject', function () {
    $userWithOne = User::factory()->create();
    $userWithThree = User::factory()->create();
    Subject::factory()->for($userWithOne)->has(Milestone::factory()->count(2)->has(Task::factory()->count(2)))->create();
    Subject::factory()->count(3)->for($userWithThree)->has(Milestone::factory()->count(2)->has(Task::factory()->count(2)))->create();

    DB::enableQueryLog();

    Livewire::actingAs($userWithOne)->test(Index::class);
    $queriesForOne = count(DB::getQueryLog());

    DB::flushQueryLog();

    Livewire::actingAs($userWithThree)->test(Index::class);

    expect(count(DB::getQueryLog()))->toBe($queriesForOne);
});

it('opens the new subject modal from ?new=1', function () {
    Livewire::actingAs(User::factory()->create())
        ->withQueryParams(['new' => 1])
        ->test(Index::class)
        ->assertSet('creating', true);
});

it('closes the modal after saving', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->set('creating', true)
        ->set('form.title', 'Spanish to B2')
        ->call('save')
        ->assertSet('creating', false);
});

it('keeps the modal open when the subject is invalid', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->set('creating', true)
        ->call('save')
        ->assertHasErrors(['form.title' => 'required'])
        ->assertSet('creating', true);
});
