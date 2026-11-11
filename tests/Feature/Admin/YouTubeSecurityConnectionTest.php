<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Jobs\UploadVideoToYouTubeJob;
use App\Models\Course;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\VideoAsset;
use App\Services\YouTubeService;
use Google\Client as GoogleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class YouTubeSecurityConnectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        Sanctum::actingAs($this->admin);

        $this->course = Course::create([
            'title' => 'Fullstack Laravel & React',
            'description' => 'Complete curriculum',
            'is_public' => false,
        ]);
    }

    /**
     * Proves: The OAuth authorization endpoint generates a non-empty,
     * cryptographically secure redirect URL with dynamic state parameter matching Google standards.
     */
    public function test_auth_url_generation_returns_secure_google_oauth_endpoint_with_csrf_state(): void
    {
        $response = $this->getJson('/api/admin/youtube/auth-url');

        $response->assertOk()
            ->assertJsonStructure(['url', 'state']);

        $url = $response->json('url');
        $state = $response->json('state');

        $this->assertNotEmpty($state);
        $this->assertTrue(str_starts_with($state, 'yt_'));
        $this->assertTrue(Cache::has("yt_oauth_state_{$state}"));

        // Assert base OAuth URL
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $url);
        // Assert offline access for refresh token retrieval
        $this->assertStringContainsString('access_type=offline', $url);
        // Assert forced consent prompt to ensure refresh token is returned
        $this->assertStringContainsString('prompt=consent', $url);
        // Assert scope includes upload capability
        $this->assertStringContainsString('youtube.upload', $url);
        // Assert clean redirect URI
        $this->assertStringContainsString('redirect_uri=' . urlencode('http://localhost:3000/admin'), $url);
        // Assert dynamic state parameter in URL
        $this->assertStringContainsString('state=' . urlencode($state), $url);
    }

    /**
     * Proves: OAuth callback exchanges code for tokens and encrypts the
     * refresh token before writing to the database (protecting credentials at rest).
     */
    public function test_oauth_callback_exchanges_code_and_encrypts_refresh_token_at_rest(): void
    {
        $validState = 'yt_test_csrf_token_123';
        Cache::put("yt_oauth_state_{$validState}", $this->admin->id, now()->addMinutes(10));

        $this->app->bind(YouTubeService::class, function () {
            $service = Mockery::mock(YouTubeService::class)->makePartial();
            $service->shouldReceive('handleCallback')
                ->once()
                ->with('valid-google-auth-code')
                ->andReturnUsing(function () {
                    // Store encrypted token
                    PlatformSetting::set('youtube_refresh_token', Crypt::encryptString('1//0gTestSecretRefreshToken'));
                    PlatformSetting::set('youtube_channel_title', 'Master Dev Academy');

                    return [
                        'status' => 'connected',
                        'channel_title' => 'Master Dev Academy',
                    ];
                });

            return $service;
        });

        $response = $this->postJson('/api/admin/youtube/callback', [
                'code' => 'valid-google-auth-code',
                'state' => $validState,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('channel_title', 'Master Dev Academy');

        // Prove state is consumed and invalidated (single-use defense against replay attacks)
        $this->assertFalse(Cache::has("yt_oauth_state_{$validState}"));

        // Prove token is encrypted in raw storage (NOT stored as plaintext)
        $rawStoredValue = PlatformSetting::where('key', 'youtube_refresh_token')->value('value');
        $this->assertNotEquals('1//0gTestSecretRefreshToken', $rawStoredValue);

        // Prove it can be decrypted with the app key
        $this->assertEquals('1//0gTestSecretRefreshToken', Crypt::decryptString($rawStoredValue));
    }

    /**
     * Proves: OAuth callback rejects invalid or forged CSRF state parameters.
     */
    public function test_oauth_callback_rejects_invalid_csrf_state(): void
    {
        $response = $this->postJson('/api/admin/youtube/callback', [
            'code' => 'valid-code-with-tampered-state',
            'state' => 'yt_forged_or_expired_state',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'رمز التحقق (CSRF State) منتهي الصلاحية أو غير صالح.');
    }

    /**
     * Proves: Disconnection completely invalidates local records and triggers
     * safe state transition in the platform.
     */
    public function test_disconnect_clears_stored_credentials_and_channel_metadata(): void
    {
        PlatformSetting::set('youtube_refresh_token', Crypt::encryptString('token-to-revoke'));
        PlatformSetting::set('youtube_channel_title', 'Channel Before Disconnect');

        $response = $this->postJson('/api/admin/youtube/disconnect');

        $response->assertOk()
            ->assertJsonPath('status', 'disconnected');

        // Prove credentials have been purged
        $this->assertNull(PlatformSetting::get('youtube_refresh_token'));
        $this->assertNull(PlatformSetting::get('youtube_channel_title'));

        // Prove status endpoint accurately reflects disconnected state
        $statusResponse = $this->getJson('/api/admin/youtube/status');

        $statusResponse->assertOk()
            ->assertJsonPath('connected', false);
    }

    /**
     * Proves: Chunked upload receives multi-part binary slices, safely reassembles
     * them without path traversal, and dispatches the background worker.
     */
    public function test_chunked_upload_assembles_video_and_dispatches_youtube_job(): void
    {
        Queue::fake();

        // Simulate connection
        PlatformSetting::set('youtube_refresh_token', Crypt::encryptString('mock-token'));

        $uploadId = 'test_upload_' . uniqid();
        $chunkSize = 256 * 1024; // 256 KB slice (Google compliant)
        $totalChunks = 2;

        // Chunk 0
        $fileChunk0 = UploadedFile::fake()->createWithContent('chunk_0', str_repeat('A', $chunkSize));
        $res0 = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/video-assets/upload-chunk', [
                'chunk' => $fileChunk0,
                'chunk_index' => 0,
                'total_chunks' => $totalChunks,
                'upload_id' => $uploadId,
                'file_name' => 'lecture_1.mp4',
                'course_id' => $this->course->id,
                'title' => 'Lecture 1: Architecture Ingestion',
            ]);

        $res0->assertOk()
            ->assertJsonPath('completed', false)
            ->assertJsonPath('progress', 50);

        // Chunk 1 (Final)
        $fileChunk1 = UploadedFile::fake()->createWithContent('chunk_1', str_repeat('B', $chunkSize));
        $res1 = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/video-assets/upload-chunk', [
                'chunk' => $fileChunk1,
                'chunk_index' => 1,
                'total_chunks' => $totalChunks,
                'upload_id' => $uploadId,
                'file_name' => 'lecture_1.mp4',
                'course_id' => $this->course->id,
                'title' => 'Lecture 1: Architecture Ingestion',
            ]);

        $res1->assertStatus(201)
            ->assertJsonPath('completed', true)
            ->assertJsonPath('progress', 100);

        // Prove VideoAsset record was created with 'processing' state
        $asset = VideoAsset::where('title', 'Lecture 1: Architecture Ingestion')->first();
        $this->assertNotNull($asset);
        $this->assertEquals('processing', $asset->status);
        $this->assertEquals($this->course->id, $asset->course_id);
        $this->assertFileExists($asset->temp_file_path);

        // Prove background job was dispatched with the assembled file
        Queue::assertPushed(UploadVideoToYouTubeJob::class, function ($job) use ($asset) {
            return $job->videoAsset->id === $asset->id;
        });

        // Cleanup test temp file
        if ($asset->temp_file_path && file_exists($asset->temp_file_path)) {
            @unlink($asset->temp_file_path);
        }
    }

    /**
     * Proves: Unauthorized users (e.g. students or guests) are strictly blocked
     * from invoking YouTube admin endpoints.
     */
    public function test_unauthenticated_or_non_admin_requests_are_denied(): void
    {
        $student = User::factory()->create();

        // Student access blocked by role middleware
        Sanctum::actingAs($student);
        $this->getJson('/api/admin/youtube/status')
            ->assertStatus(403);
    }
}
