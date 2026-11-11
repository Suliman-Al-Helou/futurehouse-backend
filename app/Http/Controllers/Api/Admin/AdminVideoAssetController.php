<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VideoAssetRequest;
use App\Http\Resources\VideoAssetResource;
use App\Jobs\UploadVideoToYouTubeJob;
use App\Models\PlatformSetting;
use App\Models\VideoAsset;
use App\Services\YouTubeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class AdminVideoAssetController extends Controller
{
    public function __construct(
        private readonly YouTubeService $youtubeService
    ) {}

    /** Lists video assets, with optional filtering by course, zoom meeting, or type. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = VideoAsset::with([
            'course:id,title,youtube_playlist_id',
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

    /** Creates a new video asset via direct metadata. */
    public function store(VideoAssetRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;
        $data['status'] = 'ready';

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
        if ($videoAsset->temp_file_path && file_exists($videoAsset->temp_file_path)) {
            @unlink($videoAsset->temp_file_path);
        }

        $videoAsset->delete();

        return response()->noContent();
    }

    /** Receives chunked file uploads and stitches them into a video file */
    public function uploadChunk(Request $request): JsonResponse
    {
        if (! $this->youtubeService->isConnected()) {
            return response()->json([
                'message' => 'قناة YouTube غير مربوطة بعد. يرجى ربط قناة YouTube من تبويب لقاءات Zoom أولاً لتفعيل الرفع.',
            ], 422);
        }

        $validated = $request->validate([
            'chunk' => ['required', 'file'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1'],
            'upload_id' => ['required', 'string', 'max:128'],
            'file_name' => ['required', 'string', 'max:255'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'zoom_meeting_id' => ['nullable', 'integer', 'exists:zoom_meetings,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['nullable', 'string', 'in:recording,lesson'],
        ]);

     
        $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', $validated['upload_id']);
        $chunkIndex = (int) $validated['chunk_index'];
        $totalChunks = (int) $validated['total_chunks'];

        $tempBaseDir = storage_path('app/temp-videos');
        $sessionDir = $tempBaseDir . '/' . $uploadId;

        if (! File::isDirectory($sessionDir)) {
            File::makeDirectory($sessionDir, 0755, true);
        }

        // Save chunk
        $chunkFile = $request->file('chunk');
        $chunkFile->move($sessionDir, "chunk_{$chunkIndex}");

        // Check if all chunks have arrived
        $allPresent = true;
        for ($i = 0; $i < $totalChunks; $i++) {
            if (! file_exists("{$sessionDir}/chunk_{$i}")) {
                $allPresent = false;
                break;
            }
        }

        if (! $allPresent) {
            $progress = (int) round((($chunkIndex + 1) / $totalChunks) * 100);
            return response()->json([
                'completed' => false,
                'chunk_index' => $chunkIndex,
                'progress' => $progress,
            ]);
        }

        // All chunks arrived: stitch into a single file
        $extension = pathinfo($validated['file_name'], PATHINFO_EXTENSION) ?: 'mp4';
        $finalFilename = "video_{$uploadId}." . $extension;
        $finalPath = "{$tempBaseDir}/{$finalFilename}";

        $outHandle = fopen($finalPath, 'wb');
        for ($i = 0; $i < $totalChunks; $i++) {
            $inPath = "{$sessionDir}/chunk_{$i}";
            $inHandle = fopen($inPath, 'rb');
            stream_copy_to_stream($inHandle, $outHandle);
            fclose($inHandle);
            @unlink($inPath);
        }
        fclose($outHandle);
        @rmdir($sessionDir);

        // Create the pending VideoAsset record
        $videoAsset = VideoAsset::create([
            'course_id' => (int) $validated['course_id'],
            'zoom_meeting_id' => ! empty($validated['zoom_meeting_id']) ? (int) $validated['zoom_meeting_id'] : null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'provider' => 'youtube',
            'provider_video_id' => 'processing_' . substr($uploadId, 0, 8),
            'status' => 'processing',
            'temp_file_path' => $finalPath,
            'type' => $validated['type'] ?? 'recording',
            'created_by' => $request->user()?->id,
        ]);

        // Dispatch background job to upload to YouTube
        if ($this->youtubeService->isConnected()) {
            UploadVideoToYouTubeJob::dispatch($videoAsset, $finalPath);
        } else {
            // Keep as ready locally if YouTube is not connected yet
            $videoAsset->update(['status' => 'ready']);
        }

        $videoAsset->load(['course:id,title', 'zoomMeeting:id,title,starts_at']);

        return response()->json([
            'completed' => true,
            'progress' => 100,
            'video_asset' => new VideoAssetResource($videoAsset),
        ], 201);
    }

    /** Returns YouTube connection status */
    public function youtubeStatus(): JsonResponse
    {
        return response()->json($this->youtubeService->getStatus());
    }

    /** Returns Google OAuth Auth URL with CSRF state protection */
    public function youtubeAuthUrl(Request $request): JsonResponse
    {
        $state = 'yt_' . bin2hex(random_bytes(16));
        $userId = $request->user()?->id ?? 'admin';
        Cache::put("yt_oauth_state_{$state}", $userId, now()->addMinutes(15));

        return response()->json([
            'url' => $this->youtubeService->getAuthUrl($state),
            'state' => $state,
        ]);
    }

    /** Handles OAuth callback code from Google with state validation */
    public function youtubeCallback(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['nullable', 'string'],
        ]);

        $state = $request->input('state');
        if ($state && $state !== 'youtube_callback' && ! Cache::has("yt_oauth_state_{$state}")) {
            return response()->json(['message' => 'رمز التحقق (CSRF State) منتهي الصلاحية أو غير صالح.'], 403);
        }
        if ($state && Cache::has("yt_oauth_state_{$state}")) {
            Cache::forget("yt_oauth_state_{$state}");
        }

        try {
            $result = $this->youtubeService->handleCallback($request->input('code'));
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Disconnects YouTube channel and revokes active token upstream on Google servers */
    public function youtubeDisconnect(): JsonResponse
    {
        $this->youtubeService->revokeCurrentToken();

        return response()->json(['status' => 'disconnected']);
    }
}
