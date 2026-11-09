<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StudentDashboardResource extends JsonResource
{
    // Frontend reads `stats` at the top level: do NOT wrap in {data: ...}.
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array{stats: array<string, int>, exam_results: list<array<string, mixed>>, pass_rate: int} $data */
        $data = $this->resource;

        return [
            'stats' => [
                'enrolled_courses' => (int) $data['stats']['enrolled_courses'],
                'completed_lessons' => (int) $data['stats']['completed_lessons'],
                'learning_hours' => (int) $data['stats']['learning_hours'],
                'completed_courses' => (int) $data['stats']['completed_courses'],
                'streak' => (int) $data['stats']['streak'],
            ],
            'exam_results' => array_map(static fn (array $row): array => [
                'lesson' => (string) $row['lesson'],
                'course' => (string) $row['course'],
                'score' => (int) $row['score'],
                'passed' => (bool) $row['passed'],
                'date' => (string) $row['date'],
            ], $data['exam_results']),
            'pass_rate' => (int) $data['pass_rate'],
        ];
    }
}
