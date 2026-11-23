<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstructorLessonRequest;
use App\Http\Resources\LessonResource;
use App\Models\CourseActivityLog;
use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class InstructorLessonController extends Controller
{
    /**
     * Creates a lesson under an authorized section.
     */
    public function store(InstructorLessonRequest $request, Section $section): JsonResponse
    {
        $course = $section->course;
        if (! $course) {
            abort(404, 'الكورس المرتبط بالقسم غير موجود');
        }

        Gate::authorize('manageAsInstructor', $course);

        $lesson = $section->lessons()->create($request->validated());

        CourseActivityLog::create([
            'course_id' => $course->id,
            'user_id' => $request->user()?->id,
            'action' => 'lesson_created',
            'changes' => ['title' => ['old' => null, 'new' => $lesson->title]],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(new LessonResource($lesson), 201);
    }

    /**
     * Updates an authorized lesson.
     */
    public function update(InstructorLessonRequest $request, Lesson $lesson): JsonResponse
    {
        $course = $lesson->section?->course;
        if (! $course) {
            abort(404, 'الكورس المرتبط بالدرس غير موجود');
        }

        Gate::authorize('manageAsInstructor', $course);

        $oldTitle = $lesson->title;
        $lesson->update($request->validated());

        CourseActivityLog::create([
            'course_id' => $course->id,
            'user_id' => $request->user()?->id,
            'action' => 'lesson_updated',
            'changes' => ['title' => ['old' => $oldTitle, 'new' => $lesson->title]],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(new LessonResource($lesson));
    }

    /**
     * Deletes a lesson after verifying no completed student progress records exist.
     */
    public function destroy(Lesson $lesson): Response
    {
        $course = $lesson->section?->course;
        if (! $course) {
            abort(404, 'الكورس المرتبط بالدرس غير موجود');
        }

        Gate::authorize('manageAsInstructor', $course);

        // Curriculum Deletion Integrity Guard:
        if ($lesson->progress()->where('completed', true)->exists()) {
            abort(422, 'Cannot delete a lesson with recorded student progress.');
        }

        $title = $lesson->title;
        $lesson->delete();

        CourseActivityLog::create([
            'course_id' => $course->id,
            'user_id' => request()->user()?->id,
            'action' => 'lesson_deleted',
            'changes' => ['title' => ['old' => $title, 'new' => null]],
            'ip_address' => request()->ip(),
        ]);

        return response()->noContent();
    }
}
