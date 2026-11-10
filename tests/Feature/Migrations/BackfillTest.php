<?php

use App\Models\Milestone;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The migration object from database/migrations/*_<name>.php.
 */
function migration(string $name): object
{
    $files = glob(database_path("migrations/*_{$name}.php")) ?: [];

    return require $files[0];
}

it('backfills subject colours by id', function () {
    $migration = migration('add_properties_to_subjects_table');
    $migration->down();

    $user = User::factory()->create();
    $ids = collect(range(1, 5))->map(fn (int $i) => DB::table('subjects')->insertGetId([
        'user_id' => $user->id,
        'title' => "Subject {$i}",
        'created_at' => now(),
        'updated_at' => now(),
    ]));

    $migration->up();

    $colours = DB::table('subjects')->pluck('colour', 'id');

    foreach ($ids as $id) {
        expect($colours[$id])->toBe(['clay', 'slate', 'moss', 'plum'][($id - 1) % 4]);
    }

    expect(DB::table('subjects')->pluck('status')->unique()->values()->all())->toBe(['active']);
});

it('backfills completed_at for milestones that are already done', function () {
    $migration = migration('add_completed_at_to_milestones_table');
    $migration->down();

    $finished = Milestone::factory()->create();
    Task::factory()->for($finished)->done()->create(['updated_at' => '2026-10-07 09:00:00']);
    Task::factory()->for($finished)->done()->create(['updated_at' => '2026-10-08 10:30:00']);

    $mixed = Milestone::factory()->create();
    Task::factory()->for($mixed)->done()->create();
    Task::factory()->for($mixed)->inProgress()->create();

    $empty = Milestone::factory()->create();

    $migration->up();

    expect($finished->fresh()?->completed_at?->toDateTimeString())->toBe('2026-10-08 10:30:00')
        ->and($mixed->fresh()?->completed_at)->toBeNull()
        ->and($empty->fresh()?->completed_at)->toBeNull();
});
