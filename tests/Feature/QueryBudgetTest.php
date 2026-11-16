<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Task;
use App\Models\TaskAttempt;
use App\Models\User;
use App\Models\UserLessonProgress;
use App\Support\ContentCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_prevent_lazy_loading_is_enforced_in_testing(): void
    {
        $this->assertTrue(Model::preventsLazyLoading(), 'Model::preventLazyLoading must be enabled in testing/local');

        $instructor = Instructor::create(['name' => 'Prof. Testing']);
        Course::factory()->count(2)->create(['instructor_id' => $instructor->id]);

        $courses = Course::query()->limit(2)->get();
        $this->assertCount(2, $courses);

        $this->expectException(LazyLoadingViolationException::class);
        foreach ($courses as $course) {
            // Accessing unloaded relation without eager loading on collection must throw
            $course->instructor;
        }
    }

    public function test_student_dashboard_stats_query_budget_is_bounded(): void
    {
        $student = User::factory()->student()->create();

        // Seed realistic workload: 3 courses, sections, lessons, attempts
        for ($c = 1; $c <= 3; $c++) {
            $course = Course::factory()->create(['status' => 'published']);
            $section = Section::factory()->create(['course_id' => $course->id]);
            $lesson = Lesson::factory()->create(['section_id' => $section->id]);
            $task = Task::create([
                'lesson_id' => $lesson->id,
                'questions' => [['question' => 'q', 'options' => ['a', 'b'], 'correct_answer' => 'a']],
                'pass_percentage' => 70,
                'max_attempts' => 3,
                'cooldown_minutes' => 60,
            ]);

            Enrollment::create([
                'user_id' => $student->id,
                'course_id' => $course->id,
                'status' => 'approved',
            ]);

            UserLessonProgress::create([
                'user_id' => $student->id,
                'lesson_id' => $lesson->id,
                'watched_percent' => 100,
                'last_position' => 100,
                'completed' => true,
            ]);

            TaskAttempt::create([
                'user_id' => $student->id,
                'task_id' => $task->id,
                'score' => 90,
                'passed' => true,
                'answers' => ['q1' => 'ans'],
            ]);
        }

        Sanctum::actingAs($student);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/dashboard/stats');
        $response->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Budget ceiling: authentication (1) + stats counts (4) + streak (1) + exam results with eager loading (5) = 11 queries
        $this->assertLessThanOrEqual(
            12,
            $queryCount,
            "Student dashboard executed {$queryCount} queries, exceeding budget of 12"
        );
    }

    public function test_course_catalog_query_budget_on_cache_miss_and_hit(): void
    {
        ContentCache::forgetCourses();

        $instructor = Instructor::create(['name' => 'Prof. Query Test']);
        Course::factory()->count(6)->create([
            'status' => 'published',
            'instructor_id' => $instructor->id,
        ]);

        // 1. Cache Miss: must not exceed 3 queries (course select + instructor eager load + counts)
        DB::flushQueryLog();
        DB::enableQueryLog();

        $responseMiss = $this->getJson('/api/courses');
        $responseMiss->assertOk();

        $missQueries = DB::getQueryLog();
        $missCount = count($missQueries);

        $this->assertLessThanOrEqual(
            3,
            $missCount,
            "Course catalog on cache miss executed {$missCount} queries, exceeding budget of 3"
        );

        // 2. Cache Hit: must execute ZERO database queries
        DB::flushQueryLog();
        DB::enableQueryLog();

        $responseHit = $this->getJson('/api/courses');
        $responseHit->assertOk();

        $hitQueries = DB::getQueryLog();
        $hitCount = count($hitQueries);

        $this->assertSame(
            0,
            $hitCount,
            "Course catalog on cache hit executed {$hitCount} queries, expected exactly 0"
        );
    }

    public function test_lesson_view_query_budget_is_bounded(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->create(['status' => 'published', 'price' => 100]);
        $section = Section::factory()->create(['course_id' => $course->id]);

        $lesson1 = Lesson::factory()->create(['section_id' => $section->id, 'order' => 1]);
        $lesson2 = Lesson::factory()->create(['section_id' => $section->id, 'order' => 2]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'approved',
        ]);

        UserLessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson1->id,
            'watched_percent' => 100,
            'last_position' => 100,
            'completed' => true,
        ]);

        Sanctum::actingAs($student);

        // Warm up permissions/role cache to isolate endpoint queries
        $student->hasRole('student');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson("/api/lessons/{$lesson2->id}");
        $response->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Budget ceiling: lesson binding (1) + section/course (1-2) + enrollment check (1) + previous lesson check (2) <= 8 queries
        $this->assertLessThanOrEqual(
            8,
            $queryCount,
            "Lesson view executed {$queryCount} queries, exceeding budget of 8"
        );
    }
}
