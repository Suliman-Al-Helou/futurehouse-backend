<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\ZoomAttendance;
use App\Models\ZoomMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZoomAttendance>
 */
class ZoomAttendanceFactory extends Factory
{
    protected $model = ZoomAttendance::class;

    public function definition(): array
    {
        return [
            'zoom_meeting_id' => ZoomMeeting::factory(),
            'user_id' => User::factory(),
            'attended_at' => now(),
        ];
    }
}
