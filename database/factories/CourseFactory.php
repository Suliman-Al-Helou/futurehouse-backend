<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'cover_image' => fake()->imageUrl(),
            'level' => fake()->randomElement(['beginner', 'intermediate', 'advanced']),
            'status' => 'published',
            'total_duration' => fake()->numberBetween(60, 600),
            'what_you_learn' => ['Understand core principles', 'Build practical applications'],
            'requirements' => ['Basic computer proficiency'],
            'target_audience' => ['Beginner developers', 'Intermediate engineers'],
            'rating' => fake()->randomFloat(1, 3, 5),
            'is_popular' => fake()->boolean(),
            'price' => 0.0,
            'is_public' => true,
            'instructor_id' => null,
            'students_count' => 0,
            'youtube_playlist_id' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    public function comingSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'coming_soon',
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 0,
            'is_public' => true,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 49.99,
            'is_public' => false,
        ]);
    }
}
