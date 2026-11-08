<?php

// FILE: tests/Feature/Admin/AdminRefactorTest.php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRefactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function enrollment(string $status, bool $free = false): Enrollment
    {
        $course = Course::create(['title' => 'T', 'description' => 'D', 'is_public' => $free]);
        $student = User::factory()->student()->create();

        return Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => $status]);
    }

    public function test_pending_can_be_approved(): void
    {
        $e = $this->enrollment('pending');

        $this->patchJson("/api/admin/enrollments/{$e->id}", ['status' => 'approved'])->assertOk();
        $this->assertDatabaseHas('enrollments', ['id' => $e->id, 'status' => 'approved']);
    }

    public function test_rejected_cannot_be_approved(): void
    {
        $e = $this->enrollment('rejected');

        $this->patchJson("/api/admin/enrollments/{$e->id}", ['status' => 'approved'])->assertStatus(422);
    }

    public function test_free_course_enrollment_is_rejected(): void
    {
        $e = $this->enrollment('pending', free: true);

        $this->patchJson("/api/admin/enrollments/{$e->id}", ['status' => 'approved'])->assertStatus(422);
    }

    public function test_instructor_update_without_courses_key_keeps_assignments(): void
    {
        $ins = Instructor::create(['name' => 'A']);
        $course = Course::create(['title' => 'T', 'description' => 'D', 'instructor_id' => $ins->id]);

        $this->putJson("/api/admin/instructors/{$ins->id}", ['title' => 'Senior'])->assertOk();

        $this->assertDatabaseHas('instructors', ['id' => $ins->id, 'title' => 'Senior']);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'instructor_id' => $ins->id]);
    }

    public function test_instructor_update_with_empty_courses_unassigns_all(): void
    {
        $ins = Instructor::create(['name' => 'A']);
        $course = Course::create(['title' => 'T', 'description' => 'D', 'instructor_id' => $ins->id]);

        $this->putJson("/api/admin/instructors/{$ins->id}", ['courses' => []])->assertOk();

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'instructor_id' => null]);
    }

    public function test_courses_list_is_a_resource_collection(): void
    {
        Course::create(['title' => 'T', 'description' => 'D']);
        Course::create(['title' => 'T2', 'description' => 'D']);

        $this->getJson('/api/admin/courses')->assertOk()->assertJsonStructure(['data' => [['id', 'title']]]);
    }

    public function test_admin_can_store_course(): void
    {
        $ins = Instructor::create(['name' => 'Instructor 1']);

        $payload = [
            'title' => 'New Course',
            'description' => 'Course description is long enough',
            'level' => 'beginner',
            'status' => 'published',
            'instructor_id' => $ins->id,
            'price' => 50,
            'is_public' => false,
        ];

        $this->postJson('/api/admin/courses', $payload)->assertCreated();
        $this->assertDatabaseHas('courses', ['title' => 'New Course']);
    }

    public function test_admin_can_show_course(): void
    {
        $course = Course::create(['title' => 'Show Course', 'description' => 'Desc']);

        $this->getJson("/api/admin/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('title', 'Show Course');
    }

    public function test_admin_can_update_course(): void
    {
        $course = Course::create(['title' => 'Old Title', 'description' => 'Long enough old description']);

        $this->putJson("/api/admin/courses/{$course->id}", [
            'title' => 'Updated Title',
            'description' => 'Updated Description is long enough',
        ])->assertOk();

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title' => 'Updated Title']);
    }

    public function test_admin_can_delete_course(): void
    {
        $course = Course::create(['title' => 'Delete Me', 'description' => 'Desc']);

        $this->deleteJson("/api/admin/courses/{$course->id}")->assertNoContent();
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_admin_can_view_courses_overview(): void
    {
        Course::create(['title' => 'Course 1', 'description' => 'Desc']);

        $this->getJson('/api/admin/courses-overview')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'title']]]);
    }

    public function test_admin_can_store_and_delete_section(): void
    {
        $course = Course::create(['title' => 'Section Course', 'description' => 'Desc']);

        $res = $this->postJson("/api/admin/courses/{$course->id}/sections", [
            'title' => 'New Section',
            'order' => 1,
        ])->assertCreated();

        $sectionId = $res->json('id');
        $this->assertDatabaseHas('sections', ['id' => $sectionId, 'title' => 'New Section']);

        $this->deleteJson("/api/admin/sections/{$sectionId}")->assertNoContent();
        $this->assertDatabaseMissing('sections', ['id' => $sectionId]);
    }

    public function test_admin_can_store_and_delete_lesson(): void
    {
        $course = Course::create(['title' => 'Course L', 'description' => 'Desc']);
        $section = Section::create(['course_id' => $course->id, 'title' => 'Sec', 'order' => 1]);

        $res = $this->postJson("/api/admin/sections/{$section->id}/lessons", [
            'title' => 'New Lesson',
            'duration' => 20,
            'order' => 1,
            'is_preview' => false,
        ])->assertCreated();

        $lessonId = $res->json('id');
        $this->assertDatabaseHas('lessons', ['id' => $lessonId, 'title' => 'New Lesson']);

        $this->deleteJson("/api/admin/lessons/{$lessonId}")->assertNoContent();
        $this->assertDatabaseMissing('lessons', ['id' => $lessonId]);
    }

    public function test_admin_can_store_and_delete_faq(): void
    {
        $res = $this->postJson('/api/admin/faqs', [
            'question' => 'What is this?',
            'answer' => 'An online course platform.',
            'order' => 1,
        ])->assertCreated();

        $faqId = $res->json('id');
        $this->assertDatabaseHas('faqs', ['id' => $faqId, 'question' => 'What is this?']);

        $this->deleteJson("/api/admin/faqs/{$faqId}")->assertNoContent();
        $this->assertDatabaseMissing('faqs', ['id' => $faqId]);
    }

    public function test_admin_can_delete_instructor(): void
    {
        $ins = Instructor::create(['name' => 'To Delete']);

        $this->deleteJson("/api/admin/instructors/{$ins->id}")->assertNoContent();
        $this->assertDatabaseMissing('instructors', ['id' => $ins->id]);
    }

    public function test_admin_can_list_users_and_students(): void
    {
        User::factory()->student()->create();

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role', 'enrollments_count']]]);

        $this->getJson('/api/admin/students')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role', 'enrollments_count']]]);
    }

    public function test_admin_can_list_enrollments_without_lazy_loading_violation(): void
    {
        $this->enrollment('pending');

        $this->getJson('/api/admin/enrollments')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'status', 'user' => ['id', 'name'], 'course' => ['id', 'title']]]]);
    }

    public function test_admin_can_get_lesson_task_without_lazy_loading_violation(): void
    {
        $course = Course::create(['title' => 'Course For Task', 'description' => 'Desc']);
        $section = Section::create(['course_id' => $course->id, 'title' => 'Sec', 'order' => 1]);
        $lesson = Lesson::create([
            'section_id' => $section->id,
            'title' => 'Lesson with Task',
            'duration' => 10,
            'order' => 1,
            'is_preview' => false,
        ]);

        $this->getJson("/api/admin/lessons/{$lesson->id}/task")
            ->assertNotFound();

        Task::create([
            'lesson_id' => $lesson->id,
            'questions' => [['question' => 'Q1']],
            'pass_percentage' => 70,
            'max_attempts' => 3,
        ]);

        $this->getJson("/api/admin/lessons/{$lesson->id}/task")
            ->assertOk()
            ->assertJsonPath('questions.0.question', 'Q1');
    }
}
