<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_courses_public_is_throttled_after_60_requests_per_ip(): void
    {
        $ip1 = '192.168.1.100';
        $ip2 = '192.168.1.101';
        $course = Course::factory()->free()->published()->create();

        for ($i = 0; $i < 60; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip1])
                ->getJson('/api/courses')
                ->assertOk();
        }

        // 61st request from ip1 is throttled
        $this->withServerVariables(['REMOTE_ADDR' => $ip1])
            ->getJson('/api/courses')
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        // GET /courses/{course} from ip1 is also throttled under courses-public
        $this->withServerVariables(['REMOTE_ADDR' => $ip1])
            ->getJson("/api/courses/{$course->id}")
            ->assertStatus(429);

        // Different IP is isolated and not throttled
        $this->withServerVariables(['REMOTE_ADDR' => $ip2])
            ->getJson('/api/courses')
            ->assertOk();
    }

    public function test_course_enroll_is_throttled_after_15_requests_per_user(): void
    {
        $student1 = User::factory()->student()->create();
        $student2 = User::factory()->student()->create();
        $course = Course::factory()->free()->published()->create();

        Sanctum::actingAs($student1);

        for ($i = 0; $i < 15; $i++) {
            $this->deleteJson("/api/courses/{$course->id}/enroll")
                ->assertOk();
        }

        // 16th request is throttled
        $this->deleteJson("/api/courses/{$course->id}/enroll")
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        // Different user is isolated and not throttled
        Sanctum::actingAs($student2);
        $this->deleteJson("/api/courses/{$course->id}/enroll")
            ->assertOk();
    }

    public function test_task_submit_is_throttled_after_20_requests_per_user(): void
    {
        $student1 = User::factory()->student()->create();
        $student2 = User::factory()->student()->create();
        $course = Course::factory()->free()->published()->create();
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Task::create([
            'lesson_id' => $lesson->id,
            'questions' => [
                ['id' => '1', 'type' => 'mcq', 'correct_answer' => 'a'],
            ],
            'pass_percentage' => 70,
            'max_attempts' => 50,
            'cooldown_minutes' => 0,
        ]);

        Sanctum::actingAs($student1);

        for ($i = 0; $i < 20; $i++) {
            $this->postJson("/api/lessons/{$lesson->id}/task", ['answers' => ['1' => 'a']])
                ->assertOk();
        }

        // 21st request from student1 is throttled
        $this->postJson("/api/lessons/{$lesson->id}/task", ['answers' => ['1' => 'a']])
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        // Different student is isolated and not throttled
        Sanctum::actingAs($student2);
        $this->postJson("/api/lessons/{$lesson->id}/task", ['answers' => ['1' => 'a']])
            ->assertOk();
    }

    public function test_lesson_progress_is_throttled_after_60_requests_per_user(): void
    {
        $student1 = User::factory()->student()->create();
        $student2 = User::factory()->student()->create();
        $course = Course::factory()->free()->published()->create();
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($student1);

        for ($i = 0; $i < 60; $i++) {
            $this->getJson("/api/lessons/{$lesson->id}/progress")
                ->assertOk();
        }

        // 61st request from student1 is throttled
        $this->getJson("/api/lessons/{$lesson->id}/progress")
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        // Course summary route shares the lesson-progress rate limiter
        $this->getJson("/api/courses/{$course->id}/progress-summary")
            ->assertStatus(429);

        // Different student is isolated and not throttled
        Sanctum::actingAs($student2);
        $this->getJson("/api/lessons/{$lesson->id}/progress")
            ->assertOk();
    }

    public function test_courses_admin_is_throttled_after_60_requests_per_user(): void
    {
        $admin1 = User::factory()->admin()->create();
        $admin2 = User::factory()->admin()->create();
        $course = Course::factory()->free()->published()->create();

        Sanctum::actingAs($admin1);

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/admin/courses')
                ->assertOk();
        }

        // 61st request from admin1 is throttled
        $this->getJson('/api/admin/courses')
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        // Admin sections route shares the courses-admin rate limiter
        $this->getJson("/api/admin/courses/{$course->id}/sections")
            ->assertStatus(429);

        // Different admin is isolated and not throttled
        Sanctum::actingAs($admin2);
        $this->getJson('/api/admin/courses')
            ->assertOk();
    }
}
