<?php

declare(strict_types=1);

namespace Tests\Feature\Instructor;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructorCourseTest extends TestCase
{
    use RefreshDatabase;

    private User $instructorA;

    private User $instructorB;

    private User $admin;

    private Course $courseA1;

    private Course $courseA2;

    private Course $courseB1;

    private Course $courseUnassigned;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('instructor', 'web');
        Role::findOrCreate('student', 'web');

        $this->admin = User::factory()->admin()->create();

        // Instructor A
        $this->instructorA = User::factory()->create(['name' => 'المدرب أحمد']);
        $this->instructorA->assignRole('instructor');
        $profileA = $this->instructorA->instructor;

        // Instructor B
        $this->instructorB = User::factory()->create(['name' => 'المدرب بلال']);
        $this->instructorB->assignRole('instructor');
        $profileB = $this->instructorB->instructor;

        // Courses for Instructor A
        $this->courseA1 = Course::factory()->create([
            'instructor_id' => $profileA->id,
            'title' => 'كورس بايثون المتقدم',
            'description' => 'دورة تدريبية مكثفة في لغة بايثون',
            'price' => 150.00,
            'is_public' => true,
            'youtube_playlist_id' => 'PL_SECRET_A1',
        ]);
        $this->courseA2 = Course::factory()->create([
            'instructor_id' => $profileA->id,
            'title' => 'كورس معمارية الأنظمة الموزعة',
            'price' => 250.00,
            'is_public' => false,
            'youtube_playlist_id' => 'PL_SECRET_A2',
        ]);

        // Course for Instructor B
        $this->courseB1 = Course::factory()->create([
            'instructor_id' => $profileB->id,
            'title' => 'كورس أمن المعلومات والشبكات',
            'price' => 300.00,
            'is_public' => true,
            'youtube_playlist_id' => 'PL_SECRET_B1',
        ]);

