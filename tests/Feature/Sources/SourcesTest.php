<?php

use App\Livewire\Milestones\Show as MilestoneShow;
use App\Livewire\Subjects\Index;
use App\Livewire\Subjects\Sources;
use App\Models\Milestone;
use App\Models\Source;
use App\Models\Subject;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

it('lists a subject\'s sources on its page, links opening in a new tab', function () {
    $subject = Subject::factory()->create();
    Source::factory()->for($subject)->create(['title' => 'Laravel docs', 'url' => 'https://www.laravel.com/docs', 'position' => 1]);
    Source::factory()->for($subject)->withoutLink()->create(['title' => 'Laravel: Up & Running', 'position' => 2]);

    $this->actingAs($subject->user)
        ->get(route('subjects.show', $subject))
        ->assertOk()
        ->assertSeeInOrder(['Sources', 'Laravel docs', 'laravel.com', 'Laravel: Up &amp; Running', 'No link'], false)
        ->assertSee('href="https://www.laravel.com/docs"', false)
        ->assertSee('rel="noopener noreferrer"', false);
});

it('shows a hint when the subject has no sources', function () {
    $subject = Subject::factory()->create();

    Livewire::actingAs($subject->user)
        ->test(Sources::class, ['subject' => $subject])
        ->assertSee('The docs, courses and books you learn this from.');
});

it('adds a link, named after its site when no title is given', function () {
    $subject = Subject::factory()->create();
    Source::factory()->for($subject)->create(['position' => 1]);

    Livewire::actingAs($subject->user)
        ->test(Sources::class, ['subject' => $subject])
        ->call('add')
        ->set('form.url', 'https://www.Laracasts.com/series/livewire')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('adding', false)
        ->assertSee('laracasts.com');

    expect($subject->sources()->get()->last()?->only(['title', 'url', 'position']))->toBe([
        'title' => 'laracasts.com',
        'url' => 'https://www.Laracasts.com/series/livewire',
        'position' => 2,
    ]);
});

it('adds a source without a link, such as a book', function () {
    $subject = Subject::factory()->create();

    Livewire::actingAs($subject->user)
        ->test(Sources::class, ['subject' => $subject])
        ->call('add')
        ->set('form.title', '  Hands-On Machine Learning  ')
        ->call('save')
        ->assertHasNoErrors();

    expect($subject->sources()->sole()->only(['title', 'url']))->toBe(['title' => 'Hands-On Machine Learning', 'url' => null]);
});

it('validates a new source', function (array $input, string $field, string $rule) {
    $subject = Subject::factory()->create();

    Livewire::actingAs($subject->user)
        ->test(Sources::class, ['subject' => $subject])
        ->call('add')
        ->set($input)
        ->call('save')
        ->assertHasErrors([$field => $rule]);

    expect($subject->sources()->count())->toBe(0);
})->with([
    'neither a link nor a title' => [['form.url' => '', 'form.title' => ''], 'form.title', 'required'],
    'not http(s)' => [['form.url' => 'javascript:alert(1)', 'form.title' => 'x'], 'form.url', 'url'],
    'link too long' => [['form.url' => 'https://example.com/'.str_repeat('a', 2030)], 'form.url', 'max'],
    'title too long' => [['form.title' => str_repeat('a', 256)], 'form.title', 'max'],
]);

it('says what is missing when a source has neither a link nor a title', function () {
    $subject = Subject::factory()->create();

    Livewire::actingAs($subject->user)
        ->test(Sources::class, ['subject' => $subject])
        ->call('add')
        ->call('save')
        ->assertSee('Add a link or a title.');
});

it('edits a source in place', function () {
    $source = Source::factory()->create(['title' => 'Docs', 'url' => 'https://laravel.com/docs']);

    Livewire::actingAs($source->subject->user)
        ->test(Sources::class, ['subject' => $source->subject])
        ->call('edit', $source->id)
        ->assertSet('form.title', 'Docs')
        ->assertSet('form.url', 'https://laravel.com/docs')
        ->set('form.title', 'Laravel docs')
        ->set('form.url', '')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    expect($source->fresh()?->only(['title', 'url']))->toBe(['title' => 'Laravel docs', 'url' => null]);
});

it('removes a source', function () {
    $source = Source::factory()->create(['title' => 'Old course']);

    Livewire::actingAs($source->subject->user)
        ->test(Sources::class, ['subject' => $source->subject])
        ->call('remove', $source->id)
        ->assertDontSee('Old course');

    expect(Source::query()->count())->toBe(0);
});

