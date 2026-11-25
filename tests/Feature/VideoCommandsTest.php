<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VideoCommandsTest extends TestCase
{
    use RefreshDatabase;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $instructorUser = User::factory()->create();
        $instructorUser->assignRole('instructor');
        $instructor = $instructorUser->instructor ?? Instructor::create([
            'user_id' => $instructorUser->id,
            'name' => 'Test Instructor',
            'email' => 'inst@example.com',
        ]);

        $course = Course::create([
            'title' => 'HLS Launch Gate Course',
            'description' => 'Course for auditing video providers',
            'status' => 'published',
            'price' => 100,
            'is_public' => false,
            'instructor_id' => $instructor->id,
        ]);

        $this->section = Section::create([
            'course_id' => $course->id,
            'title' => 'Section 1',
            'order' => 1,
        ]);
    }

    public function test_audit_providers_passes_when_only_previews_and_drafts_use_youtube(): void
    {
        Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Paid HLS Lesson',
            'video_id' => 'bunny-123',
            'video_provider' => 'hls',
            'is_preview' => false,
            'is_published' => true,
            'order' => 1,
        ]);

        Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Preview YouTube Lesson',
            'video_id' => 'yt-preview',
            'video_provider' => 'youtube',
            'is_preview' => true,
            'is_published' => true,
            'order' => 2,
        ]);

        Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Draft YouTube Lesson',
            'video_id' => 'yt-draft',
            'video_provider' => 'youtube',
            'is_preview' => false,
            'is_published' => false,
            'order' => 3,
        ]);

        $this->artisan('videos:audit-providers')
            ->expectsOutputToContain('ALLOWED/WARN')
            ->expectsOutputToContain('INFO')
            ->assertExitCode(0);
    }

    public function test_audit_providers_fails_with_exit_code_1_if_published_paid_lesson_uses_youtube_or_missing_video(): void
    {
        Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Published Paid YouTube Lesson',
            'video_id' => 'yt-paid-leak',
            'video_provider' => 'youtube',
            'is_preview' => false,
            'is_published' => true,
            'order' => 1,
        ]);

        $this->artisan('videos:audit-providers')
            ->expectsOutputToContain('FAIL')
            ->assertExitCode(1);
    }

    public function test_import_hls_applies_json_and_csv_mappings_to_youtube_lessons(): void
    {
        $lesson1 = Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Lesson 1',
            'video_id' => 'yt-old-1',
            'video_provider' => 'youtube',
            'is_preview' => false,
            'is_published' => true,
            'order' => 1,
        ]);

        $lesson2 = Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Lesson 2',
            'video_id' => 'yt-old-2',
            'video_provider' => 'youtube',
            'is_preview' => false,
            'is_published' => true,
            'order' => 2,
        ]);

        $jsonPath = sys_get_temp_dir().'/hls_map_'.uniqid().'.json';
        file_put_contents($jsonPath, json_encode(['yt-old-1' => 'bunny-guid-1']));

        $csvPath = sys_get_temp_dir().'/hls_map_'.uniqid().'.csv';
        file_put_contents($csvPath, "youtube_id,bunny_guid\nyt-old-2,bunny-guid-2\n");

        try {
            $this->artisan('videos:import-hls', ['--map' => $jsonPath])
                ->assertExitCode(0);

            $lesson1->refresh();
            $lesson2->refresh();
            $this->assertSame('hls', $lesson1->video_provider);
            $this->assertSame('bunny-guid-1', $lesson1->video_id);
            $this->assertSame('youtube', $lesson2->video_provider);

            $this->artisan('videos:import-hls', ['--map' => $csvPath])
                ->assertExitCode(0);

            $lesson2->refresh();
            $this->assertSame('hls', $lesson2->video_provider);
            $this->assertSame('bunny-guid-2', $lesson2->video_id);
        } finally {
            @unlink($jsonPath);
            @unlink($csvPath);
        }
    }

    public function test_import_hls_uploads_local_mp4_directory_to_bunny_stream(): void
    {
        config([
            'services.video.bunny.library_id' => '99887',
            'services.video.bunny.api_key' => 'bunny-api-secret',
        ]);

        Http::fake([
            'https://video.bunnycdn.com/library/99887/videos' => Http::response([
                'guid' => 'uploaded-bunny-guid-777',
            ], 200),
            'https://video.bunnycdn.com/library/99887/videos/uploaded-bunny-guid-777' => Http::response([
                'success' => true,
            ], 200),
        ]);

        $lesson = Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Local MP4 Lesson',
            'video_id' => 'yt-local-1',
            'video_provider' => 'youtube',
            'is_preview' => false,
            'is_published' => true,
            'order' => 1,
        ]);

        $dir = sys_get_temp_dir().'/hls_mp4_'.uniqid();
        mkdir($dir, 0755, true);
        $mp4Path = $dir."/{$lesson->id}.mp4";
        file_put_contents($mp4Path, 'fake-mp4-binary-data');

        try {
            $this->artisan('videos:import-hls', ['--dir' => $dir])
                ->assertExitCode(0);

            $lesson->refresh();
            $this->assertSame('hls', $lesson->video_provider);
            $this->assertSame('uploaded-bunny-guid-777', $lesson->video_id);
        } finally {
            @unlink($mp4Path);
            @rmdir($dir);
        }
    }
}
