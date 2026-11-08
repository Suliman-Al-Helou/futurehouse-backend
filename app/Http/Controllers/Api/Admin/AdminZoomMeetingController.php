<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ZoomMeetingRequest;
use App\Http\Resources\ZoomMeetingResource;
use App\Models\User;
use App\Models\ZoomMeeting;
use App\Services\ZoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AdminZoomMeetingController extends Controller
{
    public function __construct(
        private readonly ZoomService $zoomService,
    ) {}

    /** Lists all meetings (newest first) with course info and attendance counts. */
    public function index(): AnonymousResourceCollection
    {
        return ZoomMeetingResource::collection(
            ZoomMeeting::with('course:id,title')
                ->withCount('attendances')
                ->orderByDesc('starts_at')
                ->get()
        );
    }

    /** Creates a Zoom meeting. */
    public function store(ZoomMeetingRequest $request): JsonResponse
    {
        $meeting = $this->zoomService->create($request->validated());

        return response()->json(new ZoomMeetingResource($meeting), 201);
    }

    /** Updates a Zoom meeting. */
    public function update(ZoomMeetingRequest $request, ZoomMeeting $zoomMeeting): JsonResponse
    {
        $meeting = $this->zoomService->update($zoomMeeting, $request->validated());

        return response()->json(new ZoomMeetingResource($meeting));
    }

    /** Deletes a Zoom meeting (cascade removes attendances). */
    public function destroy(ZoomMeeting $zoomMeeting): Response
    {
        $zoomMeeting->delete();

        return response()->noContent();
    }

    /** Lists attendances for a specific meeting. */
    public function attendances(ZoomMeeting $zoomMeeting): JsonResponse
    {
        $rows = $zoomMeeting->attendances()
            ->with('user:id,name,email')
            ->orderBy('attended_at')
            ->get();

        return response()->json($rows);
    }

    /** All approved students in the course with their attendance status (for manual marking). */
    public function roster(ZoomMeeting $zoomMeeting): JsonResponse
    {
        return response()->json($this->zoomService->roster($zoomMeeting));
    }

    /** Manually confirms or revokes a student's attendance. */
    public function setAttendance(Request $request, ZoomMeeting $zoomMeeting, User $user): JsonResponse
    {
        $data = $request->validate(['attended' => ['required', 'boolean']]);

        $this->zoomService->setAttendance($zoomMeeting, $user, (bool) $data['attended']);

        return response()->json(['attended' => (bool) $data['attended']]);
    }
}
