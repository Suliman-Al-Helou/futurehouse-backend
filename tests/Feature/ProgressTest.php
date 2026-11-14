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

    public function test_student_can_update_lesson_progress_with_valid_data(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->free()->published()->create();
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($student);

        $response = $this->postJson("/api/lessons/{$lesson->id}/progress", [
            'watched_percent' => 50,
            'last_position' => 120,
        ]);

        $response->assertOk()
            ->assertJson([
                'watched_percent' => 50,
                'completed' => false,
                'task_unlocked' => false,
            ]);

        $this->assertDatabaseHas('user_lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'watched_percent' => 50,
            'last_position' => 120,
            'completed' => false,
        ]);
    }

    public function test_student_completes_lesson_when_watched_percent_reaches_80(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->free()->published()->create();
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($student);

        $response = $this->postJson("/api/lessons/{$lesson->id}/progress", [
            'watched_percent' => 85,
            'last_position' => 200,
        ]);

        $response->assertOk()
            ->assertJson([
                'watched_percent' => 85,
                'completed' => true,
                'task_unlocked' => true,
            ]);

        $this->assertDatabaseHas('user_lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'completed' => true,
        ]);
    }

    public function test_update_lesson_progress_validation_requires_fields(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->free()->published()->create();
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($student);

        $response = $this->postJson("/api/lessons/{$lesson->id}/progress", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['watched_percent', 'last_position']);
    }

    public function test_update_lesson_progress_validation_rejects_out_of_range_and_invalid_types(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->free()->published()->create();
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($student);

        $this->postJson("/api/lessons/{$lesson->id}/progress", [
            'watched_percent' => 150,
            'last_position' => -10,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['watched_percent', 'last_position']);

        $this->postJson("/api/lessons/{$lesson->id}/progress", [
            'watched_percent' => -5,
            'last_position' => 'not-a-number',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['watched_percent', 'last_position']);
    }

    public function test_update_lesson_progress_safely_rounds_float_last_position(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->free()->published()->create();
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($student);

        $response = $this->postJson("/api/lessons/{$lesson->id}/progress", [
            'watched_percent' => 45.5,
            'last_position' => 125.8,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('user_lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'last_position' => 126,
        ]);
    }

    public function test_progress_does_not_decrease_when_lower_percent_is_sent(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->free()->published()->create();
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($student);

        $this->postJson("/api/lessons/{$lesson->id}/progress", [
            'watched_percent' => 85,
            'last_position' => 300,
        ])->assertOk();

        $response = $this->postJson("/api/lessons/{$lesson->id}/progress", [
            'watched_percent' => 30,
            'last_position' => 100,
        ]);

        $response->assertOk()
            ->assertJson([
                'watched_percent' => 85,
                'completed' => true,
            ]);

        $this->assertDatabaseHas('user_lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'watched_percent' => 85,
            'completed' => true,
        ]);
    }

    public function test_in_progress_lessons_returns_uncompleted_lessons_with_watch_percent(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->free()->published()->create(['title' => 'Vue 3 Mastery']);
        $section = Section::factory()->create(['course_id' => $course->id, 'title' => 'Intro']);
        $lesson1 = Lesson::factory()->create(['section_id' => $section->id, 'title' => 'Lesson 1']);
        $lesson2 = Lesson::factory()->create(['section_id' => $section->id, 'title' => 'Lesson 2']);

        // Lesson 1 is partially watched (in progress)
        UserLessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson1->id,
            'watched_percent' => 45.5,
            'completed' => false,
        ]);

        // Lesson 2 is completed (should not appear in in-progress)
        UserLessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson2->id,
            'watched_percent' => 100,
            'completed' => true,
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/lessons/in-progress');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'id' => $lesson1->id,
                'title' => 'Lesson 1',
                'watched_percent' => 45.5,
            ])
            ->assertJsonPath('0.course.title', 'Vue 3 Mastery')
            ->assertJsonPath('0.section.title', 'Intro');
    }
}
