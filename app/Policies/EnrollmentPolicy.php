<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    /**
     * Determine whether the user can view the enrollment record.
     */
    public function view(User $user, Enrollment $enrollment): bool
    {
        return $user->id === $enrollment->user_id;
    }

    /**
     * Determine whether the user can update the enrollment status.
     * Only admins can approve or reject enrollments.
     */
    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can cancel/delete the enrollment.
     */
    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $user->id === $enrollment->user_id;
    }
}
