<?php

declare(strict_types=1);

namespace Tests\Feature\Instructor;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Task;
use App\Models\TaskAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructorTaskTest extends TestCase
{
    use RefreshDatabase;

    private User $instructorA;

    private User $instructorB;

    private User $student;

    private Course $courseA;

    private Course $courseB;

    private Section $sectionA;

    private Lesson $lessonA;

    private Section $sectionB;

    private Lesson $lessonB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('instructor', 'web');
        Role::findOrCreate('student', 'web');

        // Create Instructor A & assigned course
        $this->instructorA = User::factory()->create(['name' => 'المدرب الأول']);
        $this->instructorA->assignRole('instructor');
        $profileA = $this->instructorA->instructor;

        $this->courseA = Course::factory()->create([
            'instructor_id' => $profileA->id,
            'title' => 'مقرر المدرب الأول',
        ]);

        $this->sectionA = Section::create([
            'course_id' => $this->courseA->id,
            'title' => 'القسم التمهيدي',
            'order' => 1,
        ]);

        $this->lessonA = Lesson::create([
            'section_id' => $this->sectionA->id,
            'title' => 'الدرس الأول',
            'order' => 1,
            'duration' => 600,
            'video_provider' => 'youtube',
            'is_published' => true,
        ]);

        // Create Instructor B & assigned course (different instructor)
        $this->instructorB = User::factory()->create(['name' => 'المدرب الثاني']);
        $this->instructorB->assignRole('instructor');
        $profileB = $this->instructorB->instructor;

        $this->courseB = Course::factory()->create([
            'instructor_id' => $profileB->id,
            'title' => 'مقرر المدرب الثاني',
        ]);

        $this->sectionB = Section::create([
            'course_id' => $this->courseB->id,
            'title' => 'قسم المدرب الثاني',
            'order' => 1,
        ]);

        $this->lessonB = Lesson::create([
            'section_id' => $this->sectionB->id,
            'title' => 'درس المدرب الثاني',
            'order' => 1,
            'duration' => 900,
            'video_provider' => 'youtube',
            'is_published' => true,
        ]);

        // Create a regular student
        $this->student = User::factory()->create(['name' => 'طالب تجريبي']);
        $this->student->assignRole('student');
    }

    /**
     * Test 1: Authenticated instructor owning the course can view lesson task.
     */
    public function test_instructor_can_get_task_for_own_lesson(): void
    {
        $task = Task::create([
            'lesson_id' => $this->lessonA->id,
            'questions' => [
                [
                    'id' => 'q1',
                    'type' => 'mcq',
                    'question' => 'ما هو Next.js؟',
                    'options' => ['إطار عمل React', 'قاعدة بيانات', 'نظام تشغيل'],
                    'correct_answer' => 0,
                ],
            ],
            'pass_percentage' => 70,
            'max_attempts' => 3,
        ]);

        Sanctum::actingAs($this->instructorA);

        $response = $this->getJson("/api/instructor/lessons/{$this->lessonA->id}/task");

        $response->assertOk()
            ->assertJsonPath('pass_percentage', 70)
            ->assertJsonPath('max_attempts', 3)
            ->assertJsonPath('questions.0.question', 'ما هو Next.js؟');
    }

    /**
     * Test 2: IDOR Prevention - Instructor cannot view task on another instructor's lesson.
     */
    public function test_instructor_cannot_view_task_on_unowned_lesson(): void
    {
        Task::create([
            'lesson_id' => $this->lessonB->id,
            'questions' => [['id' => 'q1', 'question' => 'سؤال']],
            'pass_percentage' => 80,
            'max_attempts' => 2,
        ]);

        Sanctum::actingAs($this->instructorA);

        $response = $this->getJson("/api/instructor/lessons/{$this->lessonB->id}/task");

        $response->assertForbidden();
    }

    /**
     * Test 3: Student cannot access instructor task endpoints.
     */
    public function test_student_cannot_access_instructor_task_endpoints(): void
    {
        Sanctum::actingAs($this->student);

        $response = $this->getJson("/api/instructor/lessons/{$this->lessonA->id}/task");

        $response->assertForbidden();
    }

    /**
     * Test 4: Unauthenticated user receives 401 on instructor task endpoints.
     */
    public function test_unauthenticated_user_cannot_access_instructor_task(): void
    {
        $response = $this->getJson("/api/instructor/lessons/{$this->lessonA->id}/task");

        $response->assertUnauthorized();
    }

    /**
     * Test 5: Returns 404 when lesson has no task.
     */
    public function test_returns_404_when_lesson_has_no_task(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->getJson("/api/instructor/lessons/{$this->lessonA->id}/task");

        $response->assertNotFound();
    }

    /**
     * Test 6: Instructor can create task for own lesson with valid MCQ and True/False questions.
     */
    public function test_instructor_can_create_task_with_canonical_contract(): void
    {
        Sanctum::actingAs($this->instructorA);

        $payload = [
            'questions' => [
                [
                    'id' => 'q1',
                    'type' => 'mcq',
                    'question' => 'ما هو الدور الرئيسي لـ TanStack Query؟',
                    'options' => ['إدارة Server State', 'تنسيق CSS', 'توجيه صفحات'],
                    'correct_answer' => 0,
                    'explanation' => 'TanStack Query متخصص في إدارة الكاش والمزامنة مع السيرفر.',
                ],
                [
                    'id' => 'q2',
                    'type' => 'true_false',
                    'question' => 'هل React Server Components تدعم استخدام useState؟',
                    'options' => ['صح', 'خطأ'],
                    'correct_answer' => 1,
                    'explanation' => 'مكونات الخادم في React 19 لا تحتوي على حالة أو hooks للعميل.',
                ],
            ],
            'pass_percentage' => 75,
            'max_attempts' => 3,
        ];

        $response = $this->postJson("/api/instructor/lessons/{$this->lessonA->id}/task", $payload);

        $response->assertCreated()
            ->assertJsonPath('pass_percentage', 75)
            ->assertJsonPath('max_attempts', 3)
            ->assertJsonPath('questions.0.type', 'mcq')
            ->assertJsonPath('questions.1.type', 'true_false');

        $this->assertDatabaseHas('tasks', [
            'lesson_id' => $this->lessonA->id,
            'pass_percentage' => 75,
            'max_attempts' => 3,
        ]);
    }

    /**
     * Test 7: IDOR Prevention - Instructor cannot create task on another instructor's lesson.
     */
    public function test_instructor_cannot_create_task_on_unowned_lesson(): void
    {
        Sanctum::actingAs($this->instructorA);

        $payload = [
            'questions' => [['id' => 'q1', 'question' => 'test', 'options' => ['a', 'b'], 'correct_answer' => 0]],
            'pass_percentage' => 70,
            'max_attempts' => 3,
        ];

        $response = $this->postJson("/api/instructor/lessons/{$this->lessonB->id}/task", $payload);

        $response->assertForbidden();
    }

    /**
     * Test 8: Validation rejects invalid task payloads (missing questions, out-of-range percentage).
     */
    public function test_create_task_validates_required_fields(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->postJson("/api/instructor/lessons/{$this->lessonA->id}/task", [
            'pass_percentage' => 150, // Invalid: exceeds 100
            'max_attempts' => 0,     // Invalid: min 1
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['questions', 'pass_percentage', 'max_attempts']);
    }

    /**
     * Symmetric Test: Validation rejects invalid task update payloads on PUT.
     */
    public function test_update_task_validates_required_fields_symmetrically(): void
    {
        Task::create([
            'lesson_id' => $this->lessonA->id,
            'questions' => [['id' => 'q1', 'question' => 'سؤال أولي']],
            'pass_percentage' => 70,
            'max_attempts' => 3,
        ]);

        Sanctum::actingAs($this->instructorA);

        $response = $this->putJson("/api/instructor/lessons/{$this->lessonA->id}/task", [
            'pass_percentage' => 150, // Invalid: exceeds 100
            'max_attempts' => 0,     // Invalid: min 1
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['questions', 'pass_percentage', 'max_attempts']);
    }

    /**
     * Test 9: Instructor can update existing task.
     */
    public function test_instructor_can_update_task_for_own_lesson(): void
    {
        $task = Task::create([
            'lesson_id' => $this->lessonA->id,
            'questions' => [
                [
                    'id' => 'q1',
                    'type' => 'mcq',
                    'question' => 'سؤال قديم',
                    'options' => ['أ', 'ب'],
                    'correct_answer' => 0,
                ],
            ],
            'pass_percentage' => 60,
            'max_attempts' => 2,
        ]);

        Sanctum::actingAs($this->instructorA);

        $updatePayload = [
            'questions' => [
                [
                    'id' => 'q1',
                    'type' => 'mcq',
                    'question' => 'سؤال محدث بدقة',
                    'options' => ['أ', 'ب', 'ج'],
                    'correct_answer' => 2,
                ],
            ],
            'pass_percentage' => 80,
            'max_attempts' => 4,
        ];

        $response = $this->putJson("/api/instructor/lessons/{$this->lessonA->id}/task", $updatePayload);

        $response->assertOk()
            ->assertJsonPath('pass_percentage', 80)
            ->assertJsonPath('max_attempts', 4)
            ->assertJsonPath('questions.0.question', 'سؤال محدث بدقة');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'pass_percentage' => 80,
            'max_attempts' => 4,
        ]);
    }

    /**
     * Test 10: Task Integrity Guard - Instructor can delete task if no student attempts exist.
     */
    public function test_instructor_can_delete_task_when_no_student_attempts_exist(): void
    {
        $task = Task::create([
            'lesson_id' => $this->lessonA->id,
            'questions' => [['id' => 'q1', 'question' => 'سؤال تجريبي']],
            'pass_percentage' => 70,
            'max_attempts' => 3,
        ]);

        Sanctum::actingAs($this->instructorA);

        $response = $this->deleteJson("/api/instructor/lessons/{$this->lessonA->id}/task");

        $response->assertOk();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    /**
     * Test 11: Task Integrity Guard (CRITICAL) - Deleting task with recorded student attempts is blocked with 422.
     */
    public function test_task_integrity_guard_prevents_deleting_task_with_recorded_attempts(): void
    {
        $task = Task::create([
            'lesson_id' => $this->lessonA->id,
            'questions' => [
                [
                    'id' => 'q1',
                    'type' => 'mcq',
                    'question' => 'سؤال مكتمل',
                    'options' => ['صح', 'خطأ'],
                    'correct_answer' => 0,
                ],
            ],
            'pass_percentage' => 70,
            'max_attempts' => 3,
        ]);

        // Record a student attempt
        TaskAttempt::create([
            'task_id' => $task->id,
            'user_id' => $this->student->id,
            'answers' => ['q1' => 0],
            'score' => 100,
            'passed' => true,
            'attempt_number' => 1,
        ]);

        Sanctum::actingAs($this->instructorA);

        $response = $this->deleteJson("/api/instructor/lessons/{$this->lessonA->id}/task");

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Cannot delete a task with recorded student attempts.',
            ]);

        // Prove task was NOT deleted
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }
}
