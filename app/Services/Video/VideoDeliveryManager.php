<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Models\Lesson;
use Illuminate\Support\Facades\Log;

class VideoDeliveryManager
{
    public function __construct(
        private readonly ?HlsDeliveryService $hlsService = null,
        private readonly ?YoutubeDeliveryService $youtubeService = null,
    ) {}

    /**
     * Resolves the playback payload for a lesson.
     *
     * @return array{provider: string, source?: string, url?: string, expires_at: string|null}|null
     */
    public function resolvePlayback(Lesson $lesson, bool $enforceGate = false): ?array
    {
        if (empty($lesson->video_id)) {
            return null;
        }

        // When gate is enforced (e.g. in curriculum listings), strictly return null for non-preview lessons
        if ($enforceGate && ! $lesson->is_preview) {
            return null;
        }

        $provider = $lesson->video_provider ?: config('services.video.default', 'youtube');

        if (app()->isProduction() && ! $lesson->is_preview && $provider !== 'hls') {
            Log::critical('Blocked non-HLS paid lesson playback in production', [
                'lesson_id' => $lesson->id,
                'video_provider' => $provider,
            ]);

            return null;
        }

        return match ($provider) {
            'hls' => ($this->hlsService ?? app(HlsDeliveryService::class))->resolvePlayback($lesson),
            default => ($this->youtubeService ?? app(YoutubeDeliveryService::class))->resolvePlayback($lesson),
        };
    }
}
