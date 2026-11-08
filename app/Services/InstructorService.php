<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\Instructor;
use App\Support\ContentCache;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class InstructorService
{
    /** Creates an instructor and assigns courses atomically. */
    public function create(array $data): Instructor
    {
        $instructor = DB::transaction(function () use ($data): Instructor {
            $instructor = Instructor::create(Arr::except($data, 'courses'));
            $this->syncCourses($instructor, $data['courses'] ?? null);

            return $instructor;
        });

        ContentCache::forgetInstructors();

        return $instructor->loadCount('courses');
    }

    /** Updates an instructor; courses are synced only if the key was sent. */
    public function update(Instructor $instructor, array $data): Instructor
    {
        DB::transaction(function () use ($instructor, $data): void {
            $instructor->update(Arr::except($data, 'courses'));
            $this->syncCourses($instructor, $data['courses'] ?? null);
        });

        ContentCache::forgetInstructors();

        return $instructor->refresh()->loadCount('courses');
    }

    /** Deletes an instructor (courses.instructor_id is nullOnDelete in DB). */
    public function delete(Instructor $instructor): void
    {
        $instructor->delete();
        ContentCache::forgetInstructors();
    }

    /** null = leave assignments untouched; [] = unassign all. */
    private function syncCourses(Instructor $instructor, ?array $courseIds): void
    {
        if ($courseIds === null) {
            return;
        }

        Course::where('instructor_id', $instructor->id)
            ->whereNotIn('id', $courseIds)
            ->update(['instructor_id' => null]);

        if ($courseIds !== []) {
            Course::whereIn('id', $courseIds)->update(['instructor_id' => $instructor->id]);
        }
    }
}
