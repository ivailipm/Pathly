<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Other users' subjects are denied as "not found" (404), so their ids are never revealed.
 */
class SubjectPolicy
{
    /**
     * Determine whether the user can view the subject.
     */
    public function view(User $user, Subject $subject): Response
    {
        return $this->owns($user, $subject);
    }

    /**
     * Determine whether the user can update the subject and its milestones and tasks.
     */
    public function update(User $user, Subject $subject): Response
    {
        return $this->owns($user, $subject);
    }

    /**
     * Determine whether the user can delete the subject.
     */
    public function delete(User $user, Subject $subject): Response
    {
        return $this->owns($user, $subject);
    }

    private function owns(User $user, Subject $subject): Response
    {
        return $user->id === $subject->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
