<?php

use App\Models\Subject;
use App\Models\User;
use App\View\Components\SidebarSubjects;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

it("collects the signed-in user's subjects newest first", function () {
    $user = User::factory()->create();
    $older = Subject::factory()->for($user)->create();
    $newer = Subject::factory()->for($user)->create();

    $this->actingAs($user);

    expect((new SidebarSubjects)->subjects->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

it("leaves out other users' subjects", function () {
    $user = User::factory()->create();
    $mine = Subject::factory()->for($user)->create();
    Subject::factory()->create();

    $this->actingAs($user);

    expect((new SidebarSubjects)->subjects->pluck('id')->all())->toBe([$mine->id]);
});

it('has no subjects without a signed-in user', function () {
    Subject::factory()->create();

    expect((new SidebarSubjects)->subjects)->toBeEmpty();
});

it('loads the sidebar subjects with one query', function () {
    $user = User::factory()->create();
    Subject::factory()->count(3)->for($user)->create();

    $this->actingAs($user);

    DB::enableQueryLog();

    Blade::render('<x-sidebar-subjects />');

    expect(DB::getQueryLog())->toHaveCount(1);
});

it('shows the subjects in the sidebar of every app page', function (string $routeName) {
    $user = User::factory()->create();
    $subject = Subject::factory()->for($user)->create(['title' => 'Spanish to B2']);
    Subject::factory()->create(['title' => 'Someone else']);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('Spanish to B2')
        ->assertSee(route('subjects.show', $subject))
        ->assertDontSee('Someone else');
})->with(['dashboard', 'subjects.index', 'profile.edit']);

it('marks the open subject in the sidebar', function () {
    $user = User::factory()->create();
    $open = Subject::factory()->for($user)->create();
    $other = Subject::factory()->for($user)->create();

    $html = $this->actingAs($user)->get(route('subjects.show', $open))->getContent();

    $currentLink = fn (Subject $subject) => '#<a[^>]*href="'.preg_quote(route('subjects.show', $subject), '#').'"[^>]*aria-current="page"#';

    expect($html)->toMatch($currentLink($open))
        ->not->toMatch($currentLink($other));
});

it("names the library after the user's first name", function () {
    $this->actingAs(User::factory()->create(['name' => 'Ivayla Pernikova']))
        ->get(route('dashboard'))
        ->assertSee("Ivayla's library");
});
