<?php

use App\Livewire\Milestones\Show;
use App\Livewire\Notes\Index;
use App\Livewire\Subjects\Show as SubjectPage;
use App\Models\Milestone;
use App\Models\Note;
use App\Models\StudySession;
use App\Models\Task;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The milestone page with its field notes, as the owner.
 */
function notesPage(Milestone $milestone): Testable
{
    return Livewire::actingAs($milestone->subject->user)
        ->test(Show::class, ['subject' => $milestone->subject, 'milestone' => $milestone]);
}

it('adds a note to the milestone', function () {
    $milestone = Milestone::factory()->create();

    notesPage($milestone)
        ->set('noteForm.body', 'Broadcasting clicked once I drew it on paper.')
        ->set('noteForm.task_id', null)
        ->call('addNote')
        ->assertHasNoErrors()
        ->assertSet('noteForm.body', '')
        ->assertSee('Broadcasting clicked once I drew it on paper.');

    $note = Note::sole();

    expect($note->milestone_id)->toBe($milestone->id)
        ->and($note->task_id)->toBeNull();
});

it('links a note to a task of the milestone', function () {
    $task = Task::factory()->create(['title' => 'Private channels']);

    notesPage($task->milestone)
        ->set('noteForm.body', 'Auth runs through the channel callback.')
        ->set('noteForm.task_id', $task->id)
        ->call('addNote')
        ->assertHasNoErrors()
        ->assertSeeInOrder(['Auth runs through the channel callback.', 'On', 'Private channels']);

    expect(Note::sole()->task_id)->toBe($task->id);
});

it('rejects a task from another milestone with 404', function () {
    $milestone = Milestone::factory()->create();
    $elsewhere = Task::factory()->for(Milestone::factory()->for($milestone->subject))->create();

    notesPage($milestone)
        ->set('noteForm.body', 'Wrong place')
        ->set('noteForm.task_id', $elsewhere->id)
        ->call('addNote')
        ->assertNotFound();

    expect(Note::count())->toBe(0);
});

it('requires a body of at most 2000 characters', function () {
    $milestone = Milestone::factory()->create();

    notesPage($milestone)
        ->set('noteForm.body', '')
        ->call('addNote')
        ->assertHasErrors(['noteForm.body' => 'required'])
        ->set('noteForm.body', str_repeat('a', 2001))
        ->call('addNote')
        ->assertHasErrors(['noteForm.body' => 'max']);

    expect(Note::count())->toBe(0);
});

it('defaults the linked task to the running timer, then up next', function () {
    $milestone = Milestone::factory()->create();
    $upNext = Task::factory()->for($milestone)->inProgress()->create(['position' => 1]);
    $timed = Task::factory()->for($milestone)->create(['position' => 2]);

    notesPage($milestone)->assertSet('noteForm.task_id', $upNext->id);

    StudySession::factory()->forTask($timed)->running()->create();

    notesPage($milestone)
        ->assertSet('noteForm.task_id', $timed->id)
        ->set('noteForm.task_id', $upNext->id)
        ->set('noteForm.body', 'A note')
        ->call('addNote')
        ->assertSet('noteForm.task_id', $timed->id);
});

it('has no default task on an empty milestone', function () {
    notesPage(Milestone::factory()->create())->assertSet('noteForm.task_id', null);
});

it('authorizes adding notes', function () {
    $milestone = Milestone::factory()->create();
    $page = notesPage($milestone)->set('noteForm.body', 'Hijacked');

    $this->actingAs(User::factory()->create());

    $page->call('addNote')->assertNotFound();

    expect(Note::count())->toBe(0);
});

it('lists the milestone\'s notes newest first', function () {
    $milestone = Milestone::factory()->create();
    Note::factory()->for($milestone)->create(['body' => 'Older note', 'created_at' => now()->subDays(2)]);
    Note::factory()->for($milestone)->create(['body' => 'Newer note', 'created_at' => now()->subDay()]);
    Note::factory()->create(['body' => 'Another milestone']);

    notesPage($milestone)
        ->assertSeeInOrder(['Newer note', 'Older note'])
        ->assertDontSee('Another milestone');
});

it('shows a new note on the milestone, in the subject\'s margins and on the field notes page', function () {
    $milestone = Milestone::factory()->create();
    Note::factory()->for($milestone)->create(['body' => 'Yesterday\'s note', 'created_at' => now()->subDay()]);

    notesPage($milestone)
        ->set('noteForm.body', 'Fresh insight')
        ->call('addNote')
        ->assertSeeInOrder(['Fresh insight', 'Yesterday\'s note']);

    Livewire::actingAs($milestone->subject->user)
        ->test(SubjectPage::class, ['subject' => $milestone->subject])
        ->assertSee('In the margins')
        ->assertSeeInOrder(['Fresh insight', 'Yesterday\'s note']);

    Livewire::actingAs($milestone->subject->user)
        ->test(Index::class)
        ->assertSeeInOrder(['Fresh insight', 'Yesterday\'s note']);
});

it('keeps notes when their task is deleted', function () {
    $task = Task::factory()->create();
    $note = Note::factory()->forTask($task)->create();

    $task->delete();

    expect($note->fresh()?->task_id)->toBeNull();
});
