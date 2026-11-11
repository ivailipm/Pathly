<?php

use App\Models\Milestone;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    // phpunit.xml broadcasts to `null`, which accepts every subscription. Switch to Reverb (no server is
    // contacted: authorizing only signs a response) and register the channels on the new driver.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    app(BroadcastManager::class)->forgetDrivers();

    require base_path('routes/channels.php');
});

function authorizeChannel(User $user, string $channel): TestResponse
{
    return test()->actingAs($user)->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => $channel,
    ]);
}

it('lets the owner join the milestone channel', function () {
    $milestone = Milestone::factory()->create();

    authorizeChannel($milestone->subject->user, 'private-milestones.'.$milestone->id)
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('keeps other users out of the milestone channel', function () {
    $milestone = Milestone::factory()->create();

    authorizeChannel(User::factory()->create(), 'private-milestones.'.$milestone->id)->assertForbidden();
});

it('keeps everyone out of a milestone that does not exist', function () {
    authorizeChannel(User::factory()->create(), 'private-milestones.999999')->assertForbidden();
});

it('needs a signed-in user', function () {
    $milestone = Milestone::factory()->create();

    $this->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-milestones.'.$milestone->id,
    ])->assertForbidden();
});
