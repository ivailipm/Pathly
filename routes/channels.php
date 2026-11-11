<?php

use App\Models\Milestone;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// A milestone's board: only the subject's owner may listen (SubjectPolicy)
Broadcast::channel('milestones.{milestone}', function (User $user, Milestone $milestone): bool {
    return $user->can('view', $milestone->subject);
});
