<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use App\Models\ZoomMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZoomMeeting>
 */
class ZoomMeetingFactory extends Factory
{
    protected $model = ZoomMeeting::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'zoom_link' => 'https://zoom.us/j/'.fake()->numerify('##########'),
            'starts_at' => now()->addDays(2),
            'duration_minutes' => 60,
        ];
    }

    public function upcoming(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->addMinutes(10),
            'duration_minutes' => 60,
        ]);
    }

    public function past(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subHours(2),
            'duration_minutes' => 60,
        ]);
    }
}
