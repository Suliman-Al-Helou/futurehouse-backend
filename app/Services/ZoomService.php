<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\ZoomAttendance;
use App\Models\ZoomMeeting;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ZoomService
{
    /**
     * Creates a Zoom meeting, auto-filling course_name from the course title.
     */
    public function create(array $data): ZoomMeeting
    {
        $data['course_name'] = Course::findOrFail($data['course_id'])->title;
        $data['duration_minutes'] = $data['duration_minutes'] ?? 60;

        return ZoomMeeting::create($data);
    }

    /**
     * Updates a meeting; preserves duration_minutes when not provided.
     */
    public function update(ZoomMeeting $meeting, array $data): ZoomMeeting
    {
        $data['course_name'] = Course::findOrFail($data['course_id'])->title;

        // Keep the stored duration when the caller omits it
        if (! isset($data['duration_minutes'])) {
            unset($data['duration_minutes']);
        }

        $meeting->update($data);

        return $meeting->refresh();
    }

    /**
     * Returns all approved students in the meeting's course with their attendance status.
     */
    public function roster(ZoomMeeting $meeting): Collection
    {
        $studentIds = Enrollment::where('course_id', $meeting->course_id)
            ->where('status', 'approved')
            ->pluck('user_id');

        $students = User::whereIn('id', $studentIds)->orderBy('name')->get(['id', 'name', 'email']);
        $attendances = $meeting->attendances()->get()->keyBy('user_id');

        return $students->map(function (User $user) use ($attendances) {
            $att = $attendances->get($user->id);

            return [
                'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                'attended_at' => $att?->attended_at,
                'attended' => $att?->attended_at !== null,
            ];
        })->values();
    }

    /**
     * Manually confirms or revokes a student's attendance.
     * Throws 422 if the student is not enrolled and approved in the course.
     */
    public function setAttendance(ZoomMeeting $meeting, User $user, bool $attended): void
    {
        $enrolled = Enrollment::where('user_id', $user->id)
            ->where('course_id', $meeting->course_id)
            ->where('status', 'approved')
            ->exists();

        if (! $enrolled) {
            throw ValidationException::withMessages([
                'user' => 'الطالب غير مقبول في كورس هذا اللقاء',
            ]);
        }

        $attendance = ZoomAttendance::where('zoom_meeting_id', $meeting->id)
            ->where('user_id', $user->id)
            ->first();

        if ($attended) {
            $attendance ??= new ZoomAttendance([
                'zoom_meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);
            $attendance->fill(['attended_at' => now()])->save();
        } elseif ($attendance) {
            $attendance->delete();
        }
    }
}
