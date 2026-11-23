<?php

// FILE: app/Services/TaskService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lesson;
use App\Models\Task;
use App\Models\TaskAttempt;
use App\Models\User;
use App\Models\UserLessonProgress;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class TaskService
{
    /** Creates the lesson's task; a lesson may have only one (409 otherwise). */
    public function store(Lesson $lesson, array $data): Task
    {
        // 1. فتح عملية داخل قاعدة البيانات لضمان تنفيذ الخطوات كحزمة واحدة
        $task = DB::transaction(function () use ($lesson, $data): Task {

            // 2. قفل سجل الدرس الحالي في قاعدة البيانات لمنع أي مسؤول آخر من فحص أو تعديل هذا الدرس في نفس اللحظة
            $lockedLesson = Lesson::lockForUpdate()->findOrFail($lesson->getKey());

            // 3. الفحص الآمن الآن بعد قفل السجل
            if ($lockedLesson->task()->exists()) {
                throw new ConflictHttpException('هذا الدرس لديه مهمة بالفعل');
            }

            // 4. إنشاء المهمة عبر السجل المقفل والمحمي
            return $lockedLesson->task()->create($data);
        });

        // 5. إرجاع المهمة التي تم إنشاؤها بنجاح
        return $task;
    }

    /**
     * Handles student task attempt submission under an isolated lock.
     *
     * @param  array<string|int, mixed>  $userAnswers
     * @return array{status: int, data: array<string, mixed>}
     */
    public function submitAttempt(Task $task, User $user, array $userAnswers): array
    {
        $maxAttempts = $task->max_attempts ?? 3;

        return DB::transaction(function () use ($task, $user, $userAnswers, $maxAttempts): array {
            // تحقق من مشاهدة ≥ 80% من الفيديو أولاً
            $userProgress = UserLessonProgress::where('user_id', $user->id)
                ->where('lesson_id', $task->lesson_id)
                ->first();

            if (! $userProgress || (float) $userProgress->watched_percent < 80) {
                return [
                    'status' => 403,
                    'data' => [
                        'message' => 'يجب مشاهدة ≥ 80% من الفيديو أولاً',
                        'watched_percent' => $userProgress ? $userProgress->watched_percent : 0,
                    ],
                ];
            }

            // تحقق من عدد المحاولات مع قفل لمنع التزامن
            $attempts = TaskAttempt::where('user_id', $user->id)
                ->where('task_id', $task->id)
                ->lockForUpdate()
                ->get();

            $attemptsUsed = $attempts->count();

            if ($attemptsUsed >= $maxAttempts) {
                return [
                    'status' => 429,
                    'data' => [
                        'message' => 'استنفذت عدد المحاولات المسموح بها',
                    ],
                ];
            }

            // هل نجح سابقاً؟
            if ($attempts->contains('passed', true)) {
                return [
                    'status' => 200,
                    'data' => [
                        'message' => 'نجحت في هذه المهمة مسبقاً',
                        'passed' => true,
                    ],
                ];
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

                        return [
                            'status' => 429,
                            'data' => [
                                'message' => "يجب الانتظار {$remainingCooldown} دقيقة قبل المحاولة التالية",
                                'cooldown_remaining_minutes' => $remainingCooldown,
                            ],
                        ];
                    }
                }
            }

            // تصحيح الإجابات
            $questions = collect($task->questions ?? []);
            $results = [];
            $correctCount = 0;
            $gradableCount = 0;

            foreach ($questions as $index => $q) {
                $qId = (string) ($q['id'] ?? $index + 1);
                $type = $q['type'] ?? 'mcq';
                $userAns = $userAnswers[$qId] ?? (is_numeric($qId) ? ($userAnswers[(int) $qId] ?? null) : null);

                if ($type === 'open') {
                    $results[$qId] = [
                        'type' => 'open',
                        'answer' => $userAns,
                        'pending' => true,
                    ];

                    continue;
                }

                $gradableCount++;
                $correct = $q['correct_answer'] ?? '';

                $isCorrect = false;
                if (is_numeric($userAns) && is_numeric($correct)) {
                    $isCorrect = (int) $userAns === (int) $correct;
                } elseif (is_scalar($userAns)) {
                    $isCorrect = strtolower(trim((string) $userAns)) === strtolower(trim((string) $correct));
                }

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

            // إكمال الدرس ذرياً داخل المعاملة (النموذج التكويني: حل جميع الأسئلة يكمل الدرس)
            UserLessonProgress::updateOrCreate(
                ['user_id' => $user->id, 'lesson_id' => $task->lesson_id],
                ['completed' => true]
            );

            $newAttemptsUsed = $attemptsUsed + 1;
            $attemptsRemaining = max(0, $maxAttempts - $newAttemptsUsed);
            $revealAnswers = $passed || ($newAttemptsUsed >= $maxAttempts);

            if (! $revealAnswers) {
                foreach ($results as $qId => $res) {
                    if (isset($res['type'])) {
                        continue;
                    }
                    unset($results[$qId]['correct_answer'], $results[$qId]['explanation']);
                }
            }

            return [
                'status' => 200,
                'data' => [
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
                ],
            ];
        });
    }
}
