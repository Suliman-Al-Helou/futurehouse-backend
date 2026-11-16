<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\UploadVideoToYouTubeJob;
use App\Models\Course;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\VideoAsset;
use App\Notifications\ResetPasswordNotification;
use App\Services\YouTubeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class QueueAndJobsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->course = Course::factory()->free()->published()->create([
            'title' => 'Mastering Queues and Jobs in Laravel',
            'description' => 'Comprehensive course on asynchronous workers',
        ]);
    }

    public function test_failed_jobs_table_migration_and_structure_exists(): void
    {
        $this->assertTrue(Schema::hasTable('failed_jobs'), 'The failed_jobs table must exist.');
        $this->assertTrue(
            Schema::hasColumns('failed_jobs', [
                'id',
                'uuid',
                'connection',
                'queue',
                'payload',
                'exception',
                'failed_at',
            ]),
            'The failed_jobs table must contain all required columns.'
        );

        $this->assertTrue(Schema::hasTable('jobs'), 'The jobs table must exist.');
        $this->assertTrue(
            Schema::hasColumns('jobs', [
                'id',
                'queue',
                'payload',
                'attempts',
                'reserved_at',
                'available_at',
                'created_at',
            ]),
            'The jobs table must contain standard Laravel database queue columns.'
        );
    }

    public function test_upload_video_to_youtube_job_implements_should_queue_and_resilience_attributes(): void
    {
        $videoAsset = VideoAsset::create([
            'course_id' => $this->course->id,
            'title' => 'Sample Video',
            'provider' => 'youtube',
            'provider_video_id' => 'processing_'.uniqid(),
            'status' => 'processing',
        ]);

        $job = new UploadVideoToYouTubeJob($videoAsset, '/tmp/dummy.mp4');

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertGreaterThanOrEqual(2, $job->tries);
        $this->assertGreaterThanOrEqual(60, $job->timeout);
        $this->assertTrue(property_exists($job, 'backoff') || method_exists($job, 'backoff'));
        $this->assertTrue(method_exists($job, 'failed'));
    }

    public function test_upload_video_to_youtube_job_handle_executes_in_isolation_and_updates_model(): void
    {
        // Stage a temporary dummy file on disk
        $tempFilePath = tempnam(sys_get_temp_dir(), 'test_video_');
        $this->assertIsString($tempFilePath);
        file_put_contents($tempFilePath, 'dummy video binary content');
        $this->assertFileExists($tempFilePath);

        $videoAsset = VideoAsset::create([
            'course_id' => $this->course->id,
            'title' => 'Full Video Lecture',
            'description' => 'Deep dive into asynchronous architecture',
            'provider' => 'youtube',
            'provider_video_id' => 'processing_'.uniqid(),
            'status' => 'processing',
            'temp_file_path' => $tempFilePath,
        ]);

        $mockYouTube = Mockery::mock(YouTubeService::class);
        $mockYouTube->shouldReceive('isConnected')->andReturn(true);
        $mockYouTube->shouldReceive('createCoursePlaylist')
            ->once()
            ->with($this->course->title, $this->course->description)
            ->andReturn('PL_NEW_PLAYLIST_123');
        $mockYouTube->shouldReceive('uploadVideo')
            ->once()
            ->with($tempFilePath, 'Full Video Lecture', 'Deep dive into asynchronous architecture', 'PL_NEW_PLAYLIST_123')
            ->andReturn('yt_video_999888');

        $job = new UploadVideoToYouTubeJob($videoAsset, $tempFilePath);
        $job->handle($mockYouTube);

        $videoAsset->refresh();
        $this->course->refresh();

        $this->assertSame('ready', $videoAsset->status);
        $this->assertSame('youtube', $videoAsset->provider);
        $this->assertSame('yt_video_999888', $videoAsset->provider_video_id);
        $this->assertNull($videoAsset->temp_file_path);
        $this->assertSame('PL_NEW_PLAYLIST_123', $this->course->youtube_playlist_id);

        // Ensure temp local file was cleanly unlinked
        $this->assertFileDoesNotExist($tempFilePath);
    }

    public function test_upload_video_to_youtube_job_is_strictly_idempotent(): void
    {
        $tempFilePath = tempnam(sys_get_temp_dir(), 'test_video_idempotent_');
        $this->assertIsString($tempFilePath);
        file_put_contents($tempFilePath, 'idempotent video bytes');

        $this->course->update(['youtube_playlist_id' => 'PL_EXISTING_456']);

        $videoAsset = VideoAsset::create([
            'course_id' => $this->course->id,
            'title' => 'Idempotency Lesson',
            'provider' => 'youtube',
            'provider_video_id' => 'processing_'.uniqid(),
            'status' => 'processing',
            'temp_file_path' => $tempFilePath,
        ]);

        $mockYouTube = Mockery::mock(YouTubeService::class);
        $mockYouTube->shouldReceive('isConnected')->andReturn(true);
        // uploadVideo must be called exactly once despite handle() being invoked twice
        $mockYouTube->shouldReceive('uploadVideo')
            ->once()
            ->with($tempFilePath, 'Idempotency Lesson', '', 'PL_EXISTING_456')
            ->andReturn('yt_idempotent_111');

        $job = new UploadVideoToYouTubeJob($videoAsset, $tempFilePath);

        // Run 1: initial processing
        $job->handle($mockYouTube);

        $videoAsset->refresh();
        $this->assertSame('ready', $videoAsset->status);
        $this->assertSame('yt_idempotent_111', $videoAsset->provider_video_id);
        $this->assertFileDoesNotExist($tempFilePath);

        // Run 2: retry/duplicate execution (must be a safe no-op without re-calling uploadVideo)
        $job->handle($mockYouTube);

        $videoAsset->refresh();
        $this->assertSame('ready', $videoAsset->status);
        $this->assertSame('yt_idempotent_111', $videoAsset->provider_video_id);
    }

    public function test_upload_video_to_youtube_job_failed_handler_marks_asset_as_failed(): void
    {
        $videoAsset = VideoAsset::create([
            'course_id' => $this->course->id,
            'title' => 'Failing Video Asset',
            'provider' => 'youtube',
            'provider_video_id' => 'processing_'.uniqid(),
            'status' => 'processing',
        ]);

        $job = new UploadVideoToYouTubeJob($videoAsset, '/tmp/nonexistent.mp4');
        $job->failed(new \RuntimeException('Permanent upload failure: YouTube quota exceeded'));

        $videoAsset->refresh();
        $this->assertSame('failed', $videoAsset->status);
    }

    public function test_admin_chunk_upload_pushes_upload_job_when_youtube_is_connected(): void
    {
        Queue::fake();

        Sanctum::actingAs($this->admin);
        PlatformSetting::set('youtube_refresh_token', 'valid_token');
        PlatformSetting::set('youtube_channel_title', 'Production Channel');

        $uploadId = 'test_upload_queue_'.uniqid();
        $chunk = UploadedFile::fake()->createWithContent('chunk_0', 'video-content-stream');

        $response = $this->postJson('/api/admin/video-assets/upload-chunk', [
            'chunk' => $chunk,
            'chunk_index' => 0,
            'total_chunks' => 1,
            'upload_id' => $uploadId,
            'file_name' => 'final_lecture.mp4',
            'course_id' => $this->course->id,
            'title' => 'Queued Upload Lecture',
            'type' => 'recording',
        ]);

        $response->assertCreated()
            ->assertJsonPath('completed', true)
            ->assertJsonPath('progress', 100);

        // Assert job was pushed to the queue
        Queue::assertPushed(UploadVideoToYouTubeJob::class, function (UploadVideoToYouTubeJob $job) {
            return $job->videoAsset->title === 'Queued Upload Lecture'
                && $job->videoAsset->course_id === $this->course->id;
        });
    }

    public function test_admin_chunk_upload_does_not_push_job_when_youtube_is_disconnected(): void
    {
        Queue::fake();

        Sanctum::actingAs($this->admin);
        PlatformSetting::set('youtube_refresh_token', null);
        PlatformSetting::set('youtube_channel_title', null);

        $uploadId = 'test_upload_noyt_'.uniqid();
        $chunk = UploadedFile::fake()->createWithContent('chunk_0', 'local-only-video');

        $response = $this->postJson('/api/admin/video-assets/upload-chunk', [
            'chunk' => $chunk,
            'chunk_index' => 0,
            'total_chunks' => 1,
            'upload_id' => $uploadId,
            'file_name' => 'local_lecture.mp4',
            'course_id' => $this->course->id,
            'title' => 'Local Only Lecture',
            'type' => 'recording',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'قناة YouTube غير مربوطة بعد. يرجى ربط قناة YouTube من تبويب لقاءات Zoom أولاً لتفعيل الرفع.');

        // When YouTube is not connected, job must NOT be pushed
        Queue::assertNotPushed(UploadVideoToYouTubeJob::class);
    }

    public function test_reset_password_notification_implements_should_queue(): void
    {
        $notification = new ResetPasswordNotification('test-token-123');

        $this->assertInstanceOf(
            ShouldQueue::class,
            $notification,
            'ResetPasswordNotification must implement ShouldQueue so emails are offloaded to background workers.'
        );
    }
}
