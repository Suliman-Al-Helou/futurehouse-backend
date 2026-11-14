<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Http\Resources\CourseAdminResource;
use App\Models\Course;
use App\Services\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminCourseController extends Controller
{
    public function __construct(
        private readonly CourseService $courseService,
    ) {}

    /** Admin course list with instructor and counts. */
    public function index(): AnonymousResourceCollection
    {
        return CourseAdminResource::collection(Course::forAdminList()->get());
    }

    /** Creates a course. */
    public function store(StoreCourseRequest $request): JsonResponse
    {
        $course = $this->courseService->create($request->validated());

        return response()->json(
            new CourseAdminResource($course->load('instructor')->loadCount(Course::adminCounts())),
            201
        );
    }

    /** Single course with instructor and counts for the edit modal. */
    public function show(Course $course): JsonResponse
    {
        $course->load(['instructor'])->loadCount(Course::adminCounts());

        return response()->json(new CourseAdminResource($course));
    }

    /** Updates a course. */
    public function update(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        $updated = $this->courseService->update($course, $request->validated());

        return response()->json(
            new CourseAdminResource($updated->load('instructor')->loadCount(Course::adminCounts()))
        );
    }

    /** Deletes a course. */
    public function destroy(Course $course): Response|JsonResponse
    {
        if ($course->enrollments()->withTrashed()->exists()) {
            return response()->json([
                'message' => 'Cannot delete course because students are enrolled.',
            ], 409);
        }

        try {
            $this->courseService->delete($course);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->noContent();
    }
}
