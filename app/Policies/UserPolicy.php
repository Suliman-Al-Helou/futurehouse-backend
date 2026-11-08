<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can access administrative operations.
     */
    public function manageAdmin(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the user model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can update the user model.
     * Prevents students from modifying other users' profiles.
     */
    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the user model.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }
}
