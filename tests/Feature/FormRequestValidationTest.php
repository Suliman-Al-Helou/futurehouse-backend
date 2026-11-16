<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FormRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Course $course;

    private Section $section;

    private Instructor $instructor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        Sanctum::actingAs($this->admin);

        $this->instructor = Instructor::create([
            'name' => 'Instructor Test',
            'bio' => 'Bio for testing instructor',
        ]);

        $this->course = Course::create([
            'title' => 'Validation Test Course',
            'description' => 'Detailed course description here',
            'is_public' => false,
            'status' => 'published',
            'instructor_id' => $this->instructor->id,
        ]);

        $this->section = Section::create([
            'course_id' => $this->course->id,
            'title' => 'Section 1',
            'order' => 1,
        ]);
    }

    #[DataProvider('invalidCourseDataProvider')]
    public function test_store_course_validation_rules(array $payload, string $expectedErrorField): void
    {
        $basePayload = [
            'title' => 'Valid Course Title',
            'description' => 'Valid course description that is long enough',
            'level' => 'beginner',
            'status' => 'published',
            'instructor_id' => $this->instructor->id,
        ];

        $requestData = array_merge($basePayload, $payload);

        $response = $this->postJson('/api/admin/courses', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([$expectedErrorField]);
    }

    public static function invalidCourseDataProvider(): array
    {
        return [
            'missing title' => [['title' => ''], 'title'],
            'short title' => [['title' => 'ab'], 'title'],
            'missing description' => [['description' => ''], 'description'],
            'short description' => [['description' => 'too short'], 'description'],
            'invalid level' => [['level' => 'super-expert'], 'level'],
            'invalid status' => [['status' => 'archived'], 'status'],
            'non-existent instructor' => [['instructor_id' => 999999], 'instructor_id'],
            'invalid cover_image url' => [['cover_image' => 'not-a-valid-url'], 'cover_image'],
            'negative price' => [['price' => -10], 'price'],
            'rating greater than 5' => [['rating' => 5.5], 'rating'],
        ];
    }

    #[DataProvider('invalidZoomMeetingDataProvider')]
    public function test_zoom_meeting_validation_rules(array $payload, string $expectedErrorField): void
    {
        $basePayload = [
            'course_id' => $this->course->id,
            'title' => 'Valid Zoom Meeting',
            'zoom_link' => 'https://zoom.us/j/1234567890',
            'starts_at' => '2026-10-10 12:00:00',
            'duration_minutes' => 60,
        ];

        $requestData = array_merge($basePayload, $payload);

        $response = $this->postJson('/api/admin/zoom-meetings', $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([$expectedErrorField]);
    }

    public static function invalidZoomMeetingDataProvider(): array
    {
        return [
            'missing course_id' => [['course_id' => null], 'course_id'],
            'non-existent course_id' => [['course_id' => 999999], 'course_id'],
            'missing title' => [['title' => ''], 'title'],
            'missing zoom_link' => [['zoom_link' => ''], 'zoom_link'],
            'invalid zoom_link url' => [['zoom_link' => 'not-a-url'], 'zoom_link'],
            'missing starts_at' => [['starts_at' => ''], 'starts_at'],
            'invalid starts_at date' => [['starts_at' => 'invalid-date-format'], 'starts_at'],
            'duration too small' => [['duration_minutes' => 10], 'duration_minutes'],
            'duration too large' => [['duration_minutes' => 500], 'duration_minutes'],
        ];
    }

    #[DataProvider('invalidLessonDataProvider')]
    public function test_lesson_validation_rules(array $payload, string $expectedErrorField): void
    {
        $basePayload = [
            'title' => 'Valid Lesson Title',
            'duration' => 600,
            'order' => 1,
            'is_preview' => false,
        ];

        $requestData = array_merge($basePayload, $payload);

        $response = $this->postJson("/api/admin/sections/{$this->section->id}/lessons", $requestData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([$expectedErrorField]);
    }

    public static function invalidLessonDataProvider(): array
    {
        return [
            'missing title' => [['title' => ''], 'title'],
            'title too long' => [['title' => str_repeat('a', 256)], 'title'],
            'duration not integer' => [['duration' => 'ten-minutes'], 'duration'],
            'order not integer' => [['order' => 'first'], 'order'],
            'is_preview not boolean' => [['is_preview' => 'maybe'], 'is_preview'],
        ];
    }
}
