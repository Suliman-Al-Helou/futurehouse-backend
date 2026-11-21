<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstructorLessonRequest;
use App\Http\Resources\LessonResource;
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

        $lesson->update($request->validated());

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

        $lesson->delete();

        return response()->noContent();
    }
}
