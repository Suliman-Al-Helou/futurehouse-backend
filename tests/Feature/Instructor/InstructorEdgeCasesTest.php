<?php

declare(strict_types=1);

namespace Tests\Feature\Instructor;

use App\Models\Course;
use App\Models\CourseActivityLog;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use App\Models\UserLessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructorEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private User $instructorA;

    private User $instructorB;

    private User $student;

    private Course $courseA;

    private Section $sectionA;

    private Lesson $lessonA1;

    private Lesson $lessonA2;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('instructor', 'web');
        Role::findOrCreate('student', 'web');

        $this->instructorA = User::factory()->create(['name' => 'المدرب أ']);
        $this->instructorA->assignRole('instructor');

        $this->instructorB = User::factory()->create(['name' => 'المدرب ب']);
        $this->instructorB->assignRole('instructor');

        $this->student = User::factory()->create([
            'name' => 'طالب مسجل',
            'email' => 'student-pii@example.com',
        ]);
        $this->student->assignRole('student');

        $this->courseA = Course::factory()->create([
            'instructor_id' => $this->instructorA->instructor->id,
            'title' => 'كورس اختبار الحالات الحدية',
        ]);

        $this->sectionA = Section::create([
            'course_id' => $this->courseA->id,
            'title' => 'القسم الأول',
            'order' => 1,
        ]);

        $this->lessonA1 = Lesson::create([
            'section_id' => $this->sectionA->id,
            'title' => 'الدرس الأول',
            'order' => 1,
            'duration' => 300,
            'video_provider' => 'youtube',
            'is_published' => true,
        ]);

        $this->lessonA2 = Lesson::create([
            'section_id' => $this->sectionA->id,
            'title' => 'الدرس الثاني',
            'order' => 2,
            'duration' => 450,
            'video_provider' => 'youtube',
            'is_published' => true,
        ]);
    }

    public function test_non_owning_instructor_and_student_get_403_on_course_students_roster(): void
    {
        Sanctum::actingAs($this->instructorB);
        $this->getJson("/api/instructor/courses/{$this->courseA->id}/students")
            ->assertStatus(403);

        Sanctum::actingAs($this->student);
        $this->getJson("/api/instructor/courses/{$this->courseA->id}/students")
            ->assertStatus(403);
    }

    public function test_owning_instructor_gets_approved_students_with_accurate_progress_clamped_per_page_and_no_pii(): void
    {
        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->courseA->id,
            'status' => 'approved',
            'enrolled_at' => now(),
        ]);

        $pendingStudent = User::factory()->create([
            'name' => 'طالب معلق',
            'email' => 'pending@example.com',
        ]);
        $pendingStudent->assignRole('student');

        Enrollment::create([
            'user_id' => $pendingStudent->id,
            'course_id' => $this->courseA->id,
            'status' => 'pending',
            'enrolled_at' => now(),
        ]);

        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lessonA1->id,
            'watched_percent' => 100,
            'completed' => true,
        ]);

        Sanctum::actingAs($this->instructorA);

        $response = $this->getJson("/api/instructor/courses/{$this->courseA->id}/students?per_page=999");

        $response->assertStatus(200)
            ->assertJsonPath('per_page', 50)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->student->id)
            ->assertJsonPath('data.0.name', 'طالب مسجل')
            ->assertJsonPath('data.0.completed_lessons_count', 1)
            ->assertJsonPath('data.0.total_lessons_count', 2)
            ->assertJsonPath('data.0.progress_percent', 50)
            ->assertJsonMissing(['email' => 'student-pii@example.com']);

        $firstItem = $response->json('data.0');
        $this->assertArrayNotHasKey('email', $firstItem);
        $this->assertArrayNotHasKey('phone', $firstItem);
        $this->assertArrayHasKey('enrolled_at', $firstItem);
    }

    public function test_task_endpoints_return_404_when_lesson_has_missing_section_or_course(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE lessons DROP CONSTRAINT IF EXISTS lessons_section_id_foreign');
        } else {
            Schema::disableForeignKeyConstraints();
        }

        $orphanedLessonId = DB::table('lessons')->insertGetId([
            'section_id' => 999999,
            'title' => 'درس يتيم',
            'order' => 1,
            'duration' => 100,
            'video_provider' => 'youtube',
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (DB::getDriverName() !== 'pgsql') {
            Schema::enableForeignKeyConstraints();
        }

        Sanctum::actingAs($this->instructorA);

        $this->getJson("/api/instructor/lessons/{$orphanedLessonId}/task")
            ->assertStatus(404);

        $this->postJson("/api/instructor/lessons/{$orphanedLessonId}/task", [
            'questions' => [
                [
                    'id' => 'q1',
                    'type' => 'mcq',
                    'question' => 'سؤال تجريبي؟',
                    'options' => ['أ', 'ب'],
                    'correct_answer' => 0,
                ],
            ],
            'pass_percentage' => 70,
            'max_attempts' => 3,
        ])->assertStatus(404);

        $this->putJson("/api/instructor/lessons/{$orphanedLessonId}/task", [
            'questions' => [
                [
                    'id' => 'q1',
                    'type' => 'mcq',
                    'question' => 'سؤال محدث؟',
                    'options' => ['أ', 'ب'],
                    'correct_answer' => 0,
                ],
            ],
        ])->assertStatus(404);

        $this->deleteJson("/api/instructor/lessons/{$orphanedLessonId}/task")
            ->assertStatus(404);
    }

    public function test_updating_only_is_published_logs_is_published_diff_and_noop_creates_zero_logs(): void
    {
        Sanctum::actingAs($this->instructorA);

        $this->putJson("/api/instructor/lessons/{$this->lessonA1->id}", [
            'is_published' => false,
        ])->assertStatus(200);

        $log = CourseActivityLog::where('course_id', $this->courseA->id)
            ->where('action', 'lesson_updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayHasKey('is_published', $log->changes);
        $this->assertArrayNotHasKey('title', $log->changes);
        $this->assertSame(['old' => true, 'new' => false], $log->changes['is_published']);

        $countBeforeNoop = CourseActivityLog::count();

        $this->putJson("/api/instructor/lessons/{$this->lessonA1->id}", [
            'title' => $this->lessonA1->title,
            'is_published' => false,
        ])->assertStatus(200);

        $this->assertSame($countBeforeNoop, CourseActivityLog::count());
    }
}
