<?php

use App\Models\Milestone;
use App\Models\Subject;
use App\Models\Task;

/**
 * The subject as the Library loads it.
 */
function librarySubject(Subject $subject): Subject
{
    return Subject::query()->whereKey($subject->id)->withLibraryData()->sole();
}

it('uses the first not-done milestone as the current one', function () {
    $subject = Subject::factory()->create();
    $finished = Milestone::factory()->for($subject)->completed()->create(['position' => 1]);
    Task::factory()->for($finished)->done()->create();
    $started = Milestone::factory()->for($subject)->create(['position' => 2]);
    Task::factory()->for($started)->inProgress()->create();
    Milestone::factory()->for($subject)->create(['position' => 3]);

    $current = librarySubject($subject)->current_milestone;

    expect($current?->id)->toBe($started->id)
        ->and($current?->number)->toBe(2);
});

it('lets an empty milestone be the current one', function () {
    $subject = Subject::factory()->create();
    $finished = Milestone::factory()->for($subject)->completed()->create(['position' => 1]);
    Task::factory()->for($finished)->done()->create();
    $empty = Milestone::factory()->for($subject)->create(['position' => 2]);

    expect(librarySubject($subject)->current_milestone?->id)->toBe($empty->id);
});

it('has no current milestone when every milestone is done', function () {
    $subject = Subject::factory()->create();
    Task::factory()->for(Milestone::factory()->for($subject)->completed()->create(['position' => 1]))->done()->create();
    Task::factory()->for(Milestone::factory()->for($subject)->completed()->create(['position' => 2]))->done()->create();

    $loaded = librarySubject($subject);

    expect($loaded->current_milestone)->toBeNull()
        ->and($loaded->done_milestones_count)->toBe(2)
        ->and($loaded->milestones_count)->toBe(2);
});

it('has no current milestone without milestones', function () {
    $loaded = librarySubject(Subject::factory()->create());

    expect($loaded->current_milestone)->toBeNull()
        ->and($loaded->milestones_count)->toBe(0)
        ->and($loaded->done_milestones_count)->toBe(0);
});

it('is started once any task is in progress or done', function () {
    $todo = Subject::factory()->create();
    Task::factory()->for(Milestone::factory()->for($todo))->create();

    $inProgress = Subject::factory()->create();
    Task::factory()->for(Milestone::factory()->for($inProgress))->inProgress()->create();

    $done = Subject::factory()->create();
    Task::factory()->for(Milestone::factory()->for($done))->done()->create();

    expect(librarySubject($todo)->is_started)->toBeFalse()
        ->and(librarySubject($inProgress)->is_started)->toBeTrue()
        ->and(librarySubject($done)->is_started)->toBeTrue()
        ->and(librarySubject(Subject::factory()->create())->is_started)->toBeFalse();
});
