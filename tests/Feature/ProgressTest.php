<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use App\Models\UserLessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_progress_summary(): void
    {
        $course = Course::create([
            'title' => 'Paid Course',
            'description' => 'Desc',
            'status' => 'published',
            'is_public' => false,
        ]);

        $this->getJson("/api/courses/{$course->id}/progress-summary")
            ->assertUnauthorized();
    }

    public function test_unenrolled_student_cannot_view_paid_course_progress_summary(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::create([
            'title' => 'Paid Course',
            'description' => 'Desc',
            'status' => 'published',
            'is_public' => false,
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/courses/{$course->id}/progress-summary")
            ->assertForbidden();
    }

    public function test_enrolled_student_can_view_paid_course_progress_summary(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::create([
            'title' => 'Paid Course',
            'description' => 'Desc',
            'status' => 'published',
            'is_public' => false,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'approved',
        ]);

        $section = Section::create(['course_id' => $course->id, 'title' => 'S1', 'order' => 1]);
        $l1 = Lesson::create(['section_id' => $section->id, 'title' => 'L1', 'duration' => 10, 'order' => 1]);
        $l2 = Lesson::create(['section_id' => $section->id, 'title' => 'L2', 'duration' => 10, 'order' => 2]);

        UserLessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $l1->id,
            'watched_percent' => 100,
            'last_position' => 10,
            'completed' => true,
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/courses/{$course->id}/progress-summary");

        $response->assertOk()
            ->assertJson([
                'total_lessons' => 2,
                'completed_count' => 1,
                'percent' => 50,
                'course_complete' => false,
            ]);
    }

    public function test_unenrolled_student_can_view_free_course_progress_summary(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::create([
            'title' => 'Free Course',
            'description' => 'Desc',
            'status' => 'published',
            'is_public' => true,
        ]);

        $section = Section::create(['course_id' => $course->id, 'title' => 'S1', 'order' => 1]);
        $lesson = Lesson::create(['section_id' => $section->id, 'title' => 'L1', 'duration' => 10, 'order' => 1]);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/courses/{$course->id}/progress-summary");

        $response->assertOk()
            ->assertJson([
                'total_lessons' => 1,
                'completed_count' => 0,
                'percent' => 0,
                'course_complete' => false,
            ]);
    }

    public function test_completed_course_marks_course_complete_as_true(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::create([
            'title' => 'Free Course 100',
            'description' => 'Desc',
            'status' => 'published',
            'is_public' => true,
        ]);

        $section = Section::create(['course_id' => $course->id, 'title' => 'S1', 'order' => 1]);
        $l1 = Lesson::create(['section_id' => $section->id, 'title' => 'L1', 'duration' => 10, 'order' => 1]);
        $l2 = Lesson::create(['section_id' => $section->id, 'title' => 'L2', 'duration' => 10, 'order' => 2]);

        UserLessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $l1->id,
            'watched_percent' => 100,
            'last_position' => 10,
            'completed' => true,
        ]);

        UserLessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $l2->id,
            'watched_percent' => 100,
            'last_position' => 10,
            'completed' => true,
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/courses/{$course->id}/progress-summary");

        $response->assertOk()
            ->assertJson([
                'total_lessons' => 2,
                'completed_count' => 2,
                'percent' => 100,
                'course_complete' => true,
            ]);

        $this->assertTrue($response->json('course_complete'));
        $this->assertSame(100, $response->json('percent'));
    }
}
