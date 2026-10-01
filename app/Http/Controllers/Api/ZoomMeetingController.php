<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ZoomAttendance;
use App\Models\ZoomMeeting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ZoomMeetingController extends Controller
{
    /**
     * GET /api/zoom-meetings?month=2026-09
     * لقاءات الشهر للتقويم. لا يرجّع zoom_link أبدًا.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);

        $start = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))
            ->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $userId = Auth::id();

        $meetings = ZoomMeeting::whereBetween('starts_at', [$start, $end])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (ZoomMeeting $m) => [
                'id'          => $m->id,
                'course_name' => $m->course_name,
                'title'       => $m->title,
                'description' => $m->description,
                'starts_at'   => $m->starts_at,
                'can_join'    => $m->canJoin(),
            ]);

        $attended = ZoomAttendance::where('user_id', $userId)
            ->whereIn('zoom_meeting_id', $meetings->pluck('id'))
            ->pluck('zoom_meeting_id')
            ->all();

        return response()->json(
            $meetings->map(fn ($m) => $m + ['attended' => in_array($m['id'], $attended, true)])->values()
        );
    }

    /**
     * POST /api/zoom-meetings/{zoomMeeting}/attend
     * يسجّل الحضور ويرجّع الرابط (الرابط يظهر هنا فقط).
     */
    public function attend(ZoomMeeting $zoomMeeting): JsonResponse
    {
        if (! $zoomMeeting->canJoin()) {
            return response()->json(['message' => 'اللقاء لم يبدأ بعد'], 403);
        }

        ZoomAttendance::firstOrCreate(
            ['zoom_meeting_id' => $zoomMeeting->id, 'user_id' => Auth::id()],
            ['attended_at' => now()]
        );

        return response()->json(['zoom_link' => $zoomMeeting->zoom_link]);
    }
}
