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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyCoursesAndInProgressEdgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_edge_free_course_with_progress_appears_in_my_courses_and_supports_free_paid_filter(): void
    {
        $student = User::factory()->student()->create();

        $freeCourse = Course::create([
            'title' => 'Free Next.js Course',
            'description' => 'Free course',
            'status' => 'published',
            'is_public' => true,
            'price' => 0,
        ]);
        $freeSection = Section::create(['course_id' => $freeCourse->id, 'title' => 'S1', 'order' => 1]);
        $freeLesson = Lesson::create([
            'section_id' => $freeSection->id,
            'title' => 'L1',
            'order' => 1,
            'is_published' => true,
            'duration' => 100,
        ]);

        // Student started watching free course without an explicit Enrollment row
        UserLessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $freeLesson->id,
            'watched_percent' => 25,
            'last_position' => 25,
            'completed' => false,
        ]);

        $paidCourse = Course::create([
            'title' => 'Paid Laravel Course',
            'description' => 'Paid course',
            'status' => 'published',
            'is_public' => false,
            'price' => 99,
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $paidCourse->id,
            'status' => 'approved',
            'enrolled_at' => now(),
        ]);

        Sanctum::actingAs($student);

        // Both courses appear in /api/my-courses
        $this->getJson('/api/my-courses')
            ->assertOk()
            ->assertJsonCount(2);

        // Filter: type=free
        $this->getJson('/api/my-courses?type=free')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.course.id', $freeCourse->id);

        // Filter: type=paid
        $this->getJson('/api/my-courses?type=paid')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.course.id', $paidCourse->id);

        // Continue learning includes the unfinished free lesson
        $this->getJson('/api/lessons/in-progress')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $freeLesson->id);
    }
}
