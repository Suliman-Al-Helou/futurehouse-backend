<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstructorSectionRequest;
use App\Http\Resources\SectionResource;
use App\Models\Course;
use App\Models\CourseActivityLog;
use App\Models\Section;
use App\Services\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class InstructorSectionController extends Controller
{
    public function __construct(
        private readonly CourseService $courseService,
    ) {}

    /**
     * Lists curriculum sections and lessons for an authorized course.
     */
    public function index(Course $course): JsonResponse
    {
        Gate::authorize('manageAsInstructor', $course);

        $course->load(['sections.lessons']);

        return response()->json([
            'sections' => SectionResource::collection($course->sections),
        ]);
    }

    /**
     * Adds a section to an authorized course.
     */
    public function store(InstructorSectionRequest $request, Course $course): JsonResponse
    {
        Gate::authorize('manageAsInstructor', $course);

        $section = $this->courseService->addSection($course, $request->validated());

        CourseActivityLog::create([
            'course_id' => $course->id,
            'user_id' => $request->user()?->id,
            'action' => 'section_created',
            'changes' => ['title' => ['old' => null, 'new' => $section->title]],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(new SectionResource($section), 201);
    }

    /**
     * Updates an authorized section.
     */
    public function update(InstructorSectionRequest $request, Section $section): JsonResponse
    {
        $course = $section->course;
        if (! $course) {
            abort(404, 'الكورس المرتبط بالقسم غير موجود');
        }

        Gate::authorize('manageAsInstructor', $course);

        $oldTitle = $section->title;
        $section->update($request->validated());

        CourseActivityLog::create([
            'course_id' => $course->id,
            'user_id' => $request->user()?->id,
            'action' => 'section_updated',
            'changes' => ['title' => ['old' => $oldTitle, 'new' => $section->title]],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(new SectionResource($section));
    }

    /**
     * Deletes a section after verifying no completed student progress records exist.
     */
    public function destroy(Section $section): Response
    {
        $course = $section->course;
        if (! $course) {
            abort(404, 'الكورس المرتبط بالقسم غير موجود');
        }

        Gate::authorize('manageAsInstructor', $course);

        // Curriculum Deletion Integrity Guard:
        $hasCompletedProgress = $section->lessons()
            ->whereHas('progress', fn ($q) => $q->where('completed', true))
            ->exists();

        if ($hasCompletedProgress) {
            abort(422, 'Cannot delete a section with recorded student progress.');
        }

        $title = $section->title;
        $section->delete();

        CourseActivityLog::create([
            'course_id' => $course->id,
            'user_id' => request()->user()?->id,
            'action' => 'section_deleted',
            'changes' => ['title' => ['old' => $title, 'new' => null]],
            'ip_address' => request()->ip(),
        ]);

        return response()->noContent();
    }
}
