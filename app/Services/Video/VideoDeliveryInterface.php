<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Models\Lesson;

interface VideoDeliveryInterface
{
    /**
     * Resolve the playback payload for the given lesson.
     *
     * @return array{provider: string, source?: string, url?: string, expires_at: string|null}
     */
    public function resolvePlayback(Lesson $lesson): array;
}
