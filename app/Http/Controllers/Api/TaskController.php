<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitTaskRequest;
use App\Models\Lesson;
use App\Models\TaskAttempt;
use App\Models\UserLessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    /**
     * GET /api/lessons/{lesson}/task
     * عرض مهمة الدرس — يتحقق أن الفيديو اكتمل أولاً
     */
    public function show(Lesson $lesson): JsonResponse
    {
        $user = Auth::user();
        $lesson->loadMissing('task');

        if (! $lesson->task) {
            return response()->json(['message' => 'لا توجد مهمة لهذا الدرس'], 404);
        }

        // يجب إكمال الفيديو أولاً
        $progress = UserLessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        if (! $progress || ! $progress->completed) {
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
        $user = Auth::user();
        $lesson->loadMissing('task');

        if (! $lesson->task) {
            return response()->json(['message' => 'لا توجد مهمة'], 404);
        }

        $task = $lesson->task;

        // تحقق من عدد المحاولات
        $attemptsUsed = TaskAttempt::where('user_id', $user->id)
            ->where('task_id', $task->id)
            ->count();

        if ($attemptsUsed >= ($task->max_attempts ?? 3)) {
            return response()->json([
                'message' => 'استنفذت عدد المحاولات المسموح بها',
            ], 429);
        }

        // هل نجح سابقاً؟
        $alreadyPassed = TaskAttempt::where('user_id', $user->id)
            ->where('task_id', $task->id)
            ->where('passed', true)
            ->exists();

        if ($alreadyPassed) {
            return response()->json(['message' => 'نجحت في هذه المهمة مسبقاً', 'passed' => true]);
        }

        // تصحيح الإجابات
        $questions = collect($task->questions ?? []);
        $userAnswers = $request->validated('answers'); // ['question_id' => 'answer']
        $results = [];
        $correctCount = 0;
        $gradableCount = 0;

        foreach ($questions as $index => $q) {
            $qId = $q['id'] ?? $index + 1;
            $type = $q['type'] ?? 'mcq';
            $userAns = $userAnswers[$qId] ?? $userAnswers[(string) $qId] ?? null;

            if ($type === 'open') {
                // الأسئلة المفتوحة تحتاج تصحيح يدوي
                $results[$qId] = [
                    'type' => 'open',
                    'answer' => $userAns,
                    'pending' => true,
                ];

                continue;
            }

            $gradableCount++;
            $correct = (string) ($q['correct_answer'] ?? '');
            $isCorrect = is_scalar($userAns)
                && strtolower(trim((string) $userAns)) === strtolower(trim($correct));

            if ($isCorrect) {
                $correctCount++;
            }

            $results[$qId] = [
                'correct' => $isCorrect,
                'your_answer' => $userAns,
                'correct_answer' => $correct,
                'explanation' => $q['explanation'] ?? null,
            ];
        }

        $score = $gradableCount > 0 ? (int) round(($correctCount / $gradableCount) * 100) : 0;
        $passed = $score >= ($task->pass_percentage ?? 70);

        // حفظ المحاولة
        TaskAttempt::create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'answers' => $userAnswers,
            'score' => $score,
            'passed' => $passed,
            'attempted_at' => now(),
        ]);

        return response()->json([
            'score' => $score,
            'passed' => $passed,
            'pass_score' => $task->pass_percentage ?? 70,
            'results' => $results,
            'message' => $passed
                ? '✅ أحسنت! يمكنك الانتقال للدرس التالي'
                : "❌ لم تنجح. درجتك {$score}% والمطلوب ".($task->pass_percentage ?? 70).'%',
        ]);
    }
}
