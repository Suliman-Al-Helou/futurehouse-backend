<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstructorUpdateCourseRequest;
use App\Http\Resources\CourseInstructorResource;
use App\Models\Course;
use App\Models\User;
use App\Models\UserLessonProgress;
use App\Services\CourseService;
use App\Services\YouTubeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InstructorCourseController extends Controller
{
    public function __construct(
        private readonly CourseService $courseService,
    ) {}

    /**
     * Lists courses assigned to the authenticated instructor or all courses for administrators.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Course::query()->with('instructor');

        if ($user->isAdmin()) {
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
        } else {
            $instructorId = $user->instructor?->id;

            // Safe fallback: if user has no linked instructor profile, return empty list cleanly
            if ($instructorId === null) {
                return response()->json([
                    'data' => [],
                    'courses' => [],
                ]);
            }

            $query->where('instructor_id', $instructorId);
        }

        $courses = $query->latest()->get();
        $resourceCollection = CourseInstructorResource::collection($courses);

        return response()->json([
            'data' => $resourceCollection,
            'courses' => $resourceCollection,
        ]);
    }

    /**
     * Shows course educational details, strictly authorized via CoursePolicy::manageAsInstructor.
     */
    public function show(Course $course): JsonResponse
    {
        Gate::authorize('manageAsInstructor', $course);

        return response()->json(new CourseInstructorResource($course->load('instructor')));
    }

    /**
     * Updates assigned course educational metadata with silent commercial stripping and audit logging.
     */
    public function update(InstructorUpdateCourseRequest $request, Course $course): JsonResponse
    {
        Gate::authorize('manageAsInstructor', $course);

        $updatedCourse = $this->courseService->updateEducationalMetadata(
            $course,
            $request->validated(),
            $request->user(),
            $request->ip()
        );

        return response()->json(new CourseInstructorResource($updatedCourse->load('instructor')));
    }

    /**
     * Returns YouTube playlist videos for the assigned course.
     */
    public function youtubeVideos(Course $course, YouTubeService $youtubeService): JsonResponse
    {
        Gate::authorize('manageAsInstructor', $course);

        return response()->json($youtubeService->getCoursePlaylistVideos($course));
    }

    /**
     * Lists approved enrolled students for the assigned course with bounded pagination and progress.
     */
    public function students(Request $request, Course $course): JsonResponse
    {
        Gate::authorize('manageAsInstructor', $course);

        $perPage = min(max($request->integer('per_page', 20), 1), 50);

        $lessonIds = $course->lessons()->pluck('lessons.id');
        $totalLessonsCount = $lessonIds->count();

        $enrollments = $course->enrollments()
            ->where('status', 'approved')
            ->with(['user:id,name'])
            ->latest('enrolled_at')
            ->paginate($perPage);

        $userIds = $enrollments->getCollection()
            ->pluck('user_id')
            ->unique()
            ->filter();

        $completedCounts = $userIds->isEmpty() || $lessonIds->isEmpty()
            ? collect()
            : UserLessonProgress::query()
                ->whereIn('user_id', $userIds)
                ->whereIn('lesson_id', $lessonIds)
                ->where('completed', true)
                ->selectRaw('user_id, COUNT(*) as completed_count')
                ->groupBy('user_id')
                ->pluck('completed_count', 'user_id');

        $enrollments->through(function ($enrollment) use ($completedCounts, $totalLessonsCount): array {
            $completedCount = (int) ($completedCounts[$enrollment->user_id] ?? 0);
            $progressPercent = $totalLessonsCount > 0
                ? (int) round(($completedCount / $totalLessonsCount) * 100)
                : 0;

            return [
                'id' => $enrollment->user?->id ?? $enrollment->user_id,
                'name' => $enrollment->user?->name,
                'enrolled_at' => $enrollment->enrolled_at?->toISOString(),
                'completed_lessons_count' => $completedCount,
                'total_lessons_count' => $totalLessonsCount,
                'progress_percent' => $progressPercent,
            ];
        });

        return response()->json($enrollments);
    }
}
