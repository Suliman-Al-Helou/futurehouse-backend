<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use App\Models\UserLessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonPlaybackDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $instructorUser;
    private User $adminUser;
    private Instructor $instructor;
    private Course $course;
    private Section $section;
    private Lesson $previewLesson;
    private Lesson $lockedYoutubeLesson;
    private Lesson $lockedHlsLesson;
    private Lesson $draftLesson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
        $this->instructorUser = User::factory()->create();
        $this->instructorUser->assignRole('instructor');
        $this->instructor = $this->instructorUser->instructor ?? Instructor::create([
            'user_id' => $this->instructorUser->id,
            'name' => 'Prof. Alan Turing',
            'email' => 'turing@example.com',
        ]);
        $this->adminUser = User::factory()->admin()->create();

        $this->course = Course::create([
            'title' => 'Advanced Streaming Architecture',
            'description' => 'HLS and video delivery pipeline engineering',
            'status' => 'published',
            'price' => 199.99,
            'is_public' => false,
            'instructor_id' => $this->instructor->id,
        ]);

        $this->section = Section::create([
            'course_id' => $this->course->id,
            'title' => 'Video Delivery Architecture',
            'order' => 1,
        ]);

        // Preview lesson (free to inspect in curriculum)
        $this->previewLesson = Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Preview: Course Introduction',
            'video_id' => 'intro123',
            'video_provider' => 'youtube',
            'duration' => 180,
            'order' => 1,
            'is_preview' => true,
            'is_published' => true,
        ]);

        // Locked YouTube lesson
        $this->lockedYoutubeLesson = Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Deep Dive into MP4 Parsing',
            'video_id' => 'mp4deepdive',
            'video_provider' => 'youtube',
            'duration' => 600,
            'order' => 2,
            'is_preview' => false,
            'is_published' => true,
        ]);

        // Locked HLS lesson
        $this->lockedHlsLesson = Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Signed HLS Manifest Streaming',
            'video_id' => 'bunnystream999',
            'video_provider' => 'hls',
            'duration' => 900,
            'order' => 3,
            'is_preview' => false,
            'is_published' => true,
        ]);

        // Draft lesson
        $this->draftLesson = Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Unreleased Future Lecture',
            'video_id' => 'draftvideo',
            'video_provider' => 'youtube',
            'duration' => 450,
            'order' => 4,
            'is_preview' => false,
            'is_published' => false,
        ]);
    }

    /**
     * Test 1: Public curriculum endpoint shields non-preview playback payloads.
     */
    public function test_public_curriculum_shields_locked_playback_while_allowing_preview(): void
    {
        $response = $this->getJson("/api/courses/{$this->course->id}");

        $response->assertOk();
        $lessons = $response->json('sections.0.lessons') ?? $response->json('data.sections.0.lessons');

        // Locate preview lesson vs locked lesson
        $preview = collect($lessons)->firstWhere('id', $this->previewLesson->id);
        $locked = collect($lessons)->firstWhere('id', $this->lockedYoutubeLesson->id);

        $this->assertNotNull($preview);
        $this->assertNotNull($preview['playback']);
        $this->assertSame('youtube', $preview['playback']['provider']);
        $this->assertSame('intro123', $preview['playback']['source']);

        $this->assertNotNull($locked);
        $this->assertNull($locked['playback'], 'Locked lesson playback must be null in curriculum listing');
    }

    /**
     * Test 2: Enrolled student receives full YouTube playback payload on single lesson show.
     */
    public function test_enrolled_student_receives_youtube_playback_payload(): void
    {
        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
            'enrolled_at' => now(),
        ]);

        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->previewLesson->id,
            'completed' => true,
            'watched_percent' => 100,
        ]);

        Sanctum::actingAs($this->student);

        $response = $this->getJson("/api/lessons/{$this->lockedYoutubeLesson->id}");

        $response->assertOk();
        $response->assertJsonPath('playback.provider', 'youtube');
        $response->assertJsonPath('playback.source', 'mp4deepdive');
        $response->assertJsonPath('playback.expires_at', null);
        $response->assertJsonPath('is_published', true);
    }

    /**
     * Test 3: Enrolled student receives signed HLS playback payload on HLS lesson.
     */
    public function test_enrolled_student_receives_hls_playback_payload(): void
    {
        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
            'enrolled_at' => now(),
        ]);

        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->previewLesson->id,
            'completed' => true,
            'watched_percent' => 100,
        ]);

        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lockedYoutubeLesson->id,
            'completed' => true,
            'watched_percent' => 100,
        ]);

        Sanctum::actingAs($this->student);

        $response = $this->getJson("/api/lessons/{$this->lockedHlsLesson->id}");

        $response->assertOk();
        $response->assertJsonPath('playback.provider', 'hls');
        $this->assertStringContainsString('bunnystream999', $response->json('playback.url'));
        $this->assertStringContainsString('.m3u8', $response->json('playback.url'));
        $this->assertNotNull($response->json('playback.expires_at'));
    }

    /**
     * Test 4: Draft lesson returns 404 to students, but 200 to course instructor and admin.
     */
    public function test_draft_lesson_returns_404_to_student_and_200_to_instructor_and_admin(): void
    {
        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
            'enrolled_at' => now(),
        ]);

        // 1. Student gets 404
        Sanctum::actingAs($this->student);
        $this->getJson("/api/lessons/{$this->draftLesson->id}")->assertNotFound();

        // 2. Unassigned other instructor gets 404
        $otherInstructor = User::factory()->create();
        $otherInstructor->assignRole('instructor');
        Sanctum::actingAs($otherInstructor);
        $this->getJson("/api/lessons/{$this->draftLesson->id}")->assertNotFound();

        // 3. Course assigned instructor gets 200
        Sanctum::actingAs($this->instructorUser);
        $response = $this->getJson("/api/lessons/{$this->draftLesson->id}");
        $response->assertOk();
        $response->assertJsonPath('is_published', false);

        // 4. Admin gets 200
        Sanctum::actingAs($this->adminUser);
        $adminRes = $this->getJson("/api/lessons/{$this->draftLesson->id}");
        $adminRes->assertOk();
        $adminRes->assertJsonPath('is_published', false);
    }
}
