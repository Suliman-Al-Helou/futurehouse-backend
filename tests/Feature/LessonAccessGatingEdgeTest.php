<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonAccessGatingEdgeTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Section $section;

    private Lesson $lesson1;

    private Lesson $lesson2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();

        $this->course = Course::create([
            'title' => 'SUL-10 Gating Course',
            'description' => 'Testing edge cases for lesson gating',
            'status' => 'published',
            'is_public' => false,
            'is_free' => false,
        ]);

        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
            'enrolled_at' => now(),
        ]);

        $this->section = Section::create([
            'course_id' => $this->course->id,
            'title' => 'Section 1',
            'order' => 1,
        ]);

        $this->lesson1 = Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Lesson 1',
            'order' => 1,
            'is_published' => true,
            'is_preview' => false,
            'duration' => 100,
        ]);

        $this->lesson2 = Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Lesson 2',
            'order' => 2,
            'is_published' => true,
            'is_preview' => false,
            'duration' => 100,
        ]);
    }

    public function test_edge_79_percent_watch_on_no_exam_lesson_blocks_next_lesson_with_403(): void
    {
        Sanctum::actingAs($this->student);

        $this->postJson("/api/lessons/{$this->lesson1->id}/progress", [
            'watched_percent' => 79.9,
            'last_position' => 79,
        ])->assertOk()->assertJson(['completed' => false]);

        $this->getJson("/api/lessons/{$this->lesson2->id}")->assertForbidden();
    }

    public function test_edge_80_percent_watch_on_no_exam_lesson_unlocks_next_lesson(): void
    {
        Sanctum::actingAs($this->student);

        $this->postJson("/api/lessons/{$this->lesson1->id}/progress", [
            'watched_percent' => 80.0,
            'last_position' => 80,
        ])->assertOk()->assertJson(['completed' => true]);

        $this->getJson("/api/lessons/{$this->lesson2->id}")->assertOk();
    }

    public function test_edge_100_percent_watch_with_unsolved_exam_blocks_next_lesson_with_403(): void
    {
        Task::create([
            'lesson_id' => $this->lesson1->id,
            'title' => 'Lesson 1 Exam',
            'pass_percentage' => 70,
            'questions' => [
                ['question' => '1+1?', 'options' => ['1', '2'], 'correct_index' => 1],
            ],
        ]);

        Sanctum::actingAs($this->student);

        $this->postJson("/api/lessons/{$this->lesson1->id}/progress", [
            'watched_percent' => 100,
            'last_position' => 100,
        ])->assertOk()->assertJson([
            'completed' => false,
            'task_unlocked' => true,
        ]);

        $this->getJson("/api/lessons/{$this->lesson2->id}")->assertForbidden();
    }

    public function test_edge_student_progress_is_isolated_per_student(): void
    {
        $otherStudent = User::factory()->student()->create();
        Enrollment::create([
            'user_id' => $otherStudent->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
            'enrolled_at' => now(),
        ]);

        Sanctum::actingAs($this->student);
        $this->postJson("/api/lessons/{$this->lesson1->id}/progress", [
            'watched_percent' => 90,
            'last_position' => 90,
        ])->assertOk();

        Sanctum::actingAs($otherStudent);
        $this->getJson("/api/lessons/{$this->lesson2->id}")->assertForbidden();
    }
}
