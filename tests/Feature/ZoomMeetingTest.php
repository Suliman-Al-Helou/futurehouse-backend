<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\ZoomAttendance;
use App\Models\ZoomMeeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ZoomMeetingTest extends TestCase
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
            'title' => 'Zoom Course',
            'description' => 'Course with zoom sessions',
            'status' => 'published',
        ]);

        $this->meeting = ZoomMeeting::create([
            'course_id' => $this->course->id,
            'title' => 'Weekly Live Session',
            'zoom_link' => 'https://zoom.us/j/1234567890',
            'starts_at' => now()->subMinutes(5), // Currently active
            'duration_minutes' => 60,
        ]);
    }

    public function test_zoom_schema_has_all_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('zoom_meetings', [
            'id',
            'course_id',
            'title',
            'zoom_link',
            'starts_at',
            'duration_minutes',
        ]));

        $this->assertTrue(Schema::hasColumns('zoom_attendances', [
            'id',
            'zoom_meeting_id',
            'user_id',
            'attended_at',
        ]));

        // Ensure code columns are removed
        $this->assertFalse(Schema::hasColumn('zoom_meetings', 'attendance_code'));
        $this->assertFalse(Schema::hasColumn('zoom_meetings', 'attendance_code_expires_at'));
        $this->assertFalse(Schema::hasColumn('zoom_attendances', 'confirmed_at'));
        $this->assertFalse(Schema::hasColumn('zoom_attendances', 'confirmation_method'));
    }

    public function test_guest_cannot_attend_meeting(): void
    {
        $this->postJson("/api/zoom-meetings/{$this->meeting->id}/attend")->assertUnauthorized();
    }

    public function test_unenrolled_student_cannot_attend(): void
    {
        Sanctum::actingAs($this->student);

        $this->postJson("/api/zoom-meetings/{$this->meeting->id}/attend")->assertStatus(403);
    }

    public function test_enrolled_student_clicking_join_records_attendance(): void
    {
        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($this->student);

        $response = $this->postJson("/api/zoom-meetings/{$this->meeting->id}/attend");

        $response->assertOk()
            ->assertJsonPath('zoom_link', 'https://zoom.us/j/1234567890');

        $this->assertDatabaseHas('zoom_attendances', [
            'zoom_meeting_id' => $this->meeting->id,
            'user_id' => $this->student->id,
        ]);

        $attendance = ZoomAttendance::where('zoom_meeting_id', $this->meeting->id)
            ->where('user_id', $this->student->id)
            ->first();

        $this->assertNotNull($attendance->attended_at);

        // Repeated clicks are idempotent and return the link
        $this->postJson("/api/zoom-meetings/{$this->meeting->id}/attend")
            ->assertOk()
            ->assertJsonPath('zoom_link', 'https://zoom.us/j/1234567890');

        $this->assertCount(1, ZoomAttendance::where('zoom_meeting_id', $this->meeting->id)->get());
    }

    public function test_student_cannot_attend_if_meeting_has_ended(): void
    {
        $endedMeeting = ZoomMeeting::create([
            'course_id' => $this->course->id,
            'title' => 'Past Session',
            'zoom_link' => 'https://zoom.us/j/9999999999',
            'starts_at' => now()->subHours(3),
            'duration_minutes' => 60,
        ]);

        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($this->student);

        $this->postJson("/api/zoom-meetings/{$endedMeeting->id}/attend")
            ->assertStatus(403)
            ->assertJson(['message' => 'انتهى اللقاء تماماً']);
    }

    public function test_student_cannot_attend_before_join_window_opens(): void
    {
        $futureMeeting = ZoomMeeting::create([
            'course_id' => $this->course->id,
            'title' => 'Future Session',
            'zoom_link' => 'https://zoom.us/j/8888888888',
            'starts_at' => now()->addHours(2),
            'duration_minutes' => 60,
        ]);

        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($this->student);

        $this->postJson("/api/zoom-meetings/{$futureMeeting->id}/attend")
            ->assertStatus(403)
            ->assertJson(['message' => 'اللقاء لم يبدأ بعد أو غير متاح حالياً']);
    }

    public function test_attendance_summary_and_record_reflect_attendance(): void
    {
        $enrollment = Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
        ]);
        $enrollment->forceFill(['created_at' => now()->subDays(2)])->save();

        $pastMeeting = ZoomMeeting::create([
            'course_id' => $this->course->id,
            'title' => 'Yesterday Session',
            'zoom_link' => 'https://zoom.us/j/1111111111',
            'starts_at' => now()->subHours(2),
            'duration_minutes' => 60,
        ]);

        ZoomAttendance::create([
            'zoom_meeting_id' => $pastMeeting->id,
            'user_id' => $this->student->id,
            'attended_at' => now()->subHours(2),
        ]);

        Sanctum::actingAs($this->student);

        $summaryRes = $this->getJson('/api/zoom-meetings/attendance-summary');
        $summaryRes->assertOk();
        $this->assertEquals(1, $summaryRes->json('0.attended'));

        $recordRes = $this->getJson("/api/zoom-meetings/attendance?course_id={$this->course->id}");
        $recordRes->assertOk();
        $this->assertTrue($recordRes->json('0.attended'));
    }

    public function test_admin_can_view_roster_and_set_attendance(): void
    {
        $admin = User::factory()->admin()->create();

        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($admin);

        // Admin checks roster
        $rosterRes = $this->getJson("/api/admin/zoom-meetings/{$this->meeting->id}/roster");
        $rosterRes->assertOk();
        $this->assertFalse($rosterRes->json('0.attended'));

        // Admin marks attendance manually
        $setRes = $this->putJson("/api/admin/zoom-meetings/{$this->meeting->id}/attendances/{$this->student->id}", [
            'attended' => true,
        ]);
        $setRes->assertOk()->assertJson(['attended' => true]);

        $this->assertDatabaseHas('zoom_attendances', [
            'zoom_meeting_id' => $this->meeting->id,
            'user_id' => $this->student->id,
        ]);

        // Verify roster now shows attended
        $rosterRes2 = $this->getJson("/api/admin/zoom-meetings/{$this->meeting->id}/roster");
        $rosterRes2->assertOk();
        $this->assertTrue($rosterRes2->json('0.attended'));
    }
}
