<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\ZoomAttendance;
use App\Models\ZoomMeeting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ZoomMeetingController extends Controller
{
    /**
     * أرقام الكورسات التي الطالب مقبول فيها (status = approved).
     */
    private function approvedCourseIds(int $userId): array
    {
        return Enrollment::where('user_id', $userId)
            ->where('status', 'approved')
            ->pluck('course_id')
            ->all();
    }

    /**
     * حماية: رفض الطلب إذا كان الطالب غير مسجل أو غير مقبول في كورس اللقاء.
     */
    private function denyIfNotEnrolled(ZoomMeeting $zoomMeeting)
    {
        if (! in_array($zoomMeeting->course_id, $this->approvedCourseIds(Auth::id()), true)) {
            return response()->json(['message' => 'هذا اللقاء غير متاح لك أو لم يتم قبولك بالكورس'], 403);
        }

        return null;
    }

    /**
     * جلب اللقاءات المنتهية الخاصة بكورس محدد منذ تاريخ تسجيل الطالب.
     */
    private function endedMeetings(Enrollment $enrollment)
    {
        return ZoomMeeting::where('course_id', $enrollment->course_id)
            ->where('starts_at', '>=', $enrollment->created_at) // اللقاءات بعد تسجيله فقط
            ->get()
            ->filter(fn (ZoomMeeting $m) => $m->hasEnded());
    }

    /**
     * GET /api/zoom-meetings?month=2026-09
     * لقاءات الشهر للتقويم لكورسات الطالب المقبول فيها فقط.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);

        $start = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $userId = Auth::id();
        $approvedIds = $this->approvedCourseIds($userId);
        if (empty($approvedIds)) {
            return response()->json([]);
        }
        $meetings = ZoomMeeting::with('course:id,title')
            ->whereIn('course_id', $approvedIds)
            ->whereBetween('starts_at', [$start, $end])
            ->orderBy('starts_at')
            ->get();

        $attendances = ZoomAttendance::where('user_id', $userId)
            ->whereIn('zoom_meeting_id', $meetings->pluck('id'))
            ->get()
            ->keyBy('zoom_meeting_id');

        return response()->json(
            $meetings->map(function (ZoomMeeting $m) use ($attendances) {
                $attendance = $attendances->get($m->id);
                $attended = $attendance?->confirmed_at !== null;

                return [
                    'id' => $m->id,
                    'course_id' => $m->course_id,
                    'course_name' => $m->course?->title ?? $m->course_name,
                    'title' => $m->title,
                    'description' => $m->description,
                    'starts_at' => $m->starts_at,
                    'ends_at' => $m->endsAt(),
                    'can_join' => $m->canJoin(),
                    'joined' => $attendance?->attended_at !== null,
                    'attended' => $attended,
                    'code_open' => $attendance?->attended_at !== null && ! $attended && $m->isCodeOpen(),
                ];
            })->values()
        );
    }

    /**
     * POST /api/zoom-meetings/{zoomMeeting}/attend
     * يسجّل أن الطالب ضغط دخول ويرجّع رابط زووم.
     */
    public function attend(ZoomMeeting $zoomMeeting): JsonResponse
    {
        if ($denied = $this->denyIfNotEnrolled($zoomMeeting)) {
            return $denied;
        }

        if ($zoomMeeting->hasEnded()) {
            return response()->json(['message' => 'انتهى اللقاء تماماً'], 403);
        }

        if (! $zoomMeeting->canJoin()) {
            return response()->json(['message' => 'اللقاء لم يبدأ بعد أو غير متاح حالياً'], 403);
        }

        $attendance = ZoomAttendance::firstOrCreate(
            ['zoom_meeting_id' => $zoomMeeting->id, 'user_id' => Auth::id()],
            ['attended_at' => now()]
        );

        if ($attendance->attended_at === null) {
            $attendance->update(['attended_at' => now()]);
        }

        return response()->json(['zoom_link' => $zoomMeeting->zoom_link]);
    }

    /**
     * POST /api/zoom-meetings/{zoomMeeting}/confirm-attendance
     * تأكيد حضور الطالب بإدخال الكود الرقمي المعروض باللقاء.
     */
    public function confirm(Request $request, ZoomMeeting $zoomMeeting): JsonResponse
    {
        if ($denied = $this->denyIfNotEnrolled($zoomMeeting)) {
            return $denied;
        }

        $data = $request->validate(['code' => ['required', 'digits:4']]);

        $attendance = ZoomAttendance::where('zoom_meeting_id', $zoomMeeting->id)
            ->where('user_id', Auth::id())
            ->first();

        if (! $attendance || $attendance->attended_at === null) {
            return response()->json(['message' => 'يجب عليك الدخول للقاء زووم أولاً من خلال الموقع قبل تأكيد الكود'], 422);
        }

        if ($attendance->confirmed_at !== null) {
            return response()->json(['attended' => true]);
        }

        if (! $zoomMeeting->isCodeOpen() || ! hash_equals((string) $zoomMeeting->attendance_code, $data['code'])) {
            return response()->json(['message' => 'الكود المدخل غير صحيح أو انتهت صلاحية اعتماده'], 422);
        }

        $attendance->update(['confirmed_at' => now(), 'confirmation_method' => 'code']);

        return response()->json(['attended' => true]);
    }

    /**
     * GET /api/zoom-meetings/attendance-summary
     * نسبة حضور وغياب الطالب الإجمالية لكل الكورسات المشترك بها.
     */
    public function attendanceSummary(): JsonResponse
    {
        $userId = Auth::id();

        $enrollments = Enrollment::with('course:id,title')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->get();

        $rows = $enrollments->map(function (Enrollment $enrollment) use ($userId) {
            $ended = $this->endedMeetings($enrollment);

            $attended = ZoomAttendance::where('user_id', $userId)
                ->whereNotNull('confirmed_at')
                ->whereIn('zoom_meeting_id', $ended->pluck('id'))
                ->count();

            $total = $ended->count();

            return [
                'course_id' => $enrollment->course_id,
                'course_name' => $enrollment->course?->title,
                'attended' => $attended,
                'missed' => $total - $attended,
                'total_ended' => $total,
                'percent' => $total > 0 ? (int) round($attended * 100 / $total) : null,
            ];
        });

        return response()->json($rows->values());
    }

    /**
     * GET /api/zoom-meetings/attendance?course_id=1
     * سجل تفصيلي يوضح حالة الحضور والغياب في كل اللقاءات السابقة لكورس معين.
     */
    public function attendanceRecord(Request $request): JsonResponse
    {
        $data = $request->validate(['course_id' => ['required', 'integer']]);
        $userId = Auth::id();

        $enrollment = Enrollment::where('user_id', $userId)
            ->where('course_id', $data['course_id'])
            ->where('status', 'approved')
            ->first();

        if (! $enrollment) {
            return response()->json(['message' => 'غير مسجل أو غير مقبول في هذا الكورس'], 403);
        }

        $ended = $this->endedMeetings($enrollment)->sortByDesc('starts_at')->values();

        $attendances = ZoomAttendance::where('user_id', $userId)
            ->whereIn('zoom_meeting_id', $ended->pluck('id'))
            ->get()
            ->keyBy('zoom_meeting_id');

        return response()->json(
            $ended->map(function (ZoomMeeting $m) use ($attendances) {
                $attendance = $attendances->get($m->id);

                return [
                    'id' => $m->id,
                    'title' => $m->title,
                    'starts_at' => $m->starts_at,
                    'ends_at' => $m->endsAt(),
                    'joined_at' => $attendance?->attended_at,
                    'attended' => $attendance?->confirmed_at !== null,
                ];
            })->values()
        );
    }
}
