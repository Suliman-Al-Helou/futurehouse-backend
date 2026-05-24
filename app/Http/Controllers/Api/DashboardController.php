<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\TaskAttempt;
use App\Models\UserLessonProgress;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        // الكورسات المسجل فيها
        $enrollments = Enrollment::where('user_id', $user->id)
            ->with('course')
            ->get();

        $enrolledCount   = $enrollments->count();
        $completedCount  = $enrollments->where('status', 'completed')->count();

        // ساعات التعلم
        $completedLessons = UserLessonProgress::where('user_id', $user->id)
            ->where('completed', true)
            ->count();

        $learningHours = round(($completedLessons * 30) / 60);

        // نتائج الامتحانات
        $taskAttempts = TaskAttempt::where('user_id', $user->id)
            ->with('task.lesson.section.course')
            ->latest()
            ->get()
            ->map(fn($attempt) => [
                'lesson' => $attempt->task->lesson->title    ?? '',
                'course' => $attempt->task->lesson->section->course->title ?? '',
                'score'  => $attempt->score,
                'passed' => $attempt->passed,
                'date'   => $attempt->created_at->format('Y/n/j'),
            ]);

        $passRate = $taskAttempts->count() > 0
            ? round(($taskAttempts->where('passed', true)->count() / $taskAttempts->count()) * 100)
            : 0;

        // حساب الأيام المتواصلة
        $progressDates = UserLessonProgress::where('user_id', $user->id)
            ->where('completed', true)
            ->orderBy('updated_at', 'desc')
            ->pluck('updated_at')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->unique()
            ->values();

        $streak = 0;
        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        if ($progressDates->contains($today) || $progressDates->contains($yesterday)) {
            $checkDate = $progressDates->contains($today) ? now() : now()->subDay();
            foreach ($progressDates as $date) {
                if ($date === $checkDate->format('Y-m-d')) {
                    $streak++;
                    $checkDate->subDay();
                } else {
                    break;
                }
            }
        }

        return response()->json([
            'stats' => [
                'enrolled_courses'  => $enrolledCount,
                'completed_lessons' => $completedLessons,
                'learning_hours'    => $learningHours,
                'completed_courses' => $completedCount,
                'streak'            => $streak,
            ],
            'exam_results' => $taskAttempts,
            'pass_rate'    => $passRate,
        ]);
    }
}