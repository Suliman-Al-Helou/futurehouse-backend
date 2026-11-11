<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RateLimiter::clear('courses-public:127.0.0.1');
        parent::tearDown();
    }

    public function test_courses_public_is_throttled_after_60_requests_per_ip(): void
    {
        $ip = '192.168.1.100';
        RateLimiter::clear("courses-public:{$ip}");

        for ($i = 0; $i < 60; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->getJson('/api/courses')
                ->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->getJson('/api/courses')
            ->assertStatus(429);
    }

    public function test_course_enroll_is_throttled_after_15_requests_per_user(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->free()->published()->create();

        Sanctum::actingAs($student);

        for ($i = 0; $i < 15; $i++) {
            $response = $this->postJson("/api/courses/{$course->id}/enroll");
            $this->assertNotEquals(429, $response->status());
        }

        $this->postJson("/api/courses/{$course->id}/enroll")
            ->assertStatus(429);
    }

    public function test_task_submit_is_throttled_after_20_requests_per_user(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->free()->published()->create();
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($student);

        for ($i = 0; $i < 20; $i++) {
            $response = $this->postJson("/api/lessons/{$lesson->id}/task", ['answers' => []]);
            $this->assertNotEquals(429, $response->status());
        }

        $this->postJson("/api/lessons/{$lesson->id}/task", ['answers' => []])
            ->assertStatus(429);
    }

    public function test_lesson_progress_is_throttled_after_60_requests_per_user(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->free()->published()->create();
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($student);

        for ($i = 0; $i < 60; $i++) {
            $this->getJson("/api/lessons/{$lesson->id}/progress")
                ->assertOk();
        }

        $this->getJson("/api/lessons/{$lesson->id}/progress")
            ->assertStatus(429);
    }

    public function test_courses_admin_is_throttled_after_60_requests_per_user(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/admin/courses')
                ->assertOk();
        }

        $this->getJson('/api/admin/courses')
            ->assertStatus(429);
    }
}
