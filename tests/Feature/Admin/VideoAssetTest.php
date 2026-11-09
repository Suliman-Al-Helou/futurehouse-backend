<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\User;
use App\Models\VideoAsset;
use App\Models\ZoomMeeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VideoAssetTest extends TestCase
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
            'title' => 'Next.js 16 Masterclass',
            'description' => 'Full Next.js tutorial',
            'is_public' => false,
        ]);
    }

    public function test_admin_can_list_video_assets(): void
    {
        VideoAsset::create([
            'course_id' => $this->course->id,
            'title' => 'Lesson 1 Recording',
            'provider' => 'youtube',
            'provider_video_id' => 'dQw4w9WgXcQ',
            'duration_seconds' => 3600,
            'type' => 'recording',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/admin/video-assets');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Lesson 1 Recording')
            ->assertJsonPath('data.0.provider_video_id', 'dQw4w9WgXcQ');
    }

    public function test_admin_can_filter_video_assets_by_course(): void
    {
        $otherCourse = Course::create(['title' => 'Other Course', 'description' => 'Desc', 'is_public' => true]);

        VideoAsset::create([
            'course_id' => $this->course->id,
            'title' => 'Course 1 Video',
            'provider_video_id' => 'abc12345678',
        ]);

        VideoAsset::create([
            'course_id' => $otherCourse->id,
            'title' => 'Course 2 Video',
            'provider_video_id' => 'xyz12345678',
        ]);

        $response = $this->getJson("/api/admin/video-assets?course_id={$this->course->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Course 1 Video');
    }

    public function test_admin_can_create_video_asset_from_raw_id(): void
    {
        $payload = [
            'course_id' => $this->course->id,
            'title' => 'Zoom Session Replay',
            'provider_video_id' => 'dQw4w9WgXcQ',
            'duration_seconds' => 1800,
            'type' => 'recording',
        ];

        $response = $this->postJson('/api/admin/video-assets', $payload);

        $response->assertCreated()
            ->assertJsonPath('title', 'Zoom Session Replay')
            ->assertJsonPath('provider_video_id', 'dQw4w9WgXcQ')
            ->assertJsonPath('provider', 'youtube');

        $this->assertDatabaseHas('video_assets', [
            'course_id' => $this->course->id,
            'title' => 'Zoom Session Replay',
            'provider_video_id' => 'dQw4w9WgXcQ',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_create_video_asset_from_full_youtube_url(): void
    {
        $payload = [
            'course_id' => $this->course->id,
            'title' => 'Zoom Session Replay from Link',
            'provider_video_id' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s',
            'duration_seconds' => 2400,
        ];

        $response = $this->postJson('/api/admin/video-assets', $payload);

        $response->assertCreated()
            ->assertJsonPath('provider_video_id', 'dQw4w9WgXcQ');

        $this->assertDatabaseHas('video_assets', [
            'title' => 'Zoom Session Replay from Link',
            'provider_video_id' => 'dQw4w9WgXcQ',
        ]);
    }

    public function test_admin_can_link_video_asset_to_zoom_meeting(): void
    {
        $meeting = ZoomMeeting::create([
            'course_id' => $this->course->id,
            'title' => 'Live Q&A',
            'zoom_link' => 'https://zoom.us/j/123456',
            'starts_at' => now(),
            'duration_minutes' => 60,
        ]);

        $payload = [
            'course_id' => $this->course->id,
            'zoom_meeting_id' => $meeting->id,
            'title' => 'Q&A Replay',
            'provider_video_id' => 'dQw4w9WgXcQ',
        ];

        $response = $this->postJson('/api/admin/video-assets', $payload);

        $response->assertCreated()
            ->assertJsonPath('zoom_meeting_id', $meeting->id);

        $this->assertDatabaseHas('video_assets', [
            'zoom_meeting_id' => $meeting->id,
        ]);
    }

    public function test_admin_can_update_video_asset(): void
    {
        $asset = VideoAsset::create([
            'course_id' => $this->course->id,
            'title' => 'Old Title',
            'provider_video_id' => 'oldId123456',
        ]);

        $response = $this->putJson("/api/admin/video-assets/{$asset->id}", [
            'course_id' => $this->course->id,
            'title' => 'Updated Title',
            'provider_video_id' => 'newId123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('title', 'Updated Title')
            ->assertJsonPath('provider_video_id', 'newId123456');

        $this->assertDatabaseHas('video_assets', [
            'id' => $asset->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_admin_can_delete_video_asset(): void
    {
        $asset = VideoAsset::create([
            'course_id' => $this->course->id,
            'title' => 'To be deleted',
            'provider_video_id' => 'delId123456',
        ]);

        $response = $this->deleteJson("/api/admin/video-assets/{$asset->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('video_assets', ['id' => $asset->id]);
    }

    public function test_student_cannot_access_admin_video_assets(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $this->getJson('/api/admin/video-assets')->assertForbidden();
        $this->postJson('/api/admin/video-assets', [])->assertForbidden();
    }
}
