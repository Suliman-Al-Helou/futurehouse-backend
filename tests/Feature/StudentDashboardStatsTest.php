<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use App\Models\UserLessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StudentDashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_only_approved_enrollments_and_fully_completed_courses(): void
    {
        $user = User::factory()->create();

        [$done, $doneLessons] = $this->courseWithLessons(2);
        [$partial, $partialLessons] = $this->courseWithLessons(2);
        [$pending] = $this->courseWithLessons(1);

        Enrollment::create(['user_id' => $user->id, 'course_id' => $done->id, 'status' => 'approved']);
        Enrollment::create(['user_id' => $user->id, 'course_id' => $partial->id, 'status' => 'approved']);
        Enrollment::create(['user_id' => $user->id, 'course_id' => $pending->id, 'status' => 'pending']);

        foreach ([...$doneLessons, $partialLessons[0]] as $lesson) {
            UserLessonProgress::create([
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'watched_percent' => 100,
                'last_position' => 0,
                'completed' => true,
            ]);
        }

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonMissingPath('data') // not wrapped
            ->assertJsonPath('stats.enrolled_courses', 2)
            ->assertJsonPath('stats.completed_courses', 1)
            ->assertJsonPath('stats.completed_lessons', 3);
    }

    /** @return array{0: Course, 1: list<Lesson>} */
    private function courseWithLessons(int $count): array
    {
        $course = Course::create(['title' => 't', 'description' => 'd', 'level' => 'beginner', 'status' => 'published']);
        $section = Section::create(['course_id' => $course->id, 'title' => 's', 'order' => 1]);

        $lessons = [];
        for ($i = 1; $i <= $count; $i++) {
            $lessons[] = Lesson::create(['section_id' => $section->id, 'title' => "l{$i}", 'order' => $i, 'duration' => 600]);
        }

        return [$course, $lessons];
    }
}
