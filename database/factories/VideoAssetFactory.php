<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use App\Models\User;
use App\Models\VideoAsset;
use App\Models\ZoomMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoAsset>
 */
class VideoAssetFactory extends Factory
{
    protected $model = VideoAsset::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'zoom_meeting_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'provider' => 'youtube',
            'provider_video_id' => fake()->regexify('[A-Za-z0-9_-]{11}'),
            'duration_seconds' => fake()->numberBetween(300, 7200),
            'type' => 'recording',
            'created_by' => User::factory(),
        ];
    }

    public function forZoomMeeting(ZoomMeeting $meeting): static
    {
        return $this->state(fn () => [
            'zoom_meeting_id' => $meeting->id,
            'course_id' => $meeting->course_id,
        ]);
    }

    public function lessonType(): static
    {
        return $this->state(fn () => [
            'type' => 'lesson',
        ]);
    }
}
