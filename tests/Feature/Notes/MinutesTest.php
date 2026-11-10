<?php

use App\Models\Note;
use App\Models\StudySession;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['app.display_timezone' => 'Europe/Sofia']);
});

/**
 * The notes with their minutes attached, by id.
 *
 * @return array<int, int>
 */
function noteMinutes(Note ...$notes): array
{
    $loaded = Note::query()->whereKey(array_map(fn (Note $note) => $note->id, $notes))->get();

    Note::attachMinutes($loaded);

    return $loaded->mapWithKeys(fn (Note $note) => [$note->id => $note->minutes])->all();
}

it('shows 0 minutes for a note without a task', function () {
    $note = Note::factory()->create();

    expect(noteMinutes($note))->toBe([$note->id => 0]);
});

it('shows the task\'s minutes on the note\'s local day', function () {
    $task = Task::factory()->create();
    $note = Note::factory()->forTask($task)->create(['created_at' => '2026-10-20 17:00:00']);
    StudySession::factory()->forTask($task)->create(['started_at' => '2026-10-20 09:00:00', 'minutes' => 30]);
    StudySession::factory()->forTask($task)->create(['started_at' => '2026-10-20 14:00:00', 'minutes' => 15]);

    expect(noteMinutes($note))->toBe([$note->id => 45]);
});

it('shows 0 minutes for a task with no sessions that day', function () {
    $task = Task::factory()->create();
    $note = Note::factory()->forTask($task)->create(['created_at' => '2026-10-20 17:00:00']);
    StudySession::factory()->forTask($task)->create(['started_at' => '2026-10-19 09:00:00', 'minutes' => 30]);
    StudySession::factory()->forTask(Task::factory()->create())->create(['started_at' => '2026-10-20 09:00:00']);

    expect(noteMinutes($note))->toBe([$note->id => 0]);
});

it('groups by the local day, not the UTC day', function () {
    $task = Task::factory()->create();
    // 23:30 UTC on the 19th is 02:30 on the 20th in Sofia
    $note = Note::factory()->forTask($task)->create(['created_at' => '2026-10-20 10:00:00']);
    StudySession::factory()->forTask($task)->create(['started_at' => '2026-10-19 23:30:00', 'minutes' => 25]);
    StudySession::factory()->forTask($task)->create(['started_at' => '2026-10-19 20:30:00', 'minutes' => 40]);

    expect(noteMinutes($note))->toBe([$note->id => 25]);
});

it('ignores running sessions', function () {
    $task = Task::factory()->create();
    $note = Note::factory()->forTask($task)->create(['created_at' => '2026-10-20 17:00:00']);
    StudySession::factory()->forTask($task)->running()->create(['started_at' => '2026-10-20 09:00:00']);

    expect(noteMinutes($note))->toBe([$note->id => 0]);
});

it('shows the day\'s total on every note of that task and day', function () {
    $task = Task::factory()->create();
    $first = Note::factory()->forTask($task)->create(['created_at' => '2026-10-20 12:00:00']);
    $second = Note::factory()->forTask($task)->create(['created_at' => '2026-10-20 18:00:00']);
    StudySession::factory()->forTask($task)->create(['started_at' => '2026-10-20 09:00:00', 'minutes' => 50]);

    expect(noteMinutes($first, $second))->toBe([$first->id => 50, $second->id => 50]);
});

it('loads note minutes with one query', function () {
    $notes = Task::factory()->count(3)->create()->map(function (Task $task) {
        StudySession::factory()->forTask($task)->create(['started_at' => now()]);

        return Note::factory()->forTask($task)->create(['created_at' => now()]);
    });
    Note::factory()->create();

    $loaded = Note::query()->get();

    DB::enableQueryLog();

    Note::attachMinutes($loaded);

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($loaded->pluck('minutes')->filter()->count())->toBe($notes->count());
});

it('runs no query when no note has a task', function () {
    Note::factory()->count(2)->create();
    $loaded = Note::query()->get();

    DB::enableQueryLog();

    Note::attachMinutes($loaded);
    Note::attachMinutes(new Collection);

    expect(DB::getQueryLog())->toHaveCount(0);
});
