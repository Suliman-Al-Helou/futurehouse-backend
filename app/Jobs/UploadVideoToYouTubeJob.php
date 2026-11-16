<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\VideoAsset;
use App\Services\YouTubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UploadVideoToYouTubeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour for large video uploads

    public int $tries = 2;

    public function __construct(
        public readonly VideoAsset $videoAsset,
        public readonly string $filePath
    ) {}

    public function handle(YouTubeService $youtubeService): void
    {
        $this->videoAsset->update(['status' => 'processing']);

        try {
            $course = $this->videoAsset->course;
            $playlistId = $course?->youtube_playlist_id;

            // If the course doesn't have a playlist yet, generate one
            if ($course && ! $playlistId && $youtubeService->isConnected()) {
                $playlistId = $youtubeService->createCoursePlaylist($course->title, $course->description ?? '');
                if ($playlistId) {
                    $course->update(['youtube_playlist_id' => $playlistId]);
                }
            }

            // Upload the video and bind to the course playlist
            $videoId = $youtubeService->uploadVideo(
                $this->filePath,
                $this->videoAsset->title,
                $this->videoAsset->description ?? '',
                $playlistId
            );

            $this->videoAsset->update([
                'status' => 'ready',
                'provider' => 'youtube',
                'provider_video_id' => $videoId,
                'temp_file_path' => null,
            ]);

            // Clean up temporary local file
            if (file_exists($this->filePath)) {
                @unlink($this->filePath);
            }
        } catch (\Throwable $e) {
            Log::error("Failed uploading video asset {$this->videoAsset->id} to YouTube: ".$e->getMessage());
            $this->videoAsset->update(['status' => 'failed']);
            throw $e;
        }
    }
}
