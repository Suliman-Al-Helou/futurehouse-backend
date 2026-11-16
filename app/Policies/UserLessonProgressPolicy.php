<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\UserLessonProgress;

class UserLessonProgressPolicy
{
    /**
     * Determine whether the user can view the progress record.
     */
    public function view(User $user, UserLessonProgress $progress): bool
    {
        return $user->id === $progress->user_id;
    }

    /**
     * Determine whether the user can update the progress record.
     */
    public function update(User $user, UserLessonProgress $progress): bool
    {
        return $user->id === $progress->user_id;
    }
}
