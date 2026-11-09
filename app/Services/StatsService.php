<?php

// FILE: app/Services/StatsService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\User;

final class StatsService
{
    /** Platform-wide counters for the admin dashboard. */
    public function overview(): array
    {
        return [
            'total_students' => User::students()->count(),
            'total_courses' => Course::count(),
            'total_enrollments' => Enrollment::count(),
            'total_instructors' => Instructor::count(),
        ];
    }
}
