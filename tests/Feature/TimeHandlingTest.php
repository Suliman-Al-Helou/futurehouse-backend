<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\ZoomMeeting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimeHandlingTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->student = User::factory()->student()->create();
        $this->course = Course::create([
            'title' => 'Time Test Course',
            'description' => 'Course description',
            'is_public' => false,
            'status' => 'published',
        ]);
    }

    public function test_timestamps_are_persisted_in_utc(): void
    {
        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
        ]);

        // Freeze time at specific UTC moment
        $fixedUtc = Carbon::parse('2026-10-15 14:30:00', 'UTC');
        Carbon::setTestNow($fixedUtc);

        $meeting = ZoomMeeting::create([
            'course_id' => $this->course->id,
            'title' => 'Timezone Verification Meeting',
            'zoom_link' => 'https://zoom.us/j/1231231234',
            'starts_at' => $fixedUtc,
            'duration_minutes' => 90,
        ]);

        Sanctum::actingAs($this->student);
        $this->postJson("/api/zoom-meetings/{$meeting->id}/attend")->assertOk();

        // Query raw database values via DB facade to check storage
        $rawMeeting = DB::table('zoom_meetings')->where('id', $meeting->id)->first();
        $rawAttendance = DB::table('zoom_attendances')->where('zoom_meeting_id', $meeting->id)->first();

        $this->assertNotNull($rawMeeting);
        $this->assertNotNull($rawAttendance);

        // Verify stored starts_at matches the UTC time
        $this->assertSame('2026-10-15 14:30:00', Carbon::parse($rawMeeting->starts_at)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-15 14:30:00', Carbon::parse($rawAttendance->attended_at)->format('Y-m-d H:i:s'));
    }

    public function test_time_travel_simulates_active_and_expired_states(): void
    {
        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
        ]);

        $startsAt = Carbon::parse('2026-12-01 18:00:00', 'UTC');
        $meeting = ZoomMeeting::create([
            'course_id' => $this->course->id,
            'title' => 'Time Travel Session',
            'zoom_link' => 'https://zoom.us/j/999888777',
            'starts_at' => $startsAt,
            'duration_minutes' => 60,
        ]);

        Sanctum::actingAs($this->student);

        // 1. One day before: cannot attend, canJoin is false
        $this->travelTo(Carbon::parse('2026-11-30 18:00:00', 'UTC'));
        $this->assertFalse($meeting->canJoin());
        $this->assertFalse($meeting->hasEnded());
        $this->postJson("/api/zoom-meetings/{$meeting->id}/attend")->assertStatus(403);

        // 2. 10 minutes before: canJoin is true, can attend
        $this->travelTo(Carbon::parse('2026-12-01 17:50:00', 'UTC'));
        $this->assertTrue($meeting->canJoin());
        $this->assertFalse($meeting->hasEnded());
        $this->postJson("/api/zoom-meetings/{$meeting->id}/attend")->assertOk();

        // 3. 2 hours after: hasEnded is true, canJoin is false, cannot attend
        $this->travelTo(Carbon::parse('2026-12-01 20:00:00', 'UTC'));
        $this->assertTrue($meeting->hasEnded());
        $this->assertFalse($meeting->canJoin());
        $this->postJson("/api/zoom-meetings/{$meeting->id}/attend")->assertStatus(403);
    }
}
