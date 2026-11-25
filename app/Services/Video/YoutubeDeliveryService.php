<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Models\Lesson;

class YoutubeDeliveryService implements VideoDeliveryInterface
{
    /**
     * Resolve YouTube playback payload.
     *
     * @return array{provider: 'youtube', source: string, expires_at: null}
     */
    public function resolvePlayback(Lesson $lesson): array
    {
        return [
            'provider' => 'youtube',
            'source' => (string) $lesson->video_id,
            'expires_at' => null,
        ];
    }
}
