<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\ZoomMeeting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StateTransitionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->student = User::factory()->student()->create();

        $this->course = Course::create([
            'title' => 'Transition Test Course',
            'description' => 'Test',
            'is_public' => false,
            'price' => 100,
            'status' => 'published',
            'students_count' => 0,
        ]);
    }

    public function test_cannot_transition_rejected_enrollment_to_approved(): void
    {
        Sanctum::actingAs($this->admin);

        $enrollment = Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'rejected',
        ]);

        $response = $this->patchJson("/api/admin/enrollments/{$enrollment->id}", [
            'status' => 'approved',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertSame('rejected', $enrollment->fresh()->status);
    }

    public function test_cannot_transition_approved_enrollment_back_to_pending(): void
    {
        Sanctum::actingAs($this->admin);

        $enrollment = Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
        ]);
        $this->course->update(['students_count' => 1]);

        $response = $this->patchJson("/api/admin/enrollments/{$enrollment->id}", [
            'status' => 'pending',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertSame('approved', $enrollment->fresh()->status);
    }

    public function test_cannot_transition_free_course_enrollment_status(): void
    {
        Sanctum::actingAs($this->admin);

        $freeCourse = Course::create([
            'title' => 'Free Course',
            'description' => 'Free',
            'is_public' => true,
            'price' => 0,
            'status' => 'published',
        ]);

        $enrollment = Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $freeCourse->id,
            'status' => 'pending',
        ]);

        $response = $this->patchJson("/api/admin/enrollments/{$enrollment->id}", [
            'status' => 'approved',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cannot_attend_ended_meeting_rejects_transition(): void
    {
        Sanctum::actingAs($this->student);

        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
        ]);

        $meeting = ZoomMeeting::create([
            'course_id' => $this->course->id,
            'title' => 'Expired Session',
            'zoom_link' => 'https://zoom.us/j/9876543210',
            'starts_at' => Carbon::parse('2026-06-01 10:00:00', 'UTC'),
            'duration_minutes' => 60,
        ]);

        // Attempting attendance when meeting has ended (e.g. 11:30)
        Carbon::setTestNow(Carbon::parse('2026-06-01 11:30:00', 'UTC'));

        $this->postJson("/api/zoom-meetings/{$meeting->id}/attend")
            ->assertStatus(403)
            ->assertJson(['message' => 'انتهى اللقاء تماماً']);

        $this->assertDatabaseMissing('zoom_attendances', [
            'zoom_meeting_id' => $meeting->id,
            'user_id' => $this->student->id,
        ]);
    }
}
