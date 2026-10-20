<?php

use App\Livewire\Subjects\Show;
use App\Models\Milestone;
use App\Models\Subject;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

it('shows the subject to its owner', function () {
    $subject = Subject::factory()->create(['title' => 'Learn Laravel']);

    $this->actingAs($subject->user)
        ->get(route('subjects.show', $subject))
        ->assertOk()
        ->assertSee('Learn Laravel');
});

it('answers 404 to other users, the same as a subject that does not exist', function () {
    $subject = Subject::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)->get(route('subjects.show', $subject))->assertNotFound();
    $this->actingAs($otherUser)->get('/subjects/999999')->assertNotFound();

    Livewire::actingAs($otherUser)
        ->test(Show::class, ['subject' => $subject])
        ->assertNotFound();
});

it('lists milestones in position order', function () {
    $subject = Subject::factory()->create();
    Milestone::factory()->for($subject)->create(['title' => 'Second step', 'position' => 2]);
    Milestone::factory()->for($subject)->create(['title' => 'First step', 'position' => 1]);

    Livewire::actingAs($subject->user)
        ->test(Show::class, ['subject' => $subject])
        ->assertSeeInOrder(['First step', 'Second step']);
});

it('adds milestones at the end', function () {
    $subject = Subject::factory()->create();

    Livewire::actingAs($subject->user)
        ->test(Show::class, ['subject' => $subject])
        ->set('milestoneForm.title', 'Basics')
        ->call('addMilestone')
        ->assertHasNoErrors()
        ->assertSet('milestoneForm.title', '')
        ->set('milestoneForm.title', 'Routing')
        ->call('addMilestone');

    expect($subject->milestones()->pluck('position', 'title')->all())
        ->toBe(['Basics' => 1, 'Routing' => 2]);
});

it('adds a milestone after the highest position when there are gaps', function () {
    $subject = Subject::factory()->create();
    Milestone::factory()->for($subject)->create(['position' => 1]);
    Milestone::factory()->for($subject)->create(['position' => 3]);

    Livewire::actingAs($subject->user)
        ->test(Show::class, ['subject' => $subject])
        ->set('milestoneForm.title', 'Next')
        ->call('addMilestone');

    expect($subject->milestones()->where('title', 'Next')->value('position'))->toBe(4);
});

it('requires a milestone title', function () {
    $subject = Subject::factory()->create();

    Livewire::actingAs($subject->user)
        ->test(Show::class, ['subject' => $subject])
        ->call('addMilestone')
        ->assertHasErrors(['milestoneForm.title' => 'required']);
});

it('lets the owner edit the subject', function () {
    $subject = Subject::factory()->create(['title' => 'Old title']);

    Livewire::actingAs($subject->user)
        ->test(Show::class, ['subject' => $subject])
        ->call('edit')
        ->assertSet('editing', true)
        ->assertSet('form.title', 'Old title')
        ->set('form.title', 'New title')
        ->call('update')
        ->assertHasNoErrors()
        ->assertSet('editing', false);

    expect($subject->fresh()->title)->toBe('New title');
});

it('validates the edit and clears errors on cancel', function () {
    $subject = Subject::factory()->create(['title' => 'Old title']);

    Livewire::actingAs($subject->user)
        ->test(Show::class, ['subject' => $subject])
        ->call('edit')
        ->set('form.title', '')
        ->call('update')
        ->assertHasErrors(['form.title' => 'required'])
        ->call('cancelEdit')
        ->assertHasNoErrors()
        ->assertSet('editing', false);

    expect($subject->fresh()->title)->toBe('Old title');
});

it('deletes the subject with its milestones and tasks', function () {
    $task = Task::factory()->create();
    $subject = $task->milestone->subject;

    Livewire::actingAs($subject->user)
        ->test(Show::class, ['subject' => $subject])
        ->call('delete')
        ->assertRedirect(route('subjects.index'));

    expect(Subject::count())->toBe(0)
        ->and(Milestone::count())->toBe(0)
        ->and(Task::count())->toBe(0);
});

it('authorizes every write action, not just the page load', function () {
    $subject = Subject::factory()->create(['title' => 'Mine']);
    $otherUser = User::factory()->create();

    $editing = Livewire::actingAs($subject->user)
        ->test(Show::class, ['subject' => $subject])
        ->call('edit')
        ->set('form.title', 'Hijacked');
    $adding = Livewire::actingAs($subject->user)
        ->test(Show::class, ['subject' => $subject])
        ->set('milestoneForm.title', 'Hijacked');

    $this->actingAs($otherUser);

    $editing->call('update')->assertNotFound();
    $adding->call('addMilestone')->assertNotFound();

    expect($subject->fresh()->title)->toBe('Mine')
        ->and($subject->milestones()->count())->toBe(0);
});

it('shows an empty state when the subject has no milestones', function () {
    $subject = Subject::factory()->create();

    Livewire::actingAs($subject->user)
        ->test(Show::class, ['subject' => $subject])
        ->assertSee('No milestones yet.');
});

it('shows the progress of each milestone and of the whole subject', function () {
    $subject = Subject::factory()->create();
    $basics = Milestone::factory()->for($subject)->create(['position' => 1]);
    $routing = Milestone::factory()->for($subject)->create(['position' => 2]);
    Task::factory()->for($basics)->done()->create();
    Task::factory()->for($basics)->inProgress()->create();
    Task::factory()->for($routing)->done()->create();

    Livewire::actingAs($subject->user)
        ->test(Show::class, ['subject' => $subject])
        ->assertSeeInOrder(['2 of 3 tasks done', '1 of 2 tasks done', '1 of 1 task done']);
});
