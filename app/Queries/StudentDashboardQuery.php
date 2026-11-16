<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Enrollment;
use App\Models\TaskAttempt;
use App\Models\User;
use App\Models\UserLessonProgress;
use Illuminate\Support\Facades\DB;

final class StudentDashboardQuery
{
    // Legacy estimate kept as-is (see debt in ai-context.md).
    private const MINUTES_PER_COMPLETED_LESSON = 30;

    /**
     * @return array{
     *     stats: array<string, int>,
     *     exam_results: list<array{lesson: string, course: string, score: int, passed: bool, date: string}>,
     *     pass_rate: int
     * }
     */
    public function forUser(User $user): array
    {
        $completedLessons = UserLessonProgress::query()
            ->where('user_id', $user->id)
            ->where('completed', true)
            ->count();

        [$examResults, $passRate] = $this->examResults($user->id);

        return [
            'stats' => [
                'enrolled_courses' => $this->approvedEnrollmentsCount($user->id),
                'completed_lessons' => $completedLessons,
                'learning_hours' => (int) round(($completedLessons * self::MINUTES_PER_COMPLETED_LESSON) / 60),
                'completed_courses' => $this->completedCoursesCount($user->id),
                'streak' => $this->streak($user->id),
            ],
            'exam_results' => $examResults,
            'pass_rate' => $passRate,
        ];
    }

    private function approvedEnrollmentsCount(int $userId): int
    {
        return Enrollment::query()
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->count();
    }

    // A course is completed when it has lessons and ALL of them are completed by the user.
    private function completedCoursesCount(int $userId): int
    {
        return DB::table('enrollments as e')
            ->join('sections as s', 's.course_id', '=', 'e.course_id')
            ->join('lessons as l', 'l.section_id', '=', 's.id')
            ->leftJoin('user_lesson_progress as p', function ($join) use ($userId): void {
                $join->on('p.lesson_id', '=', 'l.id')
                    ->where('p.user_id', '=', $userId)
                    ->where('p.completed', '=', true);
            })
            ->where('e.user_id', $userId)
            ->where('e.status', 'approved')
            ->whereNull('e.deleted_at')
            ->groupBy('e.course_id')
            ->havingRaw('COUNT(l.id) > 0 AND COUNT(l.id) = COUNT(p.id)')
            ->select('e.course_id')
            ->get()
            ->count();
    }

    /** @return array{0: list<array{lesson: string, course: string, score: int, passed: bool, date: string}>, 1: int} */
    private function examResults(int $userId): array
    {
        $attempts = TaskAttempt::query()
            ->where('user_id', $userId)
            ->with('task.lesson.section.course')
            ->latest()
            ->get();

        $rows = $attempts->map(static fn (TaskAttempt $a): array => [
            'lesson' => $a->task?->lesson?->title ?? '',
            'course' => $a->task?->lesson?->section?->course?->title ?? '',
            'score' => (int) $a->score,
            'passed' => (bool) $a->passed,
            'date' => $a->created_at?->format('Y/n/j') ?? '',
        ])->values()->all();

        $passRate = $attempts->isNotEmpty()
            ? (int) round(($attempts->where('passed', true)->count() / $attempts->count()) * 100)
            : 0;

        return [$rows, $passRate];
    }

    // Moved verbatim from the controller. Still based on updated_at (unreliable).
    private function streak(int $userId): int
    {
        $dates = UserLessonProgress::query()
            ->where('user_id', $userId)
            ->where('completed', true)
            ->orderByDesc('updated_at')
            ->pluck('updated_at')
            ->map(static fn ($date): string => $date->format('Y-m-d'))
            ->unique()
            ->values();

        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        if (! $dates->contains($today) && ! $dates->contains($yesterday)) {
            return 0;
        }

        $cursor = $dates->contains($today) ? now() : now()->subDay();
        $streak = 0;

        foreach ($dates as $date) {
            if ($date !== $cursor->format('Y-m-d')) {
                break;
            }
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }
}
