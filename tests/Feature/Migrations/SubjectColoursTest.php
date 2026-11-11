<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The migration that turns named subject colours into "#rrggbb".
 */
function colourMigration(): object
{
    $files = glob(database_path('migrations/*_store_subject_colours_as_hex.php')) ?: [];

    return require $files[0];
}

/**
 * @return array<int, int> subject ids by colour name
 */
function subjectsWithColours(array $colours): array
{
    $user = User::factory()->create();

    return array_map(fn (string $colour) => DB::table('subjects')->insertGetId([
        'user_id' => $user->id,
        'title' => "A {$colour} subject",
        'colour' => $colour,
        'created_at' => now(),
        'updated_at' => now(),
    ]), $colours);
}

it('turns the four named colours into their hex colours', function () {
    $migration = colourMigration();
    $migration->down();

    $ids = subjectsWithColours(['clay', 'slate', 'moss', 'plum', 'neon']);

    $migration->up();

    expect(DB::table('subjects')->whereIn('id', $ids)->orderBy('id')->pluck('colour')->all())
        ->toBe(['#7a3b22', '#2d4b5f', '#4b5320', '#5b3a5b', '#7a3b22']);
});

it('gives new rows the default colour', function () {
    $user = User::factory()->create();

    $id = DB::table('subjects')->insertGetId(['user_id' => $user->id, 'title' => 'Default', 'created_at' => now(), 'updated_at' => now()]);

    expect(DB::table('subjects')->where('id', $id)->value('colour'))->toBe('#7a3b22');
});

it('goes back to names, with custom colours as clay', function () {
    $ids = subjectsWithColours(['#2d4b5f', '#e63946']);

    colourMigration()->down();

    expect(DB::table('subjects')->whereIn('id', $ids)->orderBy('id')->pluck('colour')->all())->toBe(['slate', 'clay']);
});