        // Unassigned Course (Admin Fallback Custody)
        $this->courseUnassigned = Course::factory()->create([
            'instructor_id' => null,
            'title' => 'كورس الذكاء الاصطناعي العام',
            'price' => 199.00,
            'is_public' => false,
            'youtube_playlist_id' => null,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_courses_index(): void
    {
        app('auth')->forgetGuards();

        $response = $this->getJson('/api/instructor/courses');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_course_show(): void
    {
        app('auth')->forgetGuards();

        $response = $this->getJson("/api/instructor/courses/{$this->courseA1->id}");

        $response->assertStatus(401);
    }

    public function test_student_cannot_access_courses_index(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/instructor/courses');

        $response->assertStatus(403);
    }

    public function test_student_cannot_access_course_show(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $response = $this->getJson("/api/instructor/courses/{$this->courseA1->id}");

        $response->assertStatus(403);
    }

    public function test_instructor_only_sees_their_own_assigned_courses(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->getJson('/api/instructor/courses');

        $response->assertOk();

        $courseIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($this->courseA1->id, $courseIds);
        $this->assertContains($this->courseA2->id, $courseIds);
        $this->assertNotContains($this->courseB1->id, $courseIds);
        $this->assertNotContains($this->courseUnassigned->id, $courseIds);
    }

    public function test_unassigned_instructor_receives_empty_array_with_200_ok(): void
    {
        $newInstructor = User::factory()->create(['name' => 'مدرب بدون كورسات']);
        $newInstructor->assignRole('instructor');

        Sanctum::actingAs($newInstructor);

        $response = $this->getJson('/api/instructor/courses');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_admin_can_view_all_platform_courses_on_instructor_courses_index(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/instructor/courses');

        $response->assertOk();

        $courseIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($this->courseA1->id, $courseIds);
        $this->assertContains($this->courseA2->id, $courseIds);
        $this->assertContains($this->courseB1->id, $courseIds);
        $this->assertContains($this->courseUnassigned->id, $courseIds);
    }

    public function test_admin_can_filter_courses_under_admin_custody_via_managed_by_me(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/instructor/courses?managed_by_me=true');

        $response->assertOk();

        $courseIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($this->courseUnassigned->id, $courseIds);
        $this->assertNotContains($this->courseA1->id, $courseIds);
        $this->assertNotContains($this->courseB1->id, $courseIds);
    }

    public function test_instructor_can_view_their_own_assigned_course_details(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->getJson("/api/instructor/courses/{$this->courseA1->id}");

        $response->assertOk()
            ->assertJsonPath('id', $this->courseA1->id)
            ->assertJsonPath('title', 'كورس بايثون المتقدم')
            ->assertJsonPath('description', 'دورة تدريبية مكثفة في لغة بايثون');

        // Stripped sensitive administrative parameters
        $data = $response->json();
        $this->assertArrayNotHasKey('price', $data);
        $this->assertArrayNotHasKey('youtube_playlist_id', $data);
        $this->assertArrayNotHasKey('is_public', $data);
    }

    public function test_instructor_viewing_another_instructors_course_is_rejected_with_403_forbidden(): void
    {
        Sanctum::actingAs($this->instructorA);

        // Instructor A attempts to access Instructor B's course
        $response = $this->getJson("/api/instructor/courses/{$this->courseB1->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_view_any_course_via_instructor_course_show(): void
    {
        Sanctum::actingAs($this->admin);

        $responseA = $this->getJson("/api/instructor/courses/{$this->courseA1->id}");
        $responseA->assertOk();

        $responseB = $this->getJson("/api/instructor/courses/{$this->courseB1->id}");
        $responseB->assertOk();
    }

    public function test_unauthenticated_user_cannot_update_course(): void
    {
        app('auth')->forgetGuards();

        $response = $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'title' => 'عنوان محدث بدون مصادقة',
        ]);

        $response->assertStatus(401);
    }

    public function test_student_cannot_update_course(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $response = $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'title' => 'محاولة طالب لتعديل كورس',
        ]);

        $response->assertStatus(403);
    }

    public function test_cross_tenant_update_attempt_fails_with_403_even_with_empty_or_invalid_payload(): void
    {
        Sanctum::actingAs($this->instructorA);

        // Instructor A attempts to update Instructor B's course with an empty payload
        $response = $this->putJson("/api/instructor/courses/{$this->courseB1->id}", []);

        $response->assertStatus(403);
    }

    public function test_assigned_instructor_can_update_course_educational_metadata(): void
    {
        Sanctum::actingAs($this->instructorA);

        $payload = [
            'title' => 'كورس بايثون المتقدم المحدث 2026',
            'description' => 'دورة تدريبية مكثفة محدثة في لغة بايثون',
            'level' => 'advanced',
            'requirements' => ['إتقان أساسيات البرمجة'],
            'target_audience' => ['مهندسو النظم الموزعة'],
            'what_you_learn' => ['بناء واجهات برمجية آمنة وعالية الأداء'],
            'cover_image' => 'https://example.com/updated-cover.jpg',
        ];

        $response = $this->putJson("/api/instructor/courses/{$this->courseA1->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('title', $payload['title'])
            ->assertJsonPath('description', $payload['description'])
            ->assertJsonPath('level', 'advanced');

        $this->assertDatabaseHas('courses', [
            'id' => $this->courseA1->id,
            'title' => $payload['title'],
            'level' => 'advanced',
            'cover_image' => $payload['cover_image'],
        ]);
    }

    public function test_updating_price_is_prohibited_and_returns_422(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'price' => 999.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('price');
    }

    public function test_updating_youtube_playlist_id_is_prohibited_and_returns_422(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'youtube_playlist_id' => 'PL_NEW_TAMPERED',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('youtube_playlist_id');
    }

    public function test_updating_instructor_id_is_prohibited_and_returns_422(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'instructor_id' => $this->instructorB->instructor->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('instructor_id');
    }

    public function test_updating_is_public_is_prohibited_and_returns_422(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'is_public' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('is_public');
    }

    public function test_updating_status_is_prohibited_and_returns_422(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'status' => 'draft',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_admin_can_update_course_via_instructor_course_update(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'title' => 'تعديل إداري للعنوان التعليمي',
        ]);

        $response->assertOk()
            ->assertJsonPath('title', 'تعديل إداري للعنوان التعليمي');
    }
}
