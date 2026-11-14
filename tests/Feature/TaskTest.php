<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Task;
use App\Models\TaskAttempt;
use App\Models\User;
use App\Models\UserLessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Lesson $lesson;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();

        $course = Course::create([
            'title' => 'Laravel Mastery',
            'description' => 'Full backend course',
            'status' => 'published',
            'is_public' => true,
        ]);

        $section = Section::create([
            'course_id' => $course->id,
            'title' => 'Section 1',
            'order' => 1,
        ]);

        $this->lesson = Lesson::create([
            'section_id' => $section->id,
            'title' => 'Lesson 1',
            'duration' => 600,
            'order' => 1,
        ]);

        $this->task = Task::create([
            'lesson_id' => $this->lesson->id,
            'questions' => [
                [
                    'id' => 1,
                    'type' => 'mcq',
                    'text' => 'What is Laravel?',
                    'options' => ['A PHP Framework', 'A Database'],
                    'correct_answer' => 'A PHP Framework',
                    'explanation' => 'Laravel is a web application framework with expressive syntax.',
                ],
                [
                    'id' => 2,
                    'type' => 'true_false',
                    'text' => 'PHP is a compiled language.',
                    'options' => ['true', 'false'],
                    'correct_answer' => 'false',
                    'explanation' => 'PHP is an interpreted/scripting language.',
                ],
                [
                    'id' => 3,
                    'type' => 'open',
                    'text' => 'Describe MVC architecture.',
                ],
            ],
            'pass_percentage' => 70,
            'max_attempts' => 3,
        ]);
    }

    public function test_guest_cannot_view_or_submit_task(): void
    {
        $this->getJson("/api/lessons/{$this->lesson->id}/task")->assertUnauthorized();
        $this->postJson("/api/lessons/{$this->lesson->id}/task", ['answers' => []])->assertUnauthorized();
    }

    public function test_get_task_returns_404_if_lesson_has_no_task(): void
    {
        Sanctum::actingAs($this->student);

        $section = Section::first();
        $lessonWithoutTask = Lesson::create([
            'section_id' => $section->id,
            'title' => 'Lesson Without Task',
            'duration' => 300,
            'order' => 2,
        ]);

        $this->getJson("/api/lessons/{$lessonWithoutTask->id}/task")
            ->assertNotFound()
            ->assertJson(['message' => 'لا توجد مهمة لهذا الدرس']);
    }

    public function test_get_task_requires_80_percent_video_completion(): void
    {
        Sanctum::actingAs($this->student);

        // No progress at all
        $this->getJson("/api/lessons/{$this->lesson->id}/task")
            ->assertStatus(403)
            ->assertJson([
                'message' => 'يجب مشاهدة ≥ 80% من الفيديو أولاً',
                'watched_percent' => 0,
            ]);

        // Incomplete progress (< 80%)
        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson->id,
            'watched_percent' => 50,
            'completed' => false,
        ]);

        $this->getJson("/api/lessons/{$this->lesson->id}/task")
            ->assertStatus(403)
            ->assertJson([
                'message' => 'يجب مشاهدة ≥ 80% من الفيديو أولاً',
                'watched_percent' => 50,
            ]);
    }

    public function test_student_can_view_task_after_completing_video_without_correct_answers(): void
    {
        Sanctum::actingAs($this->student);

        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson->id,
            'watched_percent' => 100,
            'completed' => true,
        ]);

        $response = $this->getJson("/api/lessons/{$this->lesson->id}/task");

        $response->assertOk()
            ->assertJsonStructure([
                'task_id',
                'pass_percentage',
                'max_attempts',
                'attempts_used',
                'attempts_remaining',
                'already_passed',
                'questions' => [
                    [
                        'id',
                        'type',
                        'text',
                        'options',
                    ],
                ],
            ])
            ->assertJsonPath('task_id', $this->task->id)
            ->assertJsonPath('attempts_used', 0)
            ->assertJsonPath('attempts_remaining', 3)
            ->assertJsonPath('already_passed', false);

        // Ensure correct_answer is never leaked in the response
        $content = $response->json();
        foreach ($content['questions'] as $q) {
            $this->assertArrayNotHasKey('correct_answer', $q);
        }
    }

    public function test_submit_task_validates_answers_field(): void
    {
        Sanctum::actingAs($this->student);

        $this->postJson("/api/lessons/{$this->lesson->id}/task", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('answers');

        $this->postJson("/api/lessons/{$this->lesson->id}/task", ['answers' => 'not-an-array'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('answers');
    }

    public function test_student_submits_task_and_passes(): void
    {
        Sanctum::actingAs($this->student);

        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson->id,
            'watched_percent' => 100,
            'completed' => true,
        ]);

        $payload = [
            'answers' => [
                1 => 'A PHP Framework',
                2 => 'false',
                3 => 'Model View Controller separates data, presentation, and logic.',
            ],
        ];

        $response = $this->postJson("/api/lessons/{$this->lesson->id}/task", $payload);

        $response->assertOk()
            ->assertJsonPath('score', 100)
            ->assertJsonPath('passed', true)
            ->assertJsonPath('pass_score', 70)
            ->assertJsonPath('results.1.correct', true)
            ->assertJsonPath('results.2.correct', true)
            ->assertJsonPath('results.3.pending', true);

        $this->assertDatabaseHas('task_attempts', [
            'user_id' => $this->student->id,
            'task_id' => $this->task->id,
            'score' => 100,
            'passed' => true,
        ]);
    }

    public function test_student_submits_task_and_fails(): void
    {
        Sanctum::actingAs($this->student);

        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson->id,
            'watched_percent' => 100,
            'completed' => true,
        ]);

        $payload = [
            'answers' => [
                1 => 'A Database',
                2 => 'true',
            ],
        ];

        $response = $this->postJson("/api/lessons/{$this->lesson->id}/task", $payload);

        $response->assertOk()
            ->assertJsonPath('score', 0)
            ->assertJsonPath('passed', false)
            ->assertJsonPath('results.1.correct', false)
            ->assertJsonPath('results.2.correct', false)
            ->assertJsonMissingPath('results.1.correct_answer');

        $this->assertDatabaseHas('task_attempts', [
            'user_id' => $this->student->id,
            'task_id' => $this->task->id,
            'score' => 0,
            'passed' => false,
        ]);
    }

    public function test_student_cannot_retake_task_if_already_passed(): void
    {
        Sanctum::actingAs($this->student);

        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson->id,
            'watched_percent' => 100,
            'completed' => true,
        ]);

        TaskAttempt::create([
            'user_id' => $this->student->id,
            'task_id' => $this->task->id,
            'answers' => [1 => 'A PHP Framework'],
            'score' => 100,
            'passed' => true,
        ]);

        $response = $this->postJson("/api/lessons/{$this->lesson->id}/task", [
            'answers' => [1 => 'A PHP Framework'],
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'نجحت في هذه المهمة مسبقاً',
                'passed' => true,
            ]);
    }

    public function test_student_blocked_when_max_attempts_exceeded(): void
    {
        Sanctum::actingAs($this->student);

        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson->id,
            'watched_percent' => 100,
            'completed' => true,
        ]);

        // Exhaust all 3 allowed attempts
        for ($i = 0; $i < 3; $i++) {
            TaskAttempt::create([
                'user_id' => $this->student->id,
                'task_id' => $this->task->id,
                'answers' => [1 => 'Wrong'],
                'score' => 0,
                'passed' => false,
            ]);
        }

        $response = $this->postJson("/api/lessons/{$this->lesson->id}/task", [
            'answers' => [1 => 'A PHP Framework'],
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'message' => 'استنفذت عدد المحاولات المسموح بها',
            ]);
    }

    public function test_submit_task_returns_404_if_lesson_has_no_task(): void
    {
        Sanctum::actingAs($this->student);

        $section = Section::first();
        $lessonWithoutTask = Lesson::create([
            'section_id' => $section->id,
            'title' => 'Lesson Without Task For Submit',
            'duration' => 300,
            'order' => 3,
        ]);

        $this->postJson("/api/lessons/{$lessonWithoutTask->id}/task", ['answers' => [1 => 'answer']])
            ->assertNotFound()
            ->assertJson(['message' => 'لا توجد مهمة']);
    }

    public function test_route_model_binding_returns_404_for_non_existent_lesson(): void
    {
        Sanctum::actingAs($this->student);

        $this->getJson('/api/lessons/999999/task')->assertNotFound();
        $this->postJson('/api/lessons/999999/task', ['answers' => []])->assertNotFound();
    }

    public function test_task_show_and_submit_handle_questions_with_unconventional_keys(): void
    {
        Sanctum::actingAs($this->student);

        $customCourse = Course::create([
            'title' => 'Custom Course',
            'description' => 'Test',
            'status' => 'published',
            'is_public' => true,
        ]);
        $customSection = Section::create([
            'course_id' => $customCourse->id,
            'title' => 'Custom Section',
            'order' => 1,
        ]);
        $customLesson = Lesson::create([
            'section_id' => $customSection->id,
            'title' => 'Custom Lesson',
            'duration' => 300,
            'order' => 1,
        ]);

        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $customLesson->id,
            'watched_percent' => 100,
            'completed' => true,
        ]);

        Task::create([
            'lesson_id' => $customLesson->id,
            'questions' => [
                [
                    'question' => 'What is OOP?',
                    'correct_answer' => 'Object Oriented Programming',
                ],
            ],
            'pass_percentage' => 50,
            'max_attempts' => 2,
        ]);

        $getResponse = $this->getJson("/api/lessons/{$customLesson->id}/task");
        $getResponse->assertOk()
            ->assertJsonPath('questions.0.id', 1)
            ->assertJsonPath('questions.0.text', 'What is OOP?');

        $postResponse = $this->postJson("/api/lessons/{$customLesson->id}/task", [
            'answers' => [1 => 'Object Oriented Programming'],
        ]);
        $postResponse->assertOk()
            ->assertJsonPath('score', 100)
            ->assertJsonPath('passed', true);
    }

    public function test_student_blocked_by_cooldown_after_failed_attempt(): void
    {
        Sanctum::actingAs($this->student);

        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson->id,
            'watched_percent' => 100,
            'completed' => true,
        ]);

        // First failed attempt
        $res1 = $this->postJson("/api/lessons/{$this->lesson->id}/task", [
            'answers' => [1 => 'Wrong Answer'],
        ]);
        $res1->assertOk()
            ->assertJsonPath('passed', false);

        // Immediate second attempt should hit cooldown
        $res2 = $this->postJson("/api/lessons/{$this->lesson->id}/task", [
            'answers' => [1 => 'A PHP Framework'],
        ]);
        $res2->assertStatus(429)
            ->assertJsonStructure(['message', 'cooldown_remaining_minutes']);
    }
}
