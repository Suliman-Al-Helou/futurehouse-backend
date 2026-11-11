<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlatformSetting;
use Google\Client as GoogleClient;
use Google\Http\MediaFileUpload;
use Google\Service\YouTube;
use Google\Service\YouTube\Playlist;
use Google\Service\YouTube\PlaylistItem;
use Google\Service\YouTube\PlaylistItemSnippet;
use Google\Service\YouTube\PlaylistSnippet;
use Google\Service\YouTube\PlaylistStatus;
use Google\Service\YouTube\ResourceId;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class YouTubeService
{
    private function getBaseClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId((string) config('services.google.client_id', env('GOOGLE_CLIENT_ID')));
        $client->setClientSecret((string) config('services.google.client_secret', env('GOOGLE_CLIENT_SECRET')));

        $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');
        $redirectUri = (string) env('GOOGLE_REDIRECT_URI', $frontendUrl . '/admin');
        $client->setRedirectUri($redirectUri);

        $client->addScope([
            YouTube::YOUTUBE,
            YouTube::YOUTUBE_UPLOAD,
        ]);

        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);

        return $client;
    }

    public function getAuthUrl(?string $state = null): string
    {
        $client = $this->getBaseClient();
        if ($state) {
            $client->setState($state);
        } else {
            $client->setState('youtube_callback');
        }

        return $client->createAuthUrl();
    }

    public function handleCallback(string $code): array
    {
        $client = $this->getBaseClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException('Google OAuth Error: ' . ($token['error_description'] ?? $token['error']));
        }

        if (isset($token['refresh_token'])) {
            // Encrypt refresh token at rest using authenticated AES-256-GCM
            PlatformSetting::set('youtube_refresh_token', Crypt::encryptString((string) $token['refresh_token']));
        }

        // Fetch channel name for display
        $channelTitle = 'Connected YouTube Channel';
        try {
            $youtube = new YouTube($client);
            $channelsResponse = $youtube->channels->listChannels('snippet', ['mine' => true]);
            if (! empty($channelsResponse->getItems())) {
                $channelTitle = $channelsResponse->getItems()[0]->getSnippet()->getTitle();
                PlatformSetting::set('youtube_channel_title', $channelTitle);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed fetching YouTube channel details: ' . $e->getMessage());
        }

        return [
            'status' => 'connected',
            'channel_title' => $channelTitle,
        ];
    }

    public function isConnected(): bool
    {
        return ! blank(PlatformSetting::get('youtube_refresh_token'));
    }

    public function getStatus(): array
    {
        return [
            'connected' => $this->isConnected(),
            'channel_title' => PlatformSetting::get('youtube_channel_title', 'قناة YouTube'),
        ];
    }

    public function getAuthenticatedClient(): ?GoogleClient
    {
        $stored = PlatformSetting::get('youtube_refresh_token');
        if (! $stored) {
            return null;
        }

        try {
            $refreshToken = Crypt::decryptString($stored);
        } catch (\Throwable) {
            $refreshToken = $stored;
            // Upgrade legacy plaintext token to encrypted storage
            PlatformSetting::set('youtube_refresh_token', Crypt::encryptString($stored));
        }

        $client = $this->getBaseClient();
        $client->refreshToken($refreshToken);

        return $client;
    }

    /**
     * Revokes the active refresh token on Google servers and clears local credentials.
     */
    public function revokeCurrentToken(): bool
    {
        $stored = PlatformSetting::get('youtube_refresh_token');
        if (! $stored) {
            return true;
        }

        try {
            $refreshToken = Crypt::decryptString($stored);
        } catch (\Throwable) {
            $refreshToken = $stored;
        }

        try {
            $client = $this->getBaseClient();
            $client->revokeToken($refreshToken);
        } catch (\Throwable $e) {
            Log::warning('Failed revoking YouTube token on Google servers: ' . $e->getMessage());
        }

        PlatformSetting::set('youtube_refresh_token', null);
        PlatformSetting::set('youtube_channel_title', null);

        return true;
    }

    /** Creates an unlisted playlist for a course on YouTube */
    public function createCoursePlaylist(string $title, string $description = ''): ?string
    {
        try {
            $client = $this->getAuthenticatedClient();
            if (! $client) {
                return null;
            }

            $youtube = new YouTube($client);

            $playlistSnippet = new PlaylistSnippet();
            $playlistSnippet->setTitle($title);
            $playlistSnippet->setDescription($description);

            $playlistStatus = new PlaylistStatus();
            $playlistStatus->setPrivacyStatus('unlisted');

            $playlist = new Playlist();
            $playlist->setSnippet($playlistSnippet);
            $playlist->setStatus($playlistStatus);

            $response = $youtube->playlists->insert('snippet,status', $playlist);

            return $response->getId();
        } catch (\Throwable $e) {
            Log::error('YouTube createCoursePlaylist failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Uploads a video to YouTube using resumable upload and binds it to a playlist */
    public function uploadVideo(
        string $filePath,
        string $title,
        string $description = '',
        ?string $playlistId = null
    ): string {
        $client = $this->getAuthenticatedClient();
        if (! $client) {
            throw new \RuntimeException('قناة YouTube غير مربوطة بعد.');
        }

        $youtube = new YouTube($client);

        $videoSnippet = new VideoSnippet();
        $videoSnippet->setTitle($title);
        $videoSnippet->setDescription($description);

        $videoStatus = new VideoStatus();
        $videoStatus->setPrivacyStatus('unlisted');

        $video = new Video();
        $video->setSnippet($videoSnippet);
        $video->setStatus($videoStatus);

        $client->setDefer(true);
        $insertRequest = $youtube->videos->insert('status,snippet', $video);

        $media = new MediaFileUpload(
            $client,
            $insertRequest,
            'video/*',
            null,
            true,
            2 * 1024 * 1024 // 2MB chunk
        );
        $media->setFileSize(filesize($filePath));

        $status = false;
        $handle = fopen($filePath, 'rb');
        while (! $status && ! feof($handle)) {
            $chunk = fread($handle, 2 * 1024 * 1024);
            $status = $media->nextChunk($chunk);
        }
        fclose($handle);

        $client->setDefer(false);

        if (! $status || ! isset($status['id'])) {
            throw new \RuntimeException('فشل الحصول على معرّف الفيديو من YouTube.');
        }

        $videoId = (string) $status['id'];

        // إذا كان هناك playlistId للكورس، أضف الفيديو إليها تلقائياً
        if ($playlistId) {
            $this->addVideoToPlaylist($playlistId, $videoId);
        }

        return $videoId;
    }

    /** Adds a video to a YouTube playlist */
    public function addVideoToPlaylist(string $playlistId, string $videoId): void
    {
        try {
            $client = $this->getAuthenticatedClient();
            if (! $client) return;

            $youtube = new YouTube($client);

            $resourceId = new ResourceId();
            $resourceId->setKind('youtube#video');
            $resourceId->setVideoId($videoId);

            $playlistItemSnippet = new PlaylistItemSnippet();
            $playlistItemSnippet->setPlaylistId($playlistId);
            $playlistItemSnippet->setResourceId($resourceId);

            $playlistItem = new PlaylistItem();
            $playlistItem->setSnippet($playlistItemSnippet);

            $youtube->playlistItems->insert('snippet', $playlistItem);
        } catch (\Throwable $e) {
            Log::warning("Failed adding video {$videoId} to playlist {$playlistId}: " . $e->getMessage());
        }
    }
}
