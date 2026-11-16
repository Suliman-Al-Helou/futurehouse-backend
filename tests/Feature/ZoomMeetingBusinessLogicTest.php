<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\ZoomAttendance;
use App\Models\ZoomMeeting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ZoomMeetingBusinessLogicTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private ZoomMeeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();

        $this->course = Course::create([
            'title' => 'Live Interactive Masterclass',
            'description' => 'Course description',
            'status' => 'published',
            'is_public' => false,
        ]);

        $this->meeting = ZoomMeeting::create([
            'course_id' => $this->course->id,
            'title' => 'Session 1: Architecture',
            'zoom_link' => 'https://zoom.us/j/1234567890',
            'starts_at' => Carbon::parse('2026-06-01 10:00:00', 'UTC'),
            'duration_minutes' => 60,
        ]);
    }

    public function test_meeting_belongs_to_one_course_and_only_its_students_can_access(): void
    {
        $otherCourse = Course::create([
            'title' => 'Other Course',
            'description' => 'Other',
            'status' => 'published',
            'is_public' => false,
        ]);

        $otherStudent = User::factory()->student()->create();

        // Enroll otherStudent into otherCourse only
        Enrollment::create([
            'user_id' => $otherStudent->id,
            'course_id' => $otherCourse->id,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($otherStudent);

        // Cannot view this meeting in calendar
        Carbon::setTestNow(Carbon::parse('2026-06-01 10:10:00', 'UTC'));
        $calendarRes = $this->getJson('/api/zoom-meetings?month=2026-06');
        $calendarRes->assertOk();
        $this->assertEmpty($calendarRes->json());

        // Cannot attend this meeting
        $attendRes = $this->postJson("/api/zoom-meetings/{$this->meeting->id}/attend");
        $attendRes->assertStatus(403);
    }

    public function test_attendance_window_exact_boundaries(): void
    {
        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($this->student);

        // Window opens at starts_at - 15 minutes = 2026-06-01 09:45:00 UTC
        // 16 minutes before: rejected
        Carbon::setTestNow(Carbon::parse('2026-06-01 09:44:00', 'UTC'));
        $this->postJson("/api/zoom-meetings/{$this->meeting->id}/attend")
            ->assertStatus(403)
            ->assertJson(['message' => 'اللقاء لم يبدأ بعد أو غير متاح حالياً']);

        // Exactly 15 minutes before: allowed
        Carbon::setTestNow(Carbon::parse('2026-06-01 09:45:00', 'UTC'));
        $this->postJson("/api/zoom-meetings/{$this->meeting->id}/attend")
            ->assertOk()
            ->assertJsonPath('zoom_link', 'https://zoom.us/j/1234567890');

        // During meeting: allowed
        Carbon::setTestNow(Carbon::parse('2026-06-01 10:30:00', 'UTC'));
        $this->postJson("/api/zoom-meetings/{$this->meeting->id}/attend")
            ->assertOk();

        // Exactly at ends_at (11:00:00): allowed
        Carbon::setTestNow(Carbon::parse('2026-06-01 11:00:00', 'UTC'));
        $this->postJson("/api/zoom-meetings/{$this->meeting->id}/attend")
            ->assertOk();

        // 1 second after ends_at (11:00:01): rejected (ended)
        Carbon::setTestNow(Carbon::parse('2026-06-01 11:00:01', 'UTC'));
        $this->postJson("/api/zoom-meetings/{$this->meeting->id}/attend")
            ->assertStatus(403)
            ->assertJson(['message' => 'انتهى اللقاء تماماً']);
    }

    public function test_idempotent_attendance_recording(): void
    {
        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($this->student);

        Carbon::setTestNow(Carbon::parse('2026-06-01 10:05:00', 'UTC'));

        // First click
        $res1 = $this->postJson("/api/zoom-meetings/{$this->meeting->id}/attend");
        $res1->assertOk();

        $initialAttendance = ZoomAttendance::where('zoom_meeting_id', $this->meeting->id)
            ->where('user_id', $this->student->id)
            ->first();
        $this->assertNotNull($initialAttendance);
        $firstAttendedAt = $initialAttendance->attended_at->toIso8601String();

        // Second click 10 minutes later within the active window
        Carbon::setTestNow(Carbon::parse('2026-06-01 10:15:00', 'UTC'));
        $res2 = $this->postJson("/api/zoom-meetings/{$this->meeting->id}/attend");
        $res2->assertOk();

        // Check count remains exactly 1 and attended_at was not overwritten
        $this->assertSame(1, ZoomAttendance::where('zoom_meeting_id', $this->meeting->id)->where('user_id', $this->student->id)->count());
        $this->assertSame($firstAttendedAt, $initialAttendance->fresh()->attended_at->toIso8601String());
    }

    public function test_admin_manual_attendance_intervention_and_revocation(): void
    {
        $admin = User::factory()->admin()->create();

        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($admin);

        // Initially not attended
        $roster = $this->getJson("/api/admin/zoom-meetings/{$this->meeting->id}/roster");
        $roster->assertOk()
            ->assertJsonPath('0.attended', false);

        // Admin marks attendance as attended
        $this->putJson("/api/admin/zoom-meetings/{$this->meeting->id}/attendances/{$this->student->id}", [
            'attended' => true,
        ])->assertOk()->assertJson(['attended' => true]);

        $this->assertDatabaseHas('zoom_attendances', [
            'zoom_meeting_id' => $this->meeting->id,
            'user_id' => $this->student->id,
        ]);

        // Admin revokes attendance
        $this->putJson("/api/admin/zoom-meetings/{$this->meeting->id}/attendances/{$this->student->id}", [
            'attended' => false,
        ])->assertOk()->assertJson(['attended' => false]);

        $this->assertDatabaseMissing('zoom_attendances', [
            'zoom_meeting_id' => $this->meeting->id,
            'user_id' => $this->student->id,
        ]);
    }
}
