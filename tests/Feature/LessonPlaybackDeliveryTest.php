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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
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

    /**
     * Test 5: Authenticated instructor sees draft lessons in public course curriculum details.
     */
    public function test_authenticated_instructor_sees_draft_lessons_in_course_curriculum(): void
    {
        // Public visitor does not see draft lesson
        $guestRes = $this->getJson("/api/courses/{$this->course->id}");
        $guestRes->assertOk();
        $guestLessonIds = collect($guestRes->json('sections.0.lessons') ?? $guestRes->json('data.sections.0.lessons'))
            ->pluck('id')->all();
        $this->assertNotContains($this->draftLesson->id, $guestLessonIds);

        // Authenticated course instructor sees draft lesson
        Sanctum::actingAs($this->instructorUser);
        $instructorRes = $this->getJson("/api/courses/{$this->course->id}");
        $instructorRes->assertOk();
        $instructorLessonIds = collect($instructorRes->json('sections.0.lessons') ?? $instructorRes->json('data.sections.0.lessons'))
            ->pluck('id')->all();
        $this->assertContains($this->draftLesson->id, $instructorLessonIds);
    }

    /**
     * Test 6 (TDD - Ticket 01): Signed HLS URL uses 2-hour TTL and Bunny Path-Based token_path without IP binding.
     */
    public function test_hls_signed_url_uses_2_hour_ttl_and_bunny_path_based_token_without_ip_binding(): void
    {
        config([
            'services.video.bunny.token_key' => 'secret-bunny-key',
            'services.video.bunny.hostname' => 'vz-test.b-cdn.net',
        ]);

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

        $before = now()->timestamp;
        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->getJson("/api/lessons/{$this->lockedHlsLesson->id}");
        $after = now()->timestamp;

        $response->assertOk();
        $url = $response->json('playback.url');
        $expiresAt = $response->json('playback.expires_at');

        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        $this->assertSame('/bunnystream999/', $query['token_path'] ?? null);
        $this->assertArrayHasKey('expires', $query);
        $this->assertArrayHasKey('token', $query);

        $expiresTs = (int) $query['expires'];
        $this->assertGreaterThanOrEqual($before + 7200, $expiresTs);
        $this->assertLessThanOrEqual($after + 7200, $expiresTs);
        $this->assertSame(Carbon::createFromTimestamp($expiresTs)->toIso8601String(), $expiresAt);

        $expectedHashable = 'secret-bunny-key'.'/bunnystream999/'.$expiresTs.'token_path=/bunnystream999/';
        $expectedToken = rtrim(strtr(base64_encode(hash('sha256', $expectedHashable, true)), '+/', '-_'), '=');
        $this->assertSame($expectedToken, $query['token']);

        $response2 = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->getJson("/api/lessons/{$this->lockedHlsLesson->id}");
        $response2->assertOk();
        parse_str(parse_url($response2->json('playback.url'), PHP_URL_QUERY) ?? '', $query2);
        $expectedHashable2 = 'secret-bunny-key'.'/bunnystream999/'.(int) $query2['expires'].'token_path=/bunnystream999/';
        $expectedToken2 = rtrim(strtr(base64_encode(hash('sha256', $expectedHashable2, true)), '+/', '-_'), '=');
        $this->assertSame($expectedToken2, $query2['token']);
    }

    /**
     * Test 7 (TDD - Ticket 01): Signed HLS URL derives directory token_path when video_id is a full URL.
     */
    public function test_hls_signing_handles_full_url_video_id_and_derives_directory_token_path(): void
    {
        config([
            'services.video.bunny.token_key' => 'secret-bunny-key',
        ]);

        $fullUrlLesson = Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Full URL HLS Lesson',
            'video_id' => 'https://vz-custom.b-cdn.net/guid-123/playlist.m3u8',
            'video_provider' => 'hls',
            'duration' => 300,
            'order' => 5,
            'is_preview' => true,
            'is_published' => true,
        ]);

        Sanctum::actingAs($this->student);
        $response = $this->getJson("/api/lessons/{$fullUrlLesson->id}");
        $response->assertOk();

        parse_str(parse_url($response->json('playback.url'), PHP_URL_QUERY) ?? '', $query);
        $this->assertSame('/guid-123/', $query['token_path'] ?? null);
        $this->assertArrayHasKey('token', $query);
        $this->assertArrayHasKey('expires', $query);
    }

    /**
     * Test 8 (TDD - Ticket 02): Production environment fails-closed for paid YouTube lessons
     * while still allowing YouTube previews and HLS paid lessons.
     */
    public function test_production_fails_closed_for_paid_youtube_lesson_and_logs_critical_while_allowing_youtube_preview(): void
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

        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';
        Log::spy();

        try {
            Sanctum::actingAs($this->student);

            // 1. Paid YouTube lesson must fail-closed (null playback, null video_id, critical log)
            $paidYtRes = $this->getJson("/api/lessons/{$this->lockedYoutubeLesson->id}");
            $paidYtRes->assertOk();
            $paidYtRes->assertJsonPath('playback', null);
            $paidYtRes->assertJsonPath('video_id', null);
            Log::shouldHaveReceived('critical')->once();

            // 2. Free preview YouTube lesson remains playable
            $previewRes = $this->getJson("/api/lessons/{$this->previewLesson->id}");
            $previewRes->assertOk();
            $previewRes->assertJsonPath('playback.provider', 'youtube');
            $previewRes->assertJsonPath('playback.source', 'intro123');

            // 3. Paid HLS lesson remains playable
            $paidHlsRes = $this->getJson("/api/lessons/{$this->lockedHlsLesson->id}");
            $paidHlsRes->assertOk();
            $paidHlsRes->assertJsonPath('playback.provider', 'hls');
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }

    /**
     * Test 9 (TDD - Ticket 02): Production blocks publishing paid lessons without HLS on both POST and PUT.
     */
    public function test_production_blocks_publishing_paid_lesson_without_hls_on_both_create_and_update(): void
    {
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            Sanctum::actingAs($this->instructorUser);

            // 1. POST paid published YouTube lesson -> 422
            $createBlocked = $this->postJson("/api/instructor/sections/{$this->section->id}/lessons", [
                'title' => 'Blocked Paid YouTube Lesson',
                'video_provider' => 'youtube',
                'video_id' => 'yt-blocked',
                'is_preview' => false,
                'is_published' => true,
            ]);
            $createBlocked->assertUnprocessable();

            // 2. POST draft YouTube lesson -> 201 (drafts allowed before HLS migration)
            $createDraft = $this->postJson("/api/instructor/sections/{$this->section->id}/lessons", [
                'title' => 'Draft YouTube Lesson',
                'video_provider' => 'youtube',
                'video_id' => 'yt-draft',
                'is_preview' => false,
                'is_published' => false,
            ]);
            $createDraft->assertCreated();
            $draftId = $createDraft->json('id');

            // 3. PUT publishing existing draft without switching to HLS -> 422
            $updateBlocked = $this->putJson("/api/instructor/lessons/{$draftId}", [
                'is_published' => true,
            ]);
            $updateBlocked->assertUnprocessable();

            // 4. PUT publishing existing draft WITH HLS -> 200
            $updateAllowed = $this->putJson("/api/instructor/lessons/{$draftId}", [
                'is_published' => true,
                'video_provider' => 'hls',
                'video_id' => 'bunny-guid-1',
            ]);
            $updateAllowed->assertOk();
            $updateAllowed->assertJsonPath('playback.provider', 'hls');

            // 5. POST published free preview on YouTube -> 201
            $createPreview = $this->postJson("/api/instructor/sections/{$this->section->id}/lessons", [
                'title' => 'Published YouTube Preview',
                'video_provider' => 'youtube',
                'video_id' => 'yt-preview-ok',
                'is_preview' => true,
                'is_published' => true,
            ]);
            $createPreview->assertCreated();
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }
}
