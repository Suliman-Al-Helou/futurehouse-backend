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
use App\Services\CourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
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
            'what_you_learn' => ['Item 1', 'Item 2'],
            'requirements' => ['Req 1'],
            'target_audience' => ['Audience 1'],
        ];

        $response = $this->postJson('/api/admin/courses', $payload)->assertCreated();
        $this->assertDatabaseHas('courses', ['title' => 'New Course']);
        $response->assertJsonPath('instructor.id', $ins->id)
            ->assertJsonPath('instructor.name', 'Instructor 1')
            ->assertJsonPath('what_you_learn.0', 'Item 1');
    }

    public function test_admin_store_course_validates_nested_arrays(): void
    {
        $ins = Instructor::create(['name' => 'Instructor 1']);

        $basePayload = [
            'title' => 'Validation Test Course',
            'description' => 'Course description is long enough',
            'level' => 'beginner',
            'status' => 'published',
            'instructor_id' => $ins->id,
        ];

        // Item exceeds 500 chars
        $this->postJson('/api/admin/courses', array_merge($basePayload, [
            'what_you_learn' => [str_repeat('a', 501)],
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['what_you_learn.0']);

        // Non-array passed to requirements
        $this->postJson('/api/admin/courses', array_merge($basePayload, [
            'requirements' => 'not-an-array',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['requirements']);

        // Item not a string
        $this->postJson('/api/admin/courses', array_merge($basePayload, [
            'target_audience' => [['nested' => 'array']],
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['target_audience.0']);
    }

    public function test_admin_can_show_course(): void
    {
        $ins = Instructor::create(['name' => 'Show Instructor']);
        $course = Course::create([
            'title' => 'Show Course',
            'description' => 'Desc',
            'instructor_id' => $ins->id,
        ]);

        $section = Section::create(['course_id' => $course->id, 'title' => 'Sec', 'order' => 1]);
        Lesson::create([
            'section_id' => $section->id,
            'title' => 'Les',
            'duration' => 10,
            'order' => 1,
            'is_preview' => false,
        ]);

        $student = User::factory()->student()->create();
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'approved']);

        DB::enableQueryLog();

        $response = $this->getJson("/api/admin/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('title', 'Show Course')
            ->assertJsonPath('instructor.id', $ins->id)
            ->assertJsonPath('instructor.name', 'Show Instructor')
            ->assertJsonPath('students_count', 1)
            ->assertJsonPath('enrollments_count', 1)
            ->assertJsonMissingPath('sections')
            ->assertJsonMissingPath('lessons');

        $queries = DB::getQueryLog();
        $queriesString = collect($queries)->pluck('query')->implode(' ');

        // Verify sections and lessons tables were NOT queried during admin show
        $this->assertStringNotContainsString('sections', $queriesString);
        $this->assertStringNotContainsString('lessons', $queriesString);
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

    public function test_admin_update_course_loads_instructor_and_handles_nullable_instructor_id(): void
    {
        $ins1 = Instructor::create(['name' => 'First Instructor']);
        $ins2 = Instructor::create(['name' => 'Second Instructor']);

        $course = Course::create([
            'title' => 'Course With Instructor',
            'description' => 'Long enough description for update test',
            'instructor_id' => $ins1->id,
        ]);

        // 1. Update instructor_id to null
        $responseNull = $this->putJson("/api/admin/courses/{$course->id}", [
            'instructor_id' => null,
        ])->assertOk();

        $responseNull->assertJsonPath('instructor', null);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'instructor_id' => null]);

        // 2. Update instructor_id to another instructor
        $responseNew = $this->putJson("/api/admin/courses/{$course->id}", [
            'instructor_id' => $ins2->id,
        ])->assertOk();

        $responseNew->assertJsonPath('instructor.id', $ins2->id)
            ->assertJsonPath('instructor.name', 'Second Instructor');
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'instructor_id' => $ins2->id]);

        // 3. Update with invalid instructor_id fails validation
        $this->putJson("/api/admin/courses/{$course->id}", [
            'instructor_id' => 99999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['instructor_id']);

        // 4. Update with nested array exceeding 500 chars fails validation
        $this->putJson("/api/admin/courses/{$course->id}", [
            'what_you_learn' => [str_repeat('z', 501)],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['what_you_learn.0']);

        // 5. Update with valid nested arrays succeeds
        $this->putJson("/api/admin/courses/{$course->id}", [
            'what_you_learn' => ['Clean Code', 'TDD'],
            'requirements' => ['PHP 8.4'],
            'target_audience' => ['Backend Engineers'],
        ])->assertOk()
            ->assertJsonPath('what_you_learn.0', 'Clean Code')
            ->assertJsonPath('requirements.0', 'PHP 8.4')
            ->assertJsonPath('target_audience.0', 'Backend Engineers');

        // 6. Update without instructor_id retains current instructor
        $this->putJson("/api/admin/courses/{$course->id}", [
            'title' => 'Renamed Course',
        ])->assertOk()
            ->assertJsonPath('instructor.id', $ins2->id)
            ->assertJsonPath('instructor.name', 'Second Instructor');
    }

    public function test_admin_update_course_preserves_counts_and_curriculum_efficiency(): void
    {
        $ins = Instructor::create(['name' => 'Counts Instructor']);
        $course = Course::create([
            'title' => 'Course For Update Counts',
            'description' => 'Description long enough for testing update counts',
            'instructor_id' => $ins->id,
            'students_count' => 0,
        ]);

        $section = Section::create(['course_id' => $course->id, 'title' => 'Curriculum Section', 'order' => 1]);
        Lesson::create([
            'section_id' => $section->id,
            'title' => 'Curriculum Lesson',
            'duration' => 15,
            'order' => 1,
            'is_preview' => false,
        ]);

        $student1 = User::factory()->student()->create();
        $student2 = User::factory()->student()->create();
        Enrollment::create(['user_id' => $student1->id, 'course_id' => $course->id, 'status' => 'approved']);
        Enrollment::create(['user_id' => $student2->id, 'course_id' => $course->id, 'status' => 'pending']);

        DB::enableQueryLog();

        $response = $this->putJson("/api/admin/courses/{$course->id}", [
            'title' => 'Updated Title With Preserved Counts',
        ]);

        $response->assertOk()
            ->assertJsonPath('title', 'Updated Title With Preserved Counts')
            ->assertJsonPath('instructor.id', $ins->id)
            ->assertJsonPath('instructor.name', 'Counts Instructor')
            ->assertJsonPath('students_count', 1)
            ->assertJsonPath('enrollments_count', 2);

        $queries = DB::getQueryLog();
        $queriesString = collect($queries)->pluck('query')->implode(' ');

        $this->assertStringNotContainsString('sections', $queriesString);
        $this->assertStringNotContainsString('lessons', $queriesString);
    }

    public function test_admin_can_delete_course(): void
    {
        $course = Course::create(['title' => 'Delete Me', 'description' => 'Desc']);

        $this->deleteJson("/api/admin/courses/{$course->id}")->assertNoContent();
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_admin_cannot_delete_course_with_active_enrollments(): void
    {
        $course = Course::create(['title' => 'Course With Enrolled Students', 'description' => 'Desc']);
        $student = User::factory()->student()->create();
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'approved',
        ]);

        $response = $this->deleteJson("/api/admin/courses/{$course->id}");

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Cannot delete course because students are enrolled.',
            ]);

        $this->assertDatabaseHas('courses', ['id' => $course->id]);

        // Also verify service layer directly throws ConflictHttpException
        $this->expectException(ConflictHttpException::class);
        app(CourseService::class)->delete($course);
    }

    public function test_admin_cannot_delete_course_with_pending_enrollments(): void
    {
        $course = Course::create(['title' => 'Course With Pending Students', 'description' => 'Desc']);
        $student = User::factory()->student()->create();
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'pending',
        ]);

        $response = $this->deleteJson("/api/admin/courses/{$course->id}");

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Cannot delete course because students are enrolled.',
            ]);

        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    public function test_admin_cannot_delete_course_with_soft_deleted_enrollments(): void
    {
        $course = Course::create(['title' => 'Course With Soft Deleted Enrollment', 'description' => 'Desc']);
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'approved',
        ]);

        $enrollment->delete();
        $this->assertTrue($enrollment->fresh()->trashed());

        $response = $this->deleteJson("/api/admin/courses/{$course->id}");

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Cannot delete course because students are enrolled.',
            ]);

        $this->assertDatabaseHas('courses', ['id' => $course->id]);

        // Verify service layer also rejects deletion
        $this->expectException(ConflictHttpException::class);
        app(CourseService::class)->delete($course);
    }

    public function test_admin_enrollment_status_transition_updates_course_students_count(): void
    {
        $course = Course::create([
            'title' => 'Count Transition Course',
            'description' => 'Desc',
            'is_public' => false,
            'students_count' => 0,
        ]);
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'pending',
        ]);

        // Pending -> Approved increments students_count
        $this->patchJson("/api/admin/enrollments/{$enrollment->id}", [
            'status' => 'approved',
        ])->assertOk();

        $this->assertSame(1, $course->fresh()->students_count);

        // Approved -> Rejected decrements students_count
        $this->patchJson("/api/admin/enrollments/{$enrollment->id}", [
            'status' => 'rejected',
        ])->assertOk();

        $this->assertSame(0, $course->fresh()->students_count);
    }

    public function test_courses_overview_route_is_removed(): void
    {
        Course::create(['title' => 'Course 1', 'description' => 'Desc']);

        $this->getJson('/api/admin/courses-overview')
            ->assertNotFound();
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

    public function test_admin_can_delete_student_user(): void
    {
        $student = User::factory()->student()->create();

        $this->deleteJson("/api/admin/users/{$student->id}")
            ->assertOk()
            ->assertJson(['message' => 'تم حذف المستخدم بنجاح']);

        $this->assertSoftDeleted('users', ['id' => $student->id]);
    }

    public function test_admin_cannot_delete_themselves_or_other_admin(): void
    {
        $currentAdmin = auth()->user();
        $this->deleteJson("/api/admin/users/{$currentAdmin->id}")
            ->assertStatus(403);

        $otherAdmin = User::factory()->admin()->create();
        $this->deleteJson("/api/admin/users/{$otherAdmin->id}")
            ->assertStatus(403);
    }

    public function test_admin_can_update_user_role(): void
    {
        $user = User::factory()->student()->create();

        $response = $this->patchJson("/api/admin/users/{$user->id}", [
            'role' => 'instructor',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.role', 'instructor');

        $this->assertTrue($user->fresh()->hasRole('instructor'));
    }

    public function test_admin_can_manually_create_enrollment(): void
    {
        $course = Course::create(['title' => 'Vue Mastery', 'description' => 'Desc']);

        $response = $this->postJson('/api/admin/enrollments', [
            'course_id' => $course->id,
            'student_email' => 'newstudent@example.com',
            'student_name' => 'New Student',
            'status' => 'approved',
            'payment_confirmed' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.payment_confirmed', true)
            ->assertJsonPath('data.student_email', 'newstudent@example.com');

        $this->assertDatabaseHas('enrollments', [
            'course_id' => $course->id,
            'status' => 'approved',
        ]);
    }
}
