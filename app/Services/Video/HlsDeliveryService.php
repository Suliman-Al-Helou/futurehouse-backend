<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Models\Lesson;
use Illuminate\Support\Carbon;

class HlsDeliveryService implements VideoDeliveryInterface
{
    private const TTL_HOURS = 2;

    /**
     * Resolve Bunny.net Stream HLS playback payload with a 2-hour Path-Based Token.
     *
     * @return array{provider: 'hls', url: string, expires_at: string}
     */
    public function resolvePlayback(Lesson $lesson): array
    {
        $videoId = (string) $lesson->video_id;
        $expiresAtCarbon = Carbon::now()->addHours(self::TTL_HOURS);
        $expiresAt = $expiresAtCarbon->toIso8601String();

        $baseUrl = str_starts_with($videoId, 'http://') || str_starts_with($videoId, 'https://')
            ? $videoId
            : sprintf('https://%s/%s/playlist.m3u8', config('services.video.bunny.hostname', 'video.bunnycdn.com'), $videoId);

        $tokenKey = config('services.video.bunny.token_key');
        if ($tokenKey && ! str_contains($baseUrl, 'token=')) {
            $baseUrl = $this->signBunnyPathToken($baseUrl, $videoId, (string) $tokenKey, $expiresAtCarbon->timestamp);
        }

        return [
            'provider' => 'hls',
            'url' => $baseUrl,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Sign a Bunny CDN Token Auth v2 URL scoped to the video directory (`token_path`) without IP binding.
     */
    private function signBunnyPathToken(string $url, string $videoId, string $tokenKey, int $expiresTimestamp): string
    {
        $parsedUrl = parse_url($url);
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $host = $parsedUrl['host'] ?? config('services.video.bunny.hostname', 'video.bunnycdn.com');
        $urlPath = $parsedUrl['path'] ?? "/{$videoId}/playlist.m3u8";

        $tokenPath = $this->resolveDirectoryTokenPath($urlPath, $videoId);

        $parameters = [];
        if (! empty($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $parameters);
        }

        $parameters['token_path'] = $tokenPath;
        ksort($parameters);

        $parameterData = implode('&', array_map(
            fn (string $key, mixed $value): string => "{$key}={$value}",
            array_keys($parameters),
            $parameters
        ));

        // Bunny Token Auth v2: SecurityKey + SignaturePath + Expires + SortedParameters (no UserIP)
        $hashableBase = $tokenKey.$tokenPath.$expiresTimestamp.$parameterData;
        $token = rtrim(strtr(base64_encode(hash('sha256', $hashableBase, true)), '+/', '-_'), '=');

        $queryParams = array_merge($parameters, [
            'token' => $token,
            'expires' => $expiresTimestamp,
        ]);
        ksort($queryParams);

        return sprintf('%s://%s%s?%s', $scheme, $host, $urlPath, http_build_query($queryParams));
    }

    private function resolveDirectoryTokenPath(string $urlPath, string $videoId): string
    {
        $dir = trim(dirname($urlPath), '/');
        if ($dir === '' || $dir === '.') {
            $cleanId = trim($videoId, '/');

            return "/{$cleanId}/";
        }

        return "/{$dir}/";
    }
}
