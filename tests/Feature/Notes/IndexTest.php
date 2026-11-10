<?php

use App\Livewire\Notes\Index;
use App\Models\Milestone;
use App\Models\Note;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('redirects guests to the login page', function () {
    $this->get(route('notes.index'))->assertRedirect(route('login'));
});

it('shows the field notes page with a sidebar link', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('notes.index'))
        ->assertOk()
        ->assertSeeLivewire(Index::class)
        ->assertSee(route('notes.index'));
});

it('lists the user\'s notes newest first, with their subject and milestone', function () {
    $user = User::factory()->create();
    $subject = Subject::factory()->for($user)->create(['title' => 'Laravel']);
    Milestone::factory()->for($subject)->create(['position' => 1]);
    $milestone = Milestone::factory()->for($subject)->create(['position' => 2, 'title' => 'Real-time with Reverb']);
    Note::factory()->for($milestone)->create(['body' => 'Older', 'created_at' => now()->subDays(3)]);
    Note::factory()->for($milestone)->create(['body' => 'Newer', 'created_at' => now()->subDay()]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('2 notes')
        ->assertSeeInOrder(['Newer', 'Laravel', '02 Real-time with Reverb', 'Older']);
});

it('never shows another user\'s notes', function () {
    $user = User::factory()->create();
    Note::factory()->for(Milestone::factory()->for(Subject::factory()->for($user)))->create(['body' => 'Mine']);
    Note::factory()->create(['body' => 'Someone else\'s']);

    $page = Livewire::actingAs($user)->test(Index::class);

    expect($page->instance()->notes->pluck('body')->all())->toBe(['Mine']);

    $page->assertDontSee('Someone else');
});

it('paginates by 20', function () {
    $user = User::factory()->create();
    $milestone = Milestone::factory()->for(Subject::factory()->for($user))->create();
    Note::factory()->count(21)->for($milestone)->create();

    $page = Livewire::actingAs($user)->test(Index::class);

    expect($page->instance()->notes->count())->toBe(20)
        ->and($page->instance()->notes->total())->toBe(21);

    $page->call('nextPage');

    expect($page->instance()->notes->count())->toBe(1);
});

it('says when there are no notes yet', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->assertSee('No field notes yet. Add one from a milestone\'s board.');
});

it('loads notes without a query per note', function () {
    $one = User::factory()->create();
    $three = User::factory()->create();

    // Each note on its own subject, milestone and task, with a session that day
    foreach ([[$one, 1], [$three, 3]] as [$user, $count]) {
        foreach (range(1, $count) as $i) {
            $task = Task::factory()->for(Milestone::factory()->for(Subject::factory()->for($user)))->create();
            StudySession::factory()->forTask($task)->create();
            Note::factory()->forTask($task)->create();
        }
    }

    DB::enableQueryLog();

    Livewire::actingAs($one)->test(Index::class);
    $queriesForOne = count(DB::getQueryLog());

    DB::flushQueryLog();

    Livewire::actingAs($three)->test(Index::class);

    expect(count(DB::getQueryLog()))->toBe($queriesForOne);
});
