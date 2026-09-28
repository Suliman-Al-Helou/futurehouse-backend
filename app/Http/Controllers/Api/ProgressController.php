<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\UserLessonProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ProgressController extends Controller
{
    /**
     * POST /api/lessons/{id}/progress
     * يحفظ تقدم المستخدم في الفيديو
     */
    public function update(Request $request, $lessonId)
    {
        $request->validate([
            'watched_percent' => 'required|numeric|min:0|max:100',
            'last_position' => 'required|numeric|min:0',
        ]);

        $lesson = Lesson::findOrFail($lessonId);
        $user = Auth::user();

        Gate::authorize('watchLesson', [$lesson->getCourse(), $lesson]);
        
        $existing = UserLessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        $best = max((float) $request->watched_percent, $existing?->watched_percent ?? 0);

        $progress = UserLessonProgress::updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            [
                'watched_percent' => $best,
                'last_position' => $request->last_position,
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

        $user = Auth::user();

        $enrolled = $user->enrollments()->where('course_id', $courseId)->exists();
        if (! $enrolled) {
            return response()->json(['message' => 'غير مشترك'], 403);
        }

        // جلب كل دروس الكورس
        $lessonIds = Lesson::whereHas('section', fn ($q) => $q->where('course_id', $courseId))
            ->pluck('id');

        $completedCount = UserLessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->where('completed', true)
            ->count();

        $totalLessons = $lessonIds->count();
        $percent = $totalLessons > 0
            ? round(($completedCount / $totalLessons) * 100)
            : 0;

        return response()->json([
            'total_lessons' => $totalLessons,
            'completed_count' => $completedCount,
            'percent' => $percent,
            'course_complete' => $percent === 100,
        ]);
    }
}
