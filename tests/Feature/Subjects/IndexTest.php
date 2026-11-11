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
    $finished = Milestone::factory()->for($subject)->completed()->create(['position' => 1]);
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
    Task::factory()->for(Milestone::factory()->for($finished)->completed())->done()->create();

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

// Step C: views, archive, recently finished, colours

it('lists non-archived subjects in gallery and table views', function (string $view) {
    $user = User::factory()->create();
    Subject::factory()->for($user)->create(['title' => 'Active one']);
    Subject::factory()->for($user)->paused()->create(['title' => 'Paused one']);
    Subject::factory()->for($user)->archived()->create(['title' => 'Archived one']);

    $page = Livewire::actingAs($user)->withQueryParams(['view' => $view])->test(Index::class);

    expect($page->instance()->subjects->pluck('title')->all())->toBe(['Paused one', 'Active one']);

    $page->assertSee('Active one')->assertSee('Paused one')->assertDontSee('Archived one');
})->with(['gallery', 'table']);

it('lists only archived subjects in the archive view', function () {
    $user = User::factory()->create();
    Subject::factory()->for($user)->create(['title' => 'Active one']);
    Subject::factory()->for($user)->archived()->create(['title' => 'Archived one']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('view', 'archive')
        ->assertSee('Archived one')
        ->assertDontSee('Active one')
        ->assertDontSee('Start a new subject');
});

it('says when the archive is empty', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->set('view', 'archive')
        ->assertSee('No archived subjects.');
});

it('falls back to the gallery for an unknown view', function () {
    Livewire::actingAs(User::factory()->create())
        ->withQueryParams(['view' => 'kanban'])
        ->test(Index::class)
        ->assertSet('view', 'gallery')
        ->set('view', 'nonsense')
        ->assertSet('view', 'gallery');
});

it('marks the selected view as pressed', function () {
    $html = Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->set('view', 'table')
        ->html();

    expect($html)->toMatch('#aria-pressed="true"[^>]*>\s*Table#')
        ->toMatch('#aria-pressed="false"[^>]*>\s*Gallery#');
});

it('shows the current milestone and target date in the table', function () {
    $user = User::factory()->create();
    $dated = Subject::factory()->for($user)->create(['title' => 'Laravel', 'target_date' => '2026-12-15']);
    Milestone::factory()->for($dated)->completed()->create(['position' => 1]);
    Milestone::factory()->for($dated)->create(['position' => 2, 'title' => 'Real-time with Reverb']);
    Subject::factory()->for($user)->create(['title' => 'Spanish']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('view', 'table')
        ->assertSee('02 · Real-time with Reverb')
        ->assertSee('15 Dec')
        ->assertSee('No date')
        ->assertSee('1/2');
});

it('lists this month\'s finished milestones newest first', function () {
    $this->travelTo('2026-10-20 12:00:00');
    $user = User::factory()->create();
    $subject = Subject::factory()->for($user)->create();
    Milestone::factory()->for($subject)->create(['title' => 'Older', 'completed_at' => '2026-10-03 09:00:00']);
    Milestone::factory()->for($subject)->create(['title' => 'Newer', 'completed_at' => '2026-10-14 09:00:00']);
    Milestone::factory()->for($subject)->create(['title' => 'Last month', 'completed_at' => '2026-09-28 09:00:00']);
    Milestone::factory()->for($subject)->create(['title' => 'Open']);
    Milestone::factory()->completed()->create(['title' => 'Someone else']);

    $page = Livewire::actingAs($user)->test(Index::class);

    expect($page->instance()->recentlyFinished->pluck('title')->all())->toBe(['Newer', 'Older']);

    $page->assertSee('Recently finished')->assertSeeInOrder(['Newer', '14 Oct', 'Older', '3 Oct']);
});

it('starts the month at local midnight', function () {
    // 1 November 01:30 in Sofia is still 31 October in UTC
    $this->travelTo('2026-11-05 12:00:00');
    $user = User::factory()->create();
    $subject = Subject::factory()->for($user)->create();
    Milestone::factory()->for($subject)->create(['title' => 'Just after midnight', 'completed_at' => '2026-10-31 23:30:00']);
    Milestone::factory()->for($subject)->create(['title' => 'Before midnight', 'completed_at' => '2026-10-31 21:30:00']);

    $finished = Livewire::actingAs($user)->test(Index::class)->instance()->recentlyFinished;

    expect($finished->pluck('title')->all())->toBe(['Just after midnight']);
});

it('leaves archived subjects out of recently finished', function () {
    $user = User::factory()->create();
    Milestone::factory()->for(Subject::factory()->for($user)->archived())->completed()->create(['title' => 'Archived work']);

    $page = Livewire::actingAs($user)->test(Index::class);

    expect($page->instance()->recentlyFinished)->toBeEmpty();

    $page->assertDontSee('Recently finished');
});

it('numbers recently finished milestones within their subject', function () {
    $user = User::factory()->create();
    $subject = Subject::factory()->for($user)->create(['title' => 'Laravel']);

    foreach (range(1, 6) as $position) {
        Milestone::factory()->for($subject)->create([
            'position' => $position,
            'completed_at' => $position === 4 ? now() : null,
        ]);
    }

    $page = Livewire::actingAs($user)->test(Index::class);

    expect($page->instance()->recentlyFinished->sole()->number)->toBe(4);

    $page->assertSee('Laravel · 04');
});

it('defaults a new subject\'s colour to the next in rotation', function () {
    $user = User::factory()->create();
    Subject::factory()->for($user)->create();
    Subject::factory()->for($user)->archived()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSet('form.colour', '#4b5320')
        ->set('form.title', 'Data viz')
        ->call('save')
        ->assertSet('form.colour', '#5b3a5b');

    expect(Subject::query()->where('title', 'Data viz')->sole()->colour)->toBe('#4b5320');
});

it('creates a subject with the chosen colour', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('form.title', 'Spanish')
        ->set('form.colour', '#5b3a5b')
        ->call('save')
        ->assertHasNoErrors();

    $subject = $user->subjects()->sole();

    expect($subject->colour)->toBe('#5b3a5b')
        ->and($subject->status->value)->toBe('active')
        ->and($subject->tags)->toBe([]);
});

it('creates a subject with any colour from the picker, stored in lowercase', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('form.title', 'Spanish')
        ->set('form.colour', '#E63946')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->subjects()->sole()->colour)->toBe('#e63946');
});

it('rejects a colour that is not #rrggbb', function (string $colour) {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->set('form.title', 'Spanish')
        ->set('form.colour', $colour)
        ->call('save')
        ->assertHasErrors(['form.colour']);
})->with(['neon', 'clay', '#fff', '#12345g', 'red; background: url(x)', '']);

it('offers the preset colours and any colour in the new subject form', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->set('creating', true)
        ->assertSeeInOrder(['Clay', 'Slate', 'Moss', 'Plum', 'Ochre', 'Teal', 'Rose', 'Stone', 'Any colour'])
        ->assertSeeHtml('type="color"');
});

it('shows a custom colour\'s tint on its swatch', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->set('creating', true)
        ->set('form.colour', '#0000ff')
        ->assertSee('Custom')
        ->assertSeeHtml('background-color: #d8e1f2; color: #2e4880');
});

it('shows paused subjects with their status on the card', function () {
    $user = User::factory()->create();
    Subject::factory()->for($user)->paused()->create();

    Livewire::actingAs($user)->test(Index::class)->assertSee('Paused');
});
