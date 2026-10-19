<?php

use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('lets the owner view, update and delete their subject', function () {
    $subject = Subject::factory()->create();

    expect($subject->user->can('view', $subject))->toBeTrue()
        ->and($subject->user->can('update', $subject))->toBeTrue()
        ->and($subject->user->can('delete', $subject))->toBeTrue();
});

it('denies other users as not found', function () {
    $subject = Subject::factory()->create();
    $otherUser = User::factory()->create();

    expect($otherUser->can('view', $subject))->toBeFalse()
        ->and($otherUser->can('update', $subject))->toBeFalse()
        ->and($otherUser->can('delete', $subject))->toBeFalse()
        ->and(Gate::forUser($otherUser)->inspect('view', $subject)->status())->toBe(404);
});
