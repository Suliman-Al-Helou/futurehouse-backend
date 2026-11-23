<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Models\Lesson;
use Illuminate\Support\Carbon;

class VideoDeliveryManager
{
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

        return match ($provider) {
            'hls' => $this->resolveHlsPayload($lesson),
            default => $this->resolveYoutubePayload($lesson),
        };
    }

    /**
     * Resolve YouTube playback payload.
     *
     * @return array{provider: 'youtube', source: string, expires_at: null}
     */
    protected function resolveYoutubePayload(Lesson $lesson): array
    {
        return [
            'provider' => 'youtube',
            'source' => (string) $lesson->video_id,
            'expires_at' => null,
        ];
    }

    /**
     * Resolve HLS playback payload with signed URL token.
     *
     * @return array{provider: 'hls', url: string, expires_at: string}
     */
    protected function resolveHlsPayload(Lesson $lesson): array
    {
        $videoId = (string) $lesson->video_id;
        $expiresAt = Carbon::now()->addHours(4)->toIso8601String();

        $url = str_starts_with($videoId, 'http://') || str_starts_with($videoId, 'https://')
            ? $videoId
            : sprintf('https://%s/%s/playlist.m3u8', config('services.video.bunny.hostname', 'video.bunnycdn.com'), $videoId);

        // Generate Bunny Token Authentication signature when security key is configured
        $tokenKey = config('services.video.bunny.token_key');
        if ($tokenKey && ! str_contains($url, 'token=')) {
            $parsedUrl = parse_url($url);
            $path = $parsedUrl['path'] ?? "/{$videoId}/playlist.m3u8";
            $expiresTimestamp = Carbon::parse($expiresAt)->timestamp;
            $hashable = $tokenKey.$path.$expiresTimestamp;
            $token = rtrim(strtr(base64_encode(hash('sha256', $hashable, true)), '+/', '-_'), '=');
            $url .= (str_contains($url, '?') ? '&' : '?')."token={$token}&expires={$expiresTimestamp}";
        }

        return [
            'provider' => 'hls',
            'url' => $url,
            'expires_at' => $expiresAt,
        ];
    }
}
