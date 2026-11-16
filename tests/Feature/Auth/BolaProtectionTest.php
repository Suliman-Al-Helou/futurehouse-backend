<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use App\Models\UserLessonProgress;
use App\Models\ZoomAttendance;
use App\Models\ZoomMeeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BolaProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_update_another_students_profile(): void
    {
        $studentA = User::factory()->student()->create(['name' => 'Alice']);
        $studentB = User::factory()->student()->create(['name' => 'Bob']);

        Sanctum::actingAs($studentA);

        $response = $this->putJson("/api/users/{$studentB->id}", [
            'name' => 'Maliciously Changed Name',
        ]);

        $response->assertForbidden();
        $this->assertSame('Bob', $studentB->fresh()->name);
    }

    public function test_student_my_courses_never_leaks_another_students_enrollments(): void
    {
        $studentA = User::factory()->student()->create();
        $studentB = User::factory()->student()->create();

        $courseA = Course::factory()->create(['is_public' => true]);
        $courseB = Course::factory()->create(['is_public' => true]);

        Enrollment::create(['user_id' => $studentA->id, 'course_id' => $courseA->id, 'status' => 'approved']);
        Enrollment::create(['user_id' => $studentB->id, 'course_id' => $courseB->id, 'status' => 'approved']);

        Sanctum::actingAs($studentA);

        $response = $this->getJson('/api/my-courses');
        $response->assertOk();

        $enrolledCourseIds = collect($response->json())->pluck('course_id')->all();
        $this->assertContains($courseA->id, $enrolledCourseIds);
        $this->assertNotContains($courseB->id, $enrolledCourseIds);
    }

    public function test_unenrolled_student_cannot_view_paid_course_lesson(): void
    {
        $student = User::factory()->student()->create();
        $paidCourse = Course::factory()->create([
            'price' => 150.00,
            'is_public' => false,
            'status' => 'published',
        ]);

        $section = Section::factory()->create(['course_id' => $paidCourse->id]);
        $lesson = Lesson::factory()->create([
            'section_id' => $section->id,
            'is_preview' => false,
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/lessons/{$lesson->id}");
        $response->assertForbidden();
    }

    public function test_unenrolled_student_cannot_view_or_submit_tasks_in_paid_course(): void
    {
        $student = User::factory()->student()->create();
        $paidCourse = Course::factory()->create([
            'price' => 200.00,
            'is_public' => false,
            'status' => 'published',
        ]);

        $section = Section::factory()->create(['course_id' => $paidCourse->id]);
        $lesson = Lesson::factory()->create([
            'section_id' => $section->id,
            'is_preview' => false,
        ]);

        Task::create([
            'lesson_id' => $lesson->id,
            'title' => 'Protected Task',
            'pass_percentage' => 70,
            'max_attempts' => 3,
            'questions' => [
                ['id' => 1, 'type' => 'mcq', 'question' => 'Q?', 'options' => ['A', 'B'], 'correct_answer' => 'A'],
            ],
        ]);

        Sanctum::actingAs($student);

        // Cannot view task
        $this->getJson("/api/lessons/{$lesson->id}/task")->assertForbidden();

        // Cannot submit task
        $this->postJson("/api/lessons/{$lesson->id}/task", [
            'answers' => [1 => 'A'],
        ])->assertForbidden();
    }

    public function test_unenrolled_student_cannot_attend_paid_course_zoom_meeting(): void
    {
        $student = User::factory()->student()->create();
        $paidCourse = Course::factory()->create([
            'price' => 300.00,
            'is_public' => false,
            'status' => 'published',
        ]);

        $meeting = ZoomMeeting::factory()->create([
            'course_id' => $paidCourse->id,
            'starts_at' => now()->subMinutes(5),
            'duration_minutes' => 60,
        ]);

        Sanctum::actingAs($student);

        // Cannot attend
        $this->postJson("/api/zoom-meetings/{$meeting->id}/attend")
            ->assertForbidden()
            ->assertJson(['message' => 'هذا اللقاء غير متاح لك أو لم يتم قبولك بالكورس']);

        // Cannot view attendance records
        $this->getJson("/api/zoom-meetings/attendance?course_id={$paidCourse->id}")
            ->assertForbidden()
            ->assertJson(['message' => 'غير مسجل أو غير مقبول في هذا الكورس']);
    }

    public function test_raw_json_never_leaks_video_library_urls_or_secrets(): void
    {
        $course = Course::factory()->create([
            'is_public' => true,
            'status' => 'published',
        ]);
        $section = Section::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->create([
            'section_id' => $section->id,
            'title' => 'Public Detail Lesson',
            'video_id' => 'sample_youtube_id',
        ]);

        $response = $this->getJson("/api/courses/{$course->id}");
        $response->assertOk();

        $content = (string) $response->getContent();

        // Assert sensitive asset storage path and channel keys are strictly absent from raw JSON
        $this->assertStringNotContainsString('videos/raw/', $content);
        $this->assertStringNotContainsString('source_path', $content);
        $this->assertStringNotContainsString('client_secret', $content);
        $this->assertStringNotContainsString('refresh_token', $content);
    }

    public function test_raw_json_never_leaks_sensitive_user_attributes(): void
    {
        $user = User::factory()->student()->create([
            'email' => 'student_leak_test@example.com',
            'password' => 'SafePass123',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');
        $response->assertOk();

        $content = (string) $response->getContent();
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('remember_token', $content);
        $this->assertStringNotContainsString('two_factor_secret', $content);
    }

    public function test_public_courses_enforces_maximum_page_size_ceiling(): void
    {
        // Request with excessive limit
        $response = $this->getJson('/api/courses?limit=1000');
        $response->assertOk();

        // Ensure returned items count does not exceed safety ceiling (50)
        $data = $response->json('data');
        $this->assertLessThanOrEqual(50, count($data));
    }

    public function test_user_owned_model_policies_enforce_strict_ownership(): void
    {
        $studentA = User::factory()->student()->create();
        $studentB = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        $course = Course::factory()->create();
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        $enrollment = Enrollment::create(['user_id' => $studentA->id, 'course_id' => $course->id, 'status' => 'approved']);
        $progress = UserLessonProgress::create(['user_id' => $studentA->id, 'lesson_id' => $lesson->id, 'watched_percent' => 50]);
        $meeting = ZoomMeeting::factory()->create(['course_id' => $course->id]);
        $attendance = ZoomAttendance::create(['user_id' => $studentA->id, 'zoom_meeting_id' => $meeting->id, 'attended_at' => now()]);

        // Student A can view own enrollment
        $this->assertTrue(Gate::forUser($studentA)->allows('view', $enrollment));
        // Student B CANNOT view Student A's enrollment
        $this->assertFalse(Gate::forUser($studentB)->allows('view', $enrollment));

        // Student A can view own progress
        $this->assertTrue(Gate::forUser($studentA)->allows('view', $progress));
        // Student B CANNOT view Student A's progress
        $this->assertFalse(Gate::forUser($studentB)->allows('view', $progress));

        // Student A can view own attendance
        $this->assertTrue(Gate::forUser($studentA)->allows('view', $attendance));
        // Student B CANNOT view Student A's attendance
        $this->assertFalse(Gate::forUser($studentB)->allows('view', $attendance));

        // Admin bypasses checks cleanly
        $this->assertTrue(Gate::forUser($admin)->allows('view', $enrollment));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $progress));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $attendance));
    }
}
