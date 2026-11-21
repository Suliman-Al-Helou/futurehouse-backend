<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\CourseActivityLog;
use App\Models\Section;
use App\Models\User;
use App\Support\ContentCache;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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
        if ($course->enrollments()->withTrashed()->exists()) {
            throw new ConflictHttpException('Cannot delete course because students are enrolled.');
        }

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

    /**
     * Updates educational metadata of a course, storing any uploaded cover image,
     * calculating exact attribute diffs, writing to course_activity_logs inside a transaction,
     * and invalidating content caches.
     */
    public function updateEducationalMetadata(
        Course $course,
        array $validated,
        ?User $user = null,
        ?string $ipAddress = null
    ): Course {
        // Handle cover_image file upload if present
        if (isset($validated['cover_image']) && $validated['cover_image'] instanceof UploadedFile) {
            $file = $validated['cover_image'];
            $filename = uniqid('cover_').'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('courses/covers', $filename, 'public');

            // Prune old cover image if stored locally
            $oldCover = $course->cover_image;
            if ($oldCover && str_contains($oldCover, 'courses/covers/')) {
                $oldRelative = str_replace('/storage/', '', parse_url($oldCover, PHP_URL_PATH) ?? $oldCover);
                Storage::disk('public')->delete($oldRelative);
            }

            $validated['cover_image'] = Storage::disk('public')->url($path);
        }

        // Calculate diff between existing course educational attributes and incoming values
        $diff = [];
        $educationalKeys = [
            'title',
            'description',
            'cover_image',
            'level',
            'what_you_learn',
            'requirements',
            'target_audience',
        ];

        foreach ($educationalKeys as $key) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }

            $oldValue = $course->{$key};
            $newValue = $validated[$key];

            $isDifferent = false;
            if (is_array($oldValue) || is_array($newValue)) {
                $normOld = is_array($oldValue) ? array_values($oldValue) : [];
                $normNew = is_array($newValue) ? array_values($newValue) : [];
                $isDifferent = ($normOld !== $normNew);
            } else {
                $isDifferent = ((string) $oldValue !== (string) $newValue);
            }

            if ($isDifferent) {
                $diff[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        // Filter $validated to strictly only update educational keys (Silent Stripping defense in-depth)
        $attributesToUpdate = Arr::only($validated, $educationalKeys);

        // If no changes, return course without creating empty activity log
        if (empty($diff)) {
            return $course;
        }

        DB::transaction(function () use ($course, $attributesToUpdate, $diff, $user, $ipAddress): void {
            $course->update($attributesToUpdate);

            CourseActivityLog::create([
                'course_id' => $course->id,
                'user_id' => $user?->id,
                'action' => 'course_metadata_updated',
                'changes' => $diff,
                'ip_address' => $ipAddress,
            ]);
        });

        ContentCache::forgetCourses();

        return $course->refresh();
    }
}
