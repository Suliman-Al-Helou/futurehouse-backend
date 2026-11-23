<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitTaskRequest;
use App\Models\Lesson;
use App\Models\TaskAttempt;
use App\Models\UserLessonProgress;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    /**
     * GET /api/lessons/{lesson}/task
     * عرض مهمة الدرس — يتحقق أن الفيديو اكتمل أولاً
     */
    public function show(Lesson $lesson): JsonResponse
    {
        $lesson->loadMissing('task');

        if (! $lesson->task) {
            return response()->json(['message' => 'لا توجد مهمة لهذا الدرس'], 404);
        }

        $course = $lesson->getCourse();
        abort_if($course->status === 'draft', 404);
        Gate::authorize('watchLesson', [$course, $lesson]);

        $user = Auth::user();

        // يجب إكمال الفيديو أولاً (مشاهدة ≥ 80%)
        $progress = UserLessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        if (! $progress || (float) $progress->watched_percent < 80) {
            return response()->json([
                'message' => 'يجب مشاهدة ≥ 80% من الفيديو أولاً',
                'watched_percent' => $progress?->watched_percent ?? 0,
            ], 403);
        }

        // عدد المحاولات المتبقية
        $attempts = TaskAttempt::where('user_id', $user->id)
            ->where('task_id', $lesson->task->id)
            ->count();

        $maxAttempts = $lesson->task->max_attempts ?? 3;
        $passed = TaskAttempt::where('user_id', $user->id)
            ->where('task_id', $lesson->task->id)
            ->where('passed', true)
            ->exists();

        $cooldownMinutes = (int) ($lesson->task->cooldown_minutes ?? 0);
        $cooldownRemaining = 0;
        $latestAttempt = TaskAttempt::where('user_id', $user->id)
            ->where('task_id', $lesson->task->id)
            ->latest('id')
            ->first();

        if ($latestAttempt && ! $latestAttempt->passed && $cooldownMinutes > 0) {
            $attemptTime = $latestAttempt->attempted_at ?? $latestAttempt->created_at;
            $diff = $attemptTime ? (int) $attemptTime->diffInMinutes(now()) : 999;
            if ($diff < $cooldownMinutes) {
                $cooldownRemaining = $cooldownMinutes - $diff;
            }
        }

        // إخفاء الإجابات الصحيحة عن الطالب
        $questions = collect($lesson->task->questions ?? [])->map(function ($q, $index) {
            return [
                'id' => $q['id'] ?? $index + 1,
                'type' => $q['type'] ?? 'mcq',       // mcq | true_false | open
                'text' => $q['text'] ?? $q['question'] ?? '',
                'options' => $q['options'] ?? [],
                // لا نُرسل correct_answer
            ];
        });

        return response()->json([
            'task_id' => $lesson->task->id,
            'pass_percentage' => $lesson->task->pass_percentage,
            'max_attempts' => $maxAttempts,
            'attempts_used' => $attempts,
            'attempts_remaining' => max(0, $maxAttempts - $attempts),
            'cooldown_minutes' => $cooldownMinutes,
            'cooldown_remaining_minutes' => $cooldownRemaining,
            'already_passed' => $passed,
            'questions' => $questions,
        ]);
    }

    /**
     * POST /api/lessons/{lesson}/task
     * تسليم إجابات المهمة
     */
    public function submit(SubmitTaskRequest $request, Lesson $lesson): JsonResponse
    {
        $lesson->loadMissing('task');

        if (! $lesson->task) {
            return response()->json(['message' => 'لا توجد مهمة'], 404);
        }

        $course = $lesson->getCourse();
        abort_if($course->status === 'draft', 404);
        Gate::authorize('watchLesson', [$course, $lesson]);

        $user = Auth::user();

        // يجب مشاهدة الفيديو أولاً قبل تسليم المهمة (مشاهدة ≥ 80%)
        $progress = UserLessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        if (! $progress || (float) $progress->watched_percent < 80) {
            return response()->json([
                'message' => 'يجب مشاهدة ≥ 80% من الفيديو أولاً',
                'watched_percent' => $progress?->watched_percent ?? 0,
            ], 403);
        }

        $task = $lesson->task;
        if (! $task) {
            return response()->json(['message' => 'لا توجد مهمة لهذا الدرس'], 404);
        }

        $result = $this->taskService->submitAttempt($task, $user, (array) $request->validated('answers'));

        return response()->json($result['data'], $result['status']);
    }
}
