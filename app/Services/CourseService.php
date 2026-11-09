<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\Section;
use App\Support\ContentCache;

final class CourseService
{
    public function __construct(
        private readonly ?YouTubeService $youtubeService = null
    ) {}

    /** Creates a course and invalidates the public course cache. */
    public function create(array $data): Course
    {
        $course = Course::create($data);
        ContentCache::forgetCourses();

        try {
            if ($this->youtubeService && $this->youtubeService->isConnected()) {
                $playlistId = $this->youtubeService->createCoursePlaylist($course->title, $course->description ?? '');
                if ($playlistId) {
                    $course->update(['youtube_playlist_id' => $playlistId]);
                }
            }
        } catch (\Throwable $e) {
            // Log but don't fail course creation
        }

        return $course;
    }

    /** Updates a course and invalidates the public course cache. */
    public function update(Course $course, array $data): Course
    {
        $course->update($data);
        ContentCache::forgetCourses();

        return $course->refresh();
    }

    /** Deletes a course (sections/lessons cascade at DB level) and invalidates cache. */
    public function delete(Course $course): void
    {
        $course->delete();
        ContentCache::forgetCourses();
    }

    /** Adds a section; defaults its order to the end of the course. */
    public function addSection(Course $course, array $data): Section
    {
        return $course->sections()->create([
            'title' => $data['title'],
            'order' => $data['order'] ?? $course->sections()->count(),
        ]);
    }
}
