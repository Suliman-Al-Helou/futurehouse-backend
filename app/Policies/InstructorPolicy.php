<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Instructor;
use App\Models\User;

class InstructorPolicy
{
    /**
     * Determine whether the user can manage the instructor profile.
     */
    public function manage(User $user, Instructor $instructor): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $instructor->user_id !== null && $instructor->user_id === $user->id;
    }

    /**
     * Determine whether the user can view the instructor profile.
     */
    public function view(User $user, Instructor $instructor): bool
    {
        return $this->manage($user, $instructor);
    }

    /**
     * Determine whether the user can update the instructor profile.
     */
    public function update(User $user, Instructor $instructor): bool
    {
        return $this->manage($user, $instructor);
    }
}
