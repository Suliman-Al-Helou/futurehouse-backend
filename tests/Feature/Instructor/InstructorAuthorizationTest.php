<?php

declare(strict_types=1);

namespace Tests\Feature\Instructor;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use App\Models\UserLessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructorAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $instructorA;

    private User $instructorB;

    private User $admin;

    private User $student;

    private Course $courseA;

    private Section $sectionA;

    private Lesson $lessonA;

    private Lesson $unstudiedLessonA;

    private Course $courseB;

    private Section $sectionB;

    private Lesson $lessonB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('instructor', 'web');
        Role::findOrCreate('student', 'web');

        $this->admin = User::factory()->admin()->create();
        $this->student = User::factory()->student()->create();

        // Instructor A & Course A
        $this->instructorA = User::factory()->create(['name' => 'المدرب الأول']);
        $this->instructorA->assignRole('instructor');
        $this->courseA = Course::factory()->create([
            'instructor_id' => $this->instructorA->instructor->id,
            'title' => 'كورس الأمان المتقدم A',
            'price' => 200.00,
        ]);
        $this->sectionA = Section::factory()->create([
            'course_id' => $this->courseA->id,
            'title' => 'الفصل الأول A',
            'order' => 1,
        ]);
        $this->lessonA = Lesson::factory()->create([
            'section_id' => $this->sectionA->id,
            'title' => 'الدرس الأول A (مكتمل من طالب)',
            'order' => 1,
        ]);
        $this->unstudiedLessonA = Lesson::factory()->create([
            'section_id' => $this->sectionA->id,
            'title' => 'درس جديد A غير مدروس',
            'order' => 2,
        ]);

        // Recorded student progress on lessonA
        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lessonA->id,
            'completed' => true,
        ]);

        // Instructor B & Course B
        $this->instructorB = User::factory()->create(['name' => 'المدرب الثاني']);
        $this->instructorB->assignRole('instructor');
        $this->courseB = Course::factory()->create([
            'instructor_id' => $this->instructorB->instructor->id,
            'title' => 'كورس النظم الموزعة B',
            'price' => 300.00,
        ]);
        $this->sectionB = Section::factory()->create([
            'course_id' => $this->courseB->id,
            'title' => 'الفصل الأول B',
            'order' => 1,
        ]);
        $this->lessonB = Lesson::factory()->create([
            'section_id' => $this->sectionB->id,
            'title' => 'الدرس الأول B',
            'order' => 1,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 1. Role Hierarchy & Global Gateway Tests (Guest & Student)
    // ─────────────────────────────────────────────────────────────

    public function test_unauthenticated_requests_to_instructor_curriculum_are_rejected_with_401(): void
    {
        app('auth')->forgetGuards();

        $this->getJson("/api/instructor/courses/{$this->courseA->id}/sections")->assertStatus(401);
        $this->postJson("/api/instructor/courses/{$this->courseA->id}/sections", ['title' => 'قسم جديد'])->assertStatus(401);
        $this->putJson("/api/instructor/sections/{$this->sectionA->id}", ['title' => 'تعديل قسم'])->assertStatus(401);
        $this->deleteJson("/api/instructor/sections/{$this->sectionA->id}")->assertStatus(401);

        $this->postJson("/api/instructor/sections/{$this->sectionA->id}/lessons", ['title' => 'درس جديد'])->assertStatus(401);
        $this->putJson("/api/instructor/lessons/{$this->lessonA->id}", ['title' => 'تعديل درس'])->assertStatus(401);
        $this->deleteJson("/api/instructor/lessons/{$this->lessonA->id}")->assertStatus(401);
    }

    public function test_student_requests_to_instructor_curriculum_are_rejected_with_403(): void
    {
        Sanctum::actingAs($this->student);

        $this->getJson("/api/instructor/courses/{$this->courseA->id}/sections")->assertStatus(403);
        $this->postJson("/api/instructor/courses/{$this->courseA->id}/sections", ['title' => 'قسم جديد'])->assertStatus(403);
        $this->putJson("/api/instructor/sections/{$this->sectionA->id}", ['title' => 'تعديل قسم'])->assertStatus(403);
        $this->deleteJson("/api/instructor/sections/{$this->sectionA->id}")->assertStatus(403);

        $this->postJson("/api/instructor/sections/{$this->sectionA->id}/lessons", ['title' => 'درس جديد'])->assertStatus(403);
        $this->putJson("/api/instructor/lessons/{$this->lessonA->id}", ['title' => 'تعديل درس'])->assertStatus(403);
        $this->deleteJson("/api/instructor/lessons/{$this->lessonA->id}")->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────
    // 2. Multi-Tenant IDOR Matrix (Instructor A vs Course B)
    // ─────────────────────────────────────────────────────────────

    public function test_instructor_a_cannot_view_sections_of_course_b(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->getJson("/api/instructor/courses/{$this->courseB->id}/sections");
        $response->assertStatus(403);
    }

    public function test_instructor_a_cannot_create_section_in_course_b(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->postJson("/api/instructor/courses/{$this->courseB->id}/sections", [
            'title' => 'محاولة اختراق قسم في كورس B',
        ]);
        $response->assertStatus(403);
    }

    public function test_instructor_a_cannot_update_section_belonging_to_course_b(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->putJson("/api/instructor/sections/{$this->sectionB->id}", [
            'title' => 'تعديل قسم في كورس B',
        ]);
        $response->assertStatus(403);
    }

    public function test_instructor_a_cannot_delete_section_belonging_to_course_b(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->deleteJson("/api/instructor/sections/{$this->sectionB->id}");
        $response->assertStatus(403);
    }

    public function test_instructor_a_cannot_create_lesson_in_section_of_course_b(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->postJson("/api/instructor/sections/{$this->sectionB->id}/lessons", [
            'title' => 'درس مخترق في كورس B',
        ]);
        $response->assertStatus(403);
    }

    public function test_instructor_a_cannot_update_lesson_belonging_to_course_b(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->putJson("/api/instructor/lessons/{$this->lessonB->id}", [
            'title' => 'تعديل درس في كورس B',
        ]);
        $response->assertStatus(403);
    }

    public function test_instructor_a_cannot_delete_lesson_belonging_to_course_b(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->deleteJson("/api/instructor/lessons/{$this->lessonB->id}");
        $response->assertStatus(403);
    }

    public function test_cross_tenant_probe_with_invalid_payload_returns_403_before_validation(): void
    {
        Sanctum::actingAs($this->instructorA);

        // Sending empty body to mutation endpoints on Course B must return 403, NOT 422
        $this->postJson("/api/instructor/courses/{$this->courseB->id}/sections", [])->assertStatus(403);
        $this->putJson("/api/instructor/sections/{$this->sectionB->id}", [])->assertStatus(403);
        $this->postJson("/api/instructor/sections/{$this->sectionB->id}/lessons", [])->assertStatus(403);
        $this->putJson("/api/instructor/lessons/{$this->lessonB->id}", [])->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────
    // 3. Authorized Instructor Actions on Assigned Course A
    // ─────────────────────────────────────────────────────────────

    public function test_instructor_a_can_view_curriculum_sections_tree_of_course_a(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->getJson("/api/instructor/courses/{$this->courseA->id}/sections");

        $response->assertOk()
            ->assertJsonStructure(['sections' => [['id', 'title', 'order', 'lessons']]]);
    }

    public function test_instructor_a_can_create_section_under_course_a(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->postJson("/api/instructor/courses/{$this->courseA->id}/sections", [
            'title' => 'الفصل الثاني المتقدم',
            'order' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonPath('title', 'الفصل الثاني المتقدم');

        $this->assertDatabaseHas('sections', [
            'course_id' => $this->courseA->id,
            'title' => 'الفصل الثاني المتقدم',
        ]);
    }

    public function test_instructor_a_can_update_section_under_course_a(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->putJson("/api/instructor/sections/{$this->sectionA->id}", [
            'title' => 'الفصل الأول A المحدث',
            'order' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('title', 'الفصل الأول A المحدث');

        $this->assertDatabaseHas('sections', [
            'id' => $this->sectionA->id,
            'title' => 'الفصل الأول A المحدث',
            'order' => 5,
        ]);
    }

    public function test_instructor_a_can_create_lesson_under_section_a(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->postJson("/api/instructor/sections/{$this->sectionA->id}/lessons", [
            'title' => 'الدرس الجديد في الفصل A',
            'description' => 'شرح تفصيلي للمفاهيم',
            'duration' => 15,
            'order' => 3,
            'is_preview' => false,
            'is_published' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('title', 'الدرس الجديد في الفصل A');

        $this->assertDatabaseHas('lessons', [
            'section_id' => $this->sectionA->id,
            'title' => 'الدرس الجديد في الفصل A',
        ]);
    }

    public function test_instructor_a_can_update_lesson_under_section_a(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->putJson("/api/instructor/lessons/{$this->unstudiedLessonA->id}", [
            'title' => 'عنوان الدرس المحدث',
            'description' => 'وصف تعليمي محدث بالكامل',
            'duration' => 25,
            'is_preview' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('title', 'عنوان الدرس المحدث');

        $this->assertDatabaseHas('lessons', [
            'id' => $this->unstudiedLessonA->id,
            'title' => 'عنوان الدرس المحدث',
            'duration' => 25,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 4. Curriculum Deletion Integrity Guard
    // ─────────────────────────────────────────────────────────────

    public function test_deleting_lesson_with_completed_student_progress_fails_with_422(): void
    {
        Sanctum::actingAs($this->instructorA);

        // lessonA has recorded student completion
        $response = $this->deleteJson("/api/instructor/lessons/{$this->lessonA->id}");

        $response->assertStatus(422);

        $this->assertDatabaseHas('lessons', [
            'id' => $this->lessonA->id,
        ]);
    }

    public function test_deleting_unstudied_lesson_succeeds_with_204(): void
    {
        Sanctum::actingAs($this->instructorA);

        // unstudiedLessonA has 0 student progress records
        $response = $this->deleteJson("/api/instructor/lessons/{$this->unstudiedLessonA->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('lessons', [
            'id' => $this->unstudiedLessonA->id,
        ]);
    }

    public function test_deleting_section_containing_completed_lessons_fails_with_422(): void
    {
        Sanctum::actingAs($this->instructorA);

        // sectionA contains lessonA which has completed progress
        $response = $this->deleteJson("/api/instructor/sections/{$this->sectionA->id}");

        $response->assertStatus(422);

        $this->assertDatabaseHas('sections', [
            'id' => $this->sectionA->id,
        ]);
    }

    public function test_deleting_section_containing_uncompleted_lessons_fails_with_422(): void
    {
        Sanctum::actingAs($this->instructorA);

        $section = Section::factory()->create([
            'course_id' => $this->courseA->id,
            'title' => 'فصل يحتوي على دروس غير مكتملة',
        ]);
        \App\Models\Lesson::factory()->create([
            'section_id' => $section->id,
            'title' => 'درس نشط بدون تقدم بعد',
        ]);

        $response = $this->deleteJson("/api/instructor/sections/{$section->id}");

        $response->assertStatus(422);

        $this->assertDatabaseHas('sections', [
            'id' => $section->id,
        ]);
    }

    public function test_deleting_unstudied_empty_section_succeeds_with_204(): void
    {
        Sanctum::actingAs($this->instructorA);

        $emptySection = Section::factory()->create([
            'course_id' => $this->courseA->id,
            'title' => 'فصل فارغ غير مدروس',
        ]);

        $response = $this->deleteJson("/api/instructor/sections/{$emptySection->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('sections', [
            'id' => $emptySection->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 5. Admin Supervisory Override
    // ─────────────────────────────────────────────────────────────

    public function test_admin_can_manage_sections_and_lessons_across_all_courses(): void
    {
        Sanctum::actingAs($this->admin);

        // Admin accessing course B curriculum
        $this->getJson("/api/instructor/courses/{$this->courseB->id}/sections")->assertOk();

        // Admin adding section to course B
        $response = $this->postJson("/api/instructor/courses/{$this->courseB->id}/sections", [
            'title' => 'فصل إشرافي جديد من الأدمن',
        ]);
        $response->assertCreated();

        // Admin updating section B
        $this->putJson("/api/instructor/sections/{$this->sectionB->id}", [
            'title' => 'تعديل إشرافي للقسم B',
        ])->assertOk();

        // Admin adding lesson under section B
        $this->postJson("/api/instructor/sections/{$this->sectionB->id}/lessons", [
            'title' => 'درس إشرافي جديد من الأدمن',
        ])->assertCreated();
    }
}
