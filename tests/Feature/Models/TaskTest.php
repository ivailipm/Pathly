<?php

use App\Models\Task;

it('shows the url without scheme, www and trailing slash', function (string $url, string $shown) {
    expect(Task::factory()->make(['url' => $url])->display_url)->toBe($shown);
})->with([
    ['https://www.laravel.com/docs/reverb/', 'laravel.com/docs/reverb'],
    ['http://github.com/laravel/echo', 'github.com/laravel/echo'],
    ['HTTPS://WWW.Laracasts.com', 'Laracasts.com'],
    ['https://wwwexample.com/', 'wwwexample.com'],
]);

it('has no display url without a url', function () {
    expect(Task::factory()->make(['url' => null])->display_url)->toBeNull();
});
