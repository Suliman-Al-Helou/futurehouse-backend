<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use App\Models\VideoAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonVideoAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Course $course;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        Sanctum::actingAs($this->admin);

        $this->course = Course::create([
            'title' => 'Test Course for Videos',
            'description' => 'Course description',
            'is_public' => false,
            'status' => 'published',
        ]);

        $this->section = Section::create([
            'course_id' => $this->course->id,
            'title' => 'Main Section',
            'order' => 1,
        ]);
    }

    public function test_cannot_assign_same_video_id_to_multiple_lessons(): void
    {
        // First lesson created with video_id 'vid_123'
        $lesson1 = Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Lesson 1',
            'video_id' => 'vid_123',
        ]);

        // Creating second lesson with same video_id must fail validation
        $response = $this->postJson("/api/admin/sections/{$this->section->id}/lessons", [
            'title' => 'Lesson 2',
            'video_id' => 'vid_123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['video_id']);

        // Updating an existing lesson to use an already-used video_id must fail validation
        $lesson2 = Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Lesson 2 Different',
            'video_id' => 'vid_456',
        ]);

        $updateResponse = $this->putJson("/api/admin/lessons/{$lesson2->id}", [
            'video_id' => 'vid_123',
        ]);

        $updateResponse->assertStatus(422)
            ->assertJsonValidationErrors(['video_id']);

        // Updating a lesson keeping its OWN video_id should succeed
        $selfUpdate = $this->putJson("/api/admin/lessons/{$lesson1->id}", [
            'title' => 'Lesson 1 Renamed',
            'video_id' => 'vid_123',
        ]);
        $selfUpdate->assertOk();
    }

    public function test_detaching_video_id_from_lesson_frees_the_video(): void
    {
        $lesson1 = Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Lesson 1',
            'video_id' => 'shared_vid',
        ]);

        // Lesson 2 cannot use it
        $this->postJson("/api/admin/sections/{$this->section->id}/lessons", [
            'title' => 'Lesson 2',
            'video_id' => 'shared_vid',
        ])->assertStatus(422);

        // Detach video from Lesson 1 (set video_id to null)
        $this->putJson("/api/admin/lessons/{$lesson1->id}", [
            'video_id' => null,
        ])->assertOk();

        $this->assertNull($lesson1->fresh()->video_id);

        // Now Lesson 2 can successfully attach it
        $this->postJson("/api/admin/sections/{$this->section->id}/lessons", [
            'title' => 'Lesson 2',
            'video_id' => 'shared_vid',
        ])->assertCreated();
    }

    public function test_admin_can_filter_unattached_video_assets(): void
    {
        // Video asset 1: attached to a lesson
        $assetAttached = VideoAsset::create([
            'course_id' => $this->course->id,
            'title' => 'Attached Asset',
            'provider_video_id' => 'attached_vid',
            'type' => 'lesson',
        ]);

        Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Lesson Using Asset',
            'video_id' => 'attached_vid',
        ]);

        // Video asset 2: not attached to any lesson
        $assetUnattached = VideoAsset::create([
            'course_id' => $this->course->id,
            'title' => 'Unattached Asset',
            'provider_video_id' => 'free_vid',
            'type' => 'lesson',
        ]);

        // Video asset 3: not attached, for different course
        $otherCourse = Course::create(['title' => 'Other', 'description' => 'D', 'is_public' => false]);
        VideoAsset::create([
            'course_id' => $otherCourse->id,
            'title' => 'Other Course Asset',
            'provider_video_id' => 'other_vid',
            'type' => 'lesson',
        ]);

        $response = $this->getJson("/api/admin/video-assets?course_id={$this->course->id}&unattached=true");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Unattached Asset')
            ->assertJsonPath('data.0.provider_video_id', 'free_vid');
    }
}
