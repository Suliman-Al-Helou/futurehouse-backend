<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_student_can_enroll_in_paid_course(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $course = Course::create([
            'title' => 'Paid Course',
            'description' => 'Course description for enrollment',
            'is_public' => false,
            'price' => 100,
            'status' => 'published',
        ]);

        $response = $this->postJson("/api/courses/{$course->id}/enroll");

        $response->assertCreated()
            ->assertJson([
                'message' => 'تم إرسال طلب الاشتراك، بانتظار موافقة الإدارة',
            ]);

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'pending',
            'deleted_at' => null,
        ]);
    }

    public function test_student_cannot_enroll_in_free_course(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $course = Course::create([
            'title' => 'Free Course',
            'description' => 'Course description for free course',
            'is_public' => true,
            'price' => 0,
            'status' => 'published',
        ]);

        $this->postJson("/api/courses/{$course->id}/enroll")
            ->assertForbidden();
    }

    public function test_student_can_unenroll_which_soft_deletes_pending_enrollment(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $course = Course::create([
            'title' => 'Course to Unenroll',
            'description' => 'Desc',
            'is_public' => false,
            'students_count' => 0,
        ]);

        $enrollment = Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'pending',
        ]);

        $response = $this->deleteJson("/api/courses/{$course->id}/enroll");

        $response->assertOk()
            ->assertJson(['message' => 'تم سحب التسجيل بنجاح']);

        $this->assertSoftDeleted('enrollments', [
            'id' => $enrollment->id,
        ]);

        $this->assertSame(0, $course->fresh()->students_count);
    }

    public function test_unenrolling_approved_enrollment_soft_deletes_and_decrements_students_count(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $course = Course::create([
            'title' => 'Course with Approved Student',
            'description' => 'Desc',
            'is_public' => false,
            'students_count' => 1,
        ]);

        $enrollment = Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'approved',
        ]);

        $response = $this->deleteJson("/api/courses/{$course->id}/enroll");

        $response->assertOk()
            ->assertJson(['message' => 'تم سحب التسجيل بنجاح']);

        $this->assertSoftDeleted('enrollments', [
            'id' => $enrollment->id,
        ]);

        $this->assertSame(0, $course->fresh()->students_count);
    }

    public function test_unenrolling_approved_enrollment_does_not_decrement_below_zero(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $course = Course::create([
            'title' => 'Course with Zero Counter',
            'description' => 'Desc',
            'is_public' => false,
            'students_count' => 0,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'approved',
        ]);

        $this->deleteJson("/api/courses/{$course->id}/enroll")->assertOk();

        $this->assertSame(0, $course->fresh()->students_count);
    }

    public function test_re_enrolling_after_unenroll_restores_soft_deleted_record(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $course = Course::create([
            'title' => 'Re-enroll Course',
            'description' => 'Desc',
            'is_public' => false,
            'students_count' => 0,
        ]);

        $enrollment = Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'approved',
        ]);

        // Student unenrolls -> soft deletes
        $this->deleteJson("/api/courses/{$course->id}/enroll")->assertOk();
        $this->assertSoftDeleted('enrollments', ['id' => $enrollment->id]);

        // Student re-enrolls -> should restore record, set status pending, and set deleted_at null
        $response = $this->postJson("/api/courses/{$course->id}/enroll");
        $response->assertCreated();

        $this->assertNotSoftDeleted('enrollments', ['id' => $enrollment->id]);
        $restored = Enrollment::findOrFail($enrollment->id);
        $this->assertSame('pending', $restored->status);
        $this->assertNull($restored->deleted_at);
    }

    public function test_check_enrollment_status(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $course = Course::create([
            'title' => 'Check Status Course',
            'description' => 'Desc',
            'is_public' => false,
        ]);

        // Not enrolled
        $this->getJson("/api/courses/{$course->id}/enrollment")
            ->assertOk()
            ->assertJson([
                'enrolled' => false,
                'status' => null,
                'can_watch' => false,
                'can_enroll' => true,
            ]);

        // Pending
        $enrollment = Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'pending',
        ]);

        $this->getJson("/api/courses/{$course->id}/enrollment")
            ->assertOk()
            ->assertJson([
                'enrolled' => false,
                'status' => 'pending',
                'can_watch' => false,
                'can_enroll' => false,
            ]);

        // Approved
        $enrollment->update(['status' => 'approved']);
        $this->getJson("/api/courses/{$course->id}/enrollment")
            ->assertOk()
            ->assertJson([
                'enrolled' => true,
                'status' => 'approved',
                'can_watch' => true,
                'can_enroll' => false,
            ]);

        // Soft-deleted enrollment (unenrolled)
        $enrollment->delete();
        $this->getJson("/api/courses/{$course->id}/enrollment")
            ->assertOk()
            ->assertJson([
                'enrolled' => false,
                'status' => null,
                'can_watch' => false,
                'can_enroll' => true,
            ]);
    }

    public function test_my_courses_excludes_soft_deleted_enrollments(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $activeCourse = Course::create(['title' => 'Active Course', 'description' => 'Desc']);
        $deletedCourse = Course::create(['title' => 'Deleted Course', 'description' => 'Desc']);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $activeCourse->id,
            'status' => 'approved',
        ]);

        $softDeleted = Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $deletedCourse->id,
            'status' => 'approved',
        ]);
        $softDeleted->delete();

        $response = $this->getJson('/api/my-courses');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.course_id', $activeCourse->id);
    }

    public function test_unenroll_when_not_enrolled_returns_success(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $course = Course::create(['title' => 'Course Without Enrollment', 'description' => 'Desc']);

        $this->deleteJson("/api/courses/{$course->id}/enroll")
            ->assertOk()
            ->assertJson(['message' => 'تم سحب التسجيل بنجاح']);
    }

    public function test_deleted_at_is_not_fillable_on_enrollment_model(): void
    {
        $enrollment = new Enrollment;
        $this->assertNotContains('deleted_at', $enrollment->getFillable());
    }
}
