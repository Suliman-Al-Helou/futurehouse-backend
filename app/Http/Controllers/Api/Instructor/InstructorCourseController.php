<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstructorUpdateCourseRequest;
use App\Http\Resources\CourseInstructorResource;
use App\Models\Course;
use App\Models\User;
use App\Services\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InstructorCourseController extends Controller
{
    public function __construct(
        private readonly CourseService $courseService,
    ) {}

    /**
     * Returns courses scoped to the authenticated instructor, or all courses for administrators.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isAdmin()) {
            $query = Course::query();

            if ($request->boolean('managed_by_me')) {
                // Admin Fallback Custody: unassigned courses or courses assigned to admin's instructor profile
                $instructorId = $user->instructor?->id;
                $query->where(function ($q) use ($instructorId) {
                    $q->whereNull('instructor_id');
                    if ($instructorId) {
                        $q->orWhere('instructor_id', $instructorId);
                    }
                });
            }

            $courses = $query->with('instructor')->get();

            return response()->json([
                'data' => CourseInstructorResource::collection($courses),
            ]);
        }

        $instructor = $user->instructor;

        if (! $instructor) {
            return response()->json([
                'data' => [],
            ]);
        }

        $courses = Course::where('instructor_id', $instructor->id)
            ->with('instructor')
            ->get();

        return response()->json([
            'data' => CourseInstructorResource::collection($courses),
        ]);
    }

    /**
     * Returns assigned course details, strictly authorized via CoursePolicy::manageAsInstructor.
     */
    public function show(Request $request, Course $course): JsonResponse
    {
        Gate::authorize('manageAsInstructor', $course);

        return response()->json(new CourseInstructorResource($course->load('instructor')));
    }

    /**
     * Updates assigned course educational metadata, strictly authorized via CoursePolicy::manageAsInstructor.
     */
    public function update(InstructorUpdateCourseRequest $request, Course $course): JsonResponse
    {
        Gate::authorize('manageAsInstructor', $course);

        $validated = $request->validated();
        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')->store('courses', 'public');
        }

        $updated = $this->courseService->update($course, $validated);

        return response()->json(new CourseInstructorResource($updated->load('instructor')));
    }
}
