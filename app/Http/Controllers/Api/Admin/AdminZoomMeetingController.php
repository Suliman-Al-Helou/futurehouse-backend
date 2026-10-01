<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ZoomMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminZoomMeetingController extends Controller
{
    private function rules(): array
    {
        return [
            'course_name' => 'required|string|max:255',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'zoom_link'   => 'required|url|max:2048',
            'starts_at'   => 'required|date',
        ];
    }

    // قائمة كل اللقاءات (الأحدث أولًا) مع عدد الحاضرين
    public function index(): JsonResponse
    {
        return response()->json(
            ZoomMeeting::withCount('attendances')->orderByDesc('starts_at')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $meeting = ZoomMeeting::create($request->validate($this->rules()));

        return response()->json($meeting, 201);
    }

    public function update(Request $request, ZoomMeeting $zoomMeeting): JsonResponse
    {
        $zoomMeeting->update($request->validate($this->rules()));

        return response()->json($zoomMeeting);
    }

    public function destroy(ZoomMeeting $zoomMeeting): JsonResponse
    {
        $zoomMeeting->delete(); // cascade يحذف سجلات الحضور

        return response()->json(['message' => 'تم حذف اللقاء']);
    }

    // من حضر هذا اللقاء
    public function attendances(ZoomMeeting $zoomMeeting): JsonResponse
    {
        $rows = $zoomMeeting->attendances()
            ->with('user:id,name,email')
            ->orderBy('attended_at')
            ->get();

        return response()->json($rows);
    }
}
