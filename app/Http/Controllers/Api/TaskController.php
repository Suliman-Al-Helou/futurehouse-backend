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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
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

        // يجب إكمال الفيديو أولاً قبل تسليم المهمة
        $progress = UserLessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        if (! $progress || ! $progress->completed) {
            return response()->json([
                'message' => 'يجب مشاهدة ≥ 80% من الفيديو أولاً',
                'watched_percent' => $progress?->watched_percent ?? 0,
            ], 403);
        }

        $task = $lesson->task;
        $maxAttempts = $task->max_attempts ?? 3;

        return DB::transaction(function () use ($task, $user, $request, $maxAttempts) {
            // تحقق من عدد المحاولات مع قفل لمنع التزامن
            $attemptsUsed = TaskAttempt::where('user_id', $user->id)
                ->where('task_id', $task->id)
                ->lockForUpdate()
                ->count();

            if ($attemptsUsed >= $maxAttempts) {
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

            // تحقق من فترة الانتظار بين المحاولات الفاشلة
            $cooldownMinutes = (int) ($task->cooldown_minutes ?? 0);
            if ($cooldownMinutes > 0) {
                $latestAttempt = TaskAttempt::where('user_id', $user->id)
                    ->where('task_id', $task->id)
                    ->latest('id')
                    ->first();

                if ($latestAttempt && ! $latestAttempt->passed) {
                    $attemptTime = $latestAttempt->attempted_at ?? $latestAttempt->created_at;
                    $diff = $attemptTime ? (int) $attemptTime->diffInMinutes(now()) : 999;
                    if ($diff < $cooldownMinutes) {
                        $remainingCooldown = $cooldownMinutes - $diff;

                        return response()->json([
                            'message' => "يجب الانتظار {$remainingCooldown} دقيقة قبل المحاولة التالية",
                            'cooldown_remaining_minutes' => $remainingCooldown,
                        ], 429);
                    }
                }
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

            $newAttemptsUsed = $attemptsUsed + 1;
            $attemptsRemaining = max(0, $maxAttempts - $newAttemptsUsed);
            $revealAnswers = $passed || ($newAttemptsUsed >= $maxAttempts);

            // حجب الإجابات الصحيحة والتوضيحات إذا لم ينجح وما زالت هناك محاولات متبقية لمنع الغش
            if (! $revealAnswers) {
                foreach ($results as $qId => $res) {
                    if (isset($res['type']) && $res['type'] === 'open') {
                        continue;
                    }
                    unset($results[$qId]['correct_answer'], $results[$qId]['explanation']);
                }
            }

            return response()->json([
                'score' => $score,
                'passed' => $passed,
                'pass_score' => $task->pass_percentage ?? 70,
                'reveal_answers' => $revealAnswers,
                'attempts_used' => $newAttemptsUsed,
                'attempts_remaining' => $attemptsRemaining,
                'results' => $results,
                'message' => $passed
                    ? '✅ أحسنت! يمكنك الانتقال للدرس التالي'
                    : "❌ لم تنجح. درجتك {$score}% والمطلوب ".($task->pass_percentage ?? 70).'%. متبقي لك '.$attemptsRemaining.' محاولات.',
            ]);
        });
    }
}
