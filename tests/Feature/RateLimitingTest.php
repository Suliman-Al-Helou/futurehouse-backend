<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use App\Models\UserLessonProgress;
use App\Models\ZoomMeeting;
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

        UserLessonProgress::create([
            'user_id' => $student1->id,
            'lesson_id' => $lesson->id,
            'watched_percent' => 100,
            'completed' => true,
        ]);
        UserLessonProgress::create([
            'user_id' => $student2->id,
            'lesson_id' => $lesson->id,
            'watched_percent' => 100,
            'completed' => true,
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

    public function test_login_is_throttled_after_5_failed_attempts_for_same_email_and_ip(): void
    {
        $email = 'target@example.com';
        $ip1 = '198.51.100.10';
        $ip2 = '198.51.100.20';

        // 5 consecutive failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip1])
                ->postJson('/api/auth/login', [
                    'email' => $email,
                    'password' => 'wrongpassword',
                ])
                ->assertStatus(401);
        }

        // 6th attempt is throttled with 429 and Retry-After header
        $this->withServerVariables(['REMOTE_ADDR' => $ip1])
            ->postJson('/api/auth/login', [
                'email' => $email,
                'password' => 'wrongpassword',
            ])
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        // Different IP with same email is not throttled (partitioned by email|ip)
        $this->withServerVariables(['REMOTE_ADDR' => $ip2])
            ->postJson('/api/auth/login', [
                'email' => $email,
                'password' => 'wrongpassword',
            ])
            ->assertStatus(401);

        // Time travel past 1 minute window resets the throttle counter
        $this->travel(61)->seconds();

        $this->withServerVariables(['REMOTE_ADDR' => $ip1])
            ->postJson('/api/auth/login', [
                'email' => $email,
                'password' => 'wrongpassword',
            ])
            ->assertStatus(401);
    }

    public function test_login_throttles_ip_after_20_attempts_across_different_emails(): void
    {
        $ip = '198.51.100.30';

        for ($i = 0; $i < 20; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/auth/login', [
                    'email' => "user{$i}@example.com",
                    'password' => 'wrongpassword',
                ])
                ->assertStatus(401);
        }

        // 21st attempt from this IP is throttled even with a brand new email
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/login', [
                'email' => 'newuser@example.com',
                'password' => 'wrongpassword',
            ])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_register_is_throttled_after_10_requests_per_hour(): void
    {
        $ip1 = '198.51.100.40';
        $ip2 = '198.51.100.50';

        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip1])
                ->postJson('/api/auth/register', [
                    'name' => "User {$i}",
                    'email' => "student{$i}@example.com",
                    'password' => 'SecurePass123!',
                    'password_confirmation' => 'SecurePass123!',
                ])
                ->assertCreated();
        }

        // 11th registration attempt from ip1 is throttled
        $this->withServerVariables(['REMOTE_ADDR' => $ip1])
            ->postJson('/api/auth/register', [
                'name' => 'User 11',
                'email' => 'student11@example.com',
                'password' => 'SecurePass123!',
                'password_confirmation' => 'SecurePass123!',
            ])
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        // Different IP is isolated and allowed
        $this->withServerVariables(['REMOTE_ADDR' => $ip2])
            ->postJson('/api/auth/register', [
                'name' => 'User From Other IP',
                'email' => 'otherip@example.com',
                'password' => 'SecurePass123!',
                'password_confirmation' => 'SecurePass123!',
            ])
            ->assertCreated();
    }

    public function test_forgot_password_is_throttled_after_3_requests_per_minute(): void
    {
        $email = 'forgot@example.com';
        $ip = '198.51.100.60';

        for ($i = 0; $i < 3; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/auth/forgot-password', ['email' => $email])
                ->assertOk();
        }

        // 4th request within a minute is throttled
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/forgot-password', ['email' => $email])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_reset_password_is_throttled_after_5_requests_per_minute(): void
    {
        $email = 'reset@example.com';
        $ip = '198.51.100.70';

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/auth/reset-password', [
                    'token' => 'invalid-token',
                    'email' => $email,
                    'password' => 'NewPassword123!',
                    'password_confirmation' => 'NewPassword123!',
                ]);
        }

        // 6th request within a minute is throttled
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/reset-password', [
                'token' => 'invalid-token',
                'email' => $email,
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_attendance_submit_is_throttled_after_10_requests_per_user(): void
    {
        $student1 = User::factory()->student()->create();
        $student2 = User::factory()->student()->create();

        $course = Course::factory()->free()->published()->create();
        $meeting = ZoomMeeting::create([
            'course_id' => $course->id,
            'title' => 'Live Interactive Session',
            'zoom_link' => 'https://zoom.us/j/1234567890',
            'starts_at' => now()->subMinutes(10),
            'duration_minutes' => 60,
        ]);

        Sanctum::actingAs($student1);

        // 10 submissions succeed
        for ($i = 0; $i < 10; $i++) {
            $this->postJson("/api/zoom-meetings/{$meeting->id}/attend")
                ->assertOk();
        }

        // 11th request is throttled
        $this->postJson("/api/zoom-meetings/{$meeting->id}/attend")
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        // Different student is isolated and not throttled
        Sanctum::actingAs($student2);
        $this->postJson("/api/zoom-meetings/{$meeting->id}/attend")
            ->assertOk();
    }

    public function test_shared_cache_store_configuration_for_production(): void
    {
        // Assert production template (.env.example) uses shared cache and queue
        $envExample = (string) file_get_contents(base_path('.env.example'));
        $this->assertMatchesRegularExpression('/CACHE_STORE=(database|redis)/', $envExample);
        $this->assertMatchesRegularExpression('/QUEUE_CONNECTION=(database|redis)/', $envExample);

        // Assert base cache config falls back to database store
        $cacheConfigContent = (string) file_get_contents(config_path('cache.php'));
        $this->assertStringContainsString("'default' => env('CACHE_STORE', 'database')", $cacheConfigContent);
        $this->assertStringContainsString("'driver' => 'database'", $cacheConfigContent);
        $this->assertStringContainsString("'lock_table' => env('DB_CACHE_LOCK_TABLE')", $cacheConfigContent);

        // Assert base queue config falls back to database queue
        $queueConfigContent = (string) file_get_contents(config_path('queue.php'));
        $this->assertStringContainsString("'default' => env('QUEUE_CONNECTION', 'database')", $queueConfigContent);
        $this->assertStringContainsString("'table' => 'failed_jobs'", $queueConfigContent);
    }
}
