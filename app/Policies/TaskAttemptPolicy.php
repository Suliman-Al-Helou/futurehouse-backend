<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TaskAttempt;
use App\Models\User;

class TaskAttemptPolicy
{
    /**
     * Determine whether the user can view the task attempt.
     */
    public function view(User $user, TaskAttempt $attempt): bool
    {
        return $user->id === $attempt->user_id;
    }
}
