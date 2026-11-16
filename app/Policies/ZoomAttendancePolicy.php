<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\ZoomAttendance;

class ZoomAttendancePolicy
{
    /**
     * Determine whether the user can view the attendance record.
     */
    public function view(User $user, ZoomAttendance $attendance): bool
    {
        return $user->id === $attendance->user_id;
    }
}
