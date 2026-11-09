<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VideoAssetRequest;
use App\Http\Resources\VideoAssetResource;
use App\Models\VideoAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AdminVideoAssetController extends Controller
{
    /** Lists video assets, with optional filtering by course, zoom meeting, or type. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = VideoAsset::with([
            'course:id,title',
            'zoomMeeting:id,title,starts_at',
            'creator:id,name',
        ]);

        if ($request->filled('course_id')) {
            $query->where('course_id', (int) $request->input('course_id'));
        }

        if ($request->filled('zoom_meeting_id')) {
            $query->where('zoom_meeting_id', (int) $request->input('zoom_meeting_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', (string) $request->input('type'));
        }

        return VideoAssetResource::collection($query->latest()->get());
    }

    /** Creates a new video asset. */
    public function store(VideoAssetRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $asset = VideoAsset::create($data);
        $asset->load(['course:id,title', 'zoomMeeting:id,title,starts_at']);

        return response()->json(new VideoAssetResource($asset), 201);
    }

    /** Shows a single video asset. */
    public function show(VideoAsset $videoAsset): JsonResponse
    {
        $videoAsset->load(['course:id,title', 'zoomMeeting:id,title,starts_at', 'creator:id,name']);

        return response()->json(new VideoAssetResource($videoAsset));
    }

    /** Updates a video asset. */
    public function update(VideoAssetRequest $request, VideoAsset $videoAsset): JsonResponse
    {
        $videoAsset->update($request->validated());
        $videoAsset->load(['course:id,title', 'zoomMeeting:id,title,starts_at']);

        return response()->json(new VideoAssetResource($videoAsset));
    }

    /** Deletes a video asset. */
    public function destroy(VideoAsset $videoAsset): Response
    {
        $videoAsset->delete();

        return response()->noContent();
    }
}