it('keeps other users away from a subject\'s sources', function () {
    $source = Source::factory()->create();
    $intruder = User::factory()->create();

    Livewire::actingAs($intruder)
        ->test(Sources::class, ['subject' => $source->subject])
        ->assertNotFound();
});

it('only edits and removes the subject\'s own sources', function () {
    $mine = Subject::factory()->create();
    $theirs = Source::factory()->create(['title' => 'Theirs']);

    Livewire::actingAs($mine->user)->test(Sources::class, ['subject' => $mine])->call('edit', $theirs->id)->assertNotFound();
    Livewire::actingAs($mine->user)->test(Sources::class, ['subject' => $mine])->call('remove', $theirs->id)->assertNotFound();

    expect($theirs->fresh()?->title)->toBe('Theirs');
});

it('deletes the sources with their subject', function () {
    $source = Source::factory()->create();

    $source->subject->delete();

    expect(Source::query()->count())->toBe(0);
});

it('saves the sources typed when creating a subject', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('form.title', 'Laravel')
        ->set('form.sources', "Laravel docs https://laravel.com/docs\nhttps://laracasts.com\n\nLaravel: Up & Running")
        ->call('save')
        ->assertHasNoErrors();

    $sources = $user->subjects()->sole()->sources()->get();

    expect($sources->map(fn (Source $source) => $source->only(['title', 'url', 'position']))->all())->toBe([
        ['title' => 'Laravel docs', 'url' => 'https://laravel.com/docs', 'position' => 1],
        ['title' => 'laracasts.com', 'url' => 'https://laracasts.com', 'position' => 2],
        ['title' => 'Laravel: Up & Running', 'url' => null, 'position' => 3],
    ]);
});

it('creates a subject without sources', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('form.title', 'Spanish')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->subjects()->sole()->sources()->count())->toBe(0);
});

it('rejects a broken link in the new subject\'s sources and creates nothing', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('form.title', 'Laravel')
        ->set('form.sources', 'Docs https://exa..mple.com')
        ->call('save')
        ->assertHasErrors('form.sources');

    expect($user->subjects()->count())->toBe(0);
});

/**
 * A milestone with one task that has a link.
 *
 * @return array{0: Subject, 1: Milestone, 2: Task}
 */
function boardWithLink(string $url = 'https://github.com/laravel/echo'): array
{
    $milestone = Milestone::factory()->create();
    $task = Task::factory()->for($milestone)->create(['title' => 'Read: Echo client API', 'url' => $url]);

    return [$milestone->subject, $milestone, $task];
}

it('adds a task\'s link to the subject\'s sources from the board', function () {
    [$subject, $milestone, $task] = boardWithLink();

    Livewire::actingAs($subject->user)
        ->test(MilestoneShow::class, ['subject' => $subject, 'milestone' => $milestone])
        ->assertSee('Add link to sources')
        ->call('pinSource', $task->id)
        ->assertDispatched('toast-show')
        ->assertDontSee('Add link to sources');

    expect($subject->sources()->sole()->only(['title', 'url']))->toBe(['title' => 'Read: Echo client API', 'url' => 'https://github.com/laravel/echo']);
});

it('does not add a link that is already a source', function () {
    [$subject, $milestone, $task] = boardWithLink();
    Source::factory()->for($subject)->create(['url' => 'https://github.com/laravel/echo']);

    Livewire::actingAs($subject->user)
        ->test(MilestoneShow::class, ['subject' => $subject, 'milestone' => $milestone])
        ->assertDontSee('Add link to sources')
        ->call('pinSource', $task->id);

    expect($subject->sources()->count())->toBe(1);
});

it('offers nothing to add for a task without a link', function () {
    $milestone = Milestone::factory()->create();
    $task = Task::factory()->for($milestone)->create(['url' => null]);

    Livewire::actingAs($milestone->subject->user)
        ->test(MilestoneShow::class, ['subject' => $milestone->subject, 'milestone' => $milestone])
        ->assertDontSee('Add link to sources')
        ->call('pinSource', $task->id);

    expect(Source::query()->count())->toBe(0);
});

it('cannot add another milestone\'s task to the sources', function () {
    [$subject, $milestone] = boardWithLink();
    [, , $otherTask] = boardWithLink('https://example.com/other');

    Livewire::actingAs($subject->user)
        ->test(MilestoneShow::class, ['subject' => $subject, 'milestone' => $milestone])
        ->call('pinSource', $otherTask->id)
        ->assertNotFound();

    expect(Source::query()->count())->toBe(0);
});
