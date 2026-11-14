<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLessonProgressRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\UserLessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ProgressController extends Controller
{
    /**
     * POST /api/lessons/{id}/progress
     * يحفظ تقدم المستخدم في الفيديو
     */
    public function update(UpdateLessonProgressRequest $request, $lessonId)
    {
        $validated = $request->validated();

        $lesson = Lesson::findOrFail($lessonId);
        $user = Auth::user();

        Gate::authorize('watchLesson', [$lesson->getCourse(), $lesson]);

        $existing = UserLessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        $best = max((float) $validated['watched_percent'], $existing?->watched_percent ?? 0);

        $progress = UserLessonProgress::updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            [
                'watched_percent' => $best,
                'last_position' => (int) round((float) $validated['last_position']),
                'completed' => $best >= 80,
            ]
        );

        return response()->json([
            'watched_percent' => $progress->watched_percent,
            'completed' => $progress->completed,
            'task_unlocked' => $progress->completed,
        ]);
    }

    /**
     * GET /api/lessons/in-progress
     * يجلب الدروس قيد المتابعة للطالب (غير مكتملة ونسبة مشاهدتها > 0) دفعة واحدة
     */
    public function inProgress(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $inProgress = UserLessonProgress::query()
            ->where('user_id', $userId)
            ->where('completed', false)
            ->where('watched_percent', '>', 0)
            ->with(['lesson.section.course'])
            ->latest('updated_at')
            ->limit(30)
            ->get();

        $items = $inProgress->map(function ($item) {
            $lesson = $item->lesson;
            $section = $lesson?->section;
            $course = $section?->course;

            if (! $lesson || ! $section || ! $course) {
                return null;
            }

            return [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'watched_percent' => (float) $item->watched_percent,
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title,
                ],
                'section' => [
                    'id' => $section->id,
                    'title' => $section->title,
                ],
            ];
        })->filter()->values();

        return response()->json($items);
    }

    /**
     * GET /api/lessons/{id}/progress
     * يجلب آخر تقدم للمستخدم في هذا الدرس
     */
    public function show($lessonId)
    {
        $user = Auth::user();
        $progress = UserLessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lessonId)
            ->first();

        return response()->json([
            'watched_percent' => $progress?->watched_percent ?? 0,
            'last_position' => $progress?->last_position ?? 0,
            'completed' => $progress?->completed ?? false,
        ]);
    }

    /**
     * GET /api/courses/{id}/progress-summary
     * ملخص تقدم المستخدم في كورس كامل
     */
    public function courseSummary(Course $course)
    {
        Gate::authorize('watch', $course);

        $courseId = $course->id;
        $user = Auth::user();

        if (! $course->isFree() && ! $user->enrollments()->where('course_id', $courseId)->exists()) {
            return response()->json(['message' => 'غير مشترك'], 403);
        }

        // جلب كل دروس الكورس
        $lessonIds = Lesson::whereHas('section', fn ($q) => $q->where('course_id', $courseId))
            ->pluck('id');

        $completedLessonIds = UserLessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->where('completed', true)
            ->pluck('lesson_id')
            ->values();

        $completedCount = $completedLessonIds->count();
        $totalLessons = $lessonIds->count();
        $percent = $totalLessons > 0
            ? (int) round(($completedCount / $totalLessons) * 100)
            : 0;

        return response()->json([
            'total_lessons' => $totalLessons,
            'completed_count' => $completedCount,
            'completed_lesson_ids' => $completedLessonIds,
            'percent' => $percent,
            'course_complete' => $percent === 100,
        ]);
    }
}
