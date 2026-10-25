<?php

use App\Models\User;

it('no longer has an appearance page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/appearance')
        ->assertNotFound();
});
