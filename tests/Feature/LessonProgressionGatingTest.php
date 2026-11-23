<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use App\Models\UserLessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonProgressionGatingTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Section $section;

    private Lesson $lesson1;

    private Lesson $lesson2;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();

        $this->course = Course::create([
            'title' => 'Web Security Mastery',
            'description' => 'Comprehensive security and architecture course',
            'status' => 'published',
            'is_public' => false,
        ]);

        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'approved',
            'enrolled_at' => now(),
        ]);

        $this->section = Section::create([
            'course_id' => $this->course->id,
            'title' => 'Security Fundamentals',
            'order' => 1,
        ]);

        $this->lesson1 = Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'SQL Injection & Prevention',
            'duration' => 600,
            'order' => 1,
            'is_preview' => false,
        ]);

        $this->lesson2 = Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Cross-Site Scripting (XSS)',
            'duration' => 900,
            'order' => 2,
            'is_preview' => false,
        ]);

        $this->task = Task::create([
            'lesson_id' => $this->lesson1->id,
            'questions' => [
                [
                    'id' => 1,
                    'type' => 'mcq',
                    'question' => 'Which technique prevents SQL injection?',
                    'options' => ['Prepared Statements', 'Blacklist Filtering', 'Regex Validation', 'Double Quotes'],
                    'correct_answer' => 0,
                    'explanation' => 'Parameterized queries guarantee separation of code and data.',
                ],
                [
                    'id' => 2,
                    'type' => 'true_false',
                    'question' => 'Stored XSS occurs when malicious input is persisted in the database.',
                    'options' => ['صح', 'خطأ'],
                    'correct_answer' => 0,
                    'explanation' => 'Stored XSS is executed whenever victim users view the stored record.',
                ],
                [
                    'id' => 3,
                    'type' => 'mcq',
                    'question' => 'What is the primary defense against CSRF attacks?',
                    'options' => ['Strict-Transport-Security', 'Anti-CSRF Tokens', 'X-Frame-Options', 'CORS Headers'],
                    'correct_answer' => 1,
                    'explanation' => 'Synchronizer token pattern or SameSite cookies protect against CSRF.',
                ],
                [
                    'id' => 4,
                    'type' => 'mcq',
                    'question' => 'Which HTTP header prevents MIME-type sniffing?',
                    'options' => ['X-XSS-Protection', 'X-Content-Type-Options: nosniff', 'Content-Security-Policy', 'Referrer-Policy'],
                    'correct_answer' => 1,
                    'explanation' => 'X-Content-Type-Options: nosniff prevents browser sniffing.',
                ],
            ],
            'pass_percentage' => 70,
            'max_attempts' => 3,
        ]);
    }

    /**
     * Test 1: Video alone does not unlock the next lesson when a task exists.
     */
    public function test_video_alone_does_not_unlock_next_lesson_when_task_exists(): void
    {
        Sanctum::actingAs($this->student);

        // Student watches 90% of lesson 1
        $response = $this->postJson("/api/lessons/{$this->lesson1->id}/progress", [
            'watched_percent' => 90,
            'last_position' => 540,
        ]);

        $response->assertOk()
            ->assertJsonPath('watched_percent', 90)
            ->assertJsonPath('completed', false)
            ->assertJsonPath('task_unlocked', true);

        $this->assertDatabaseHas('user_lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson1->id,
            'watched_percent' => 90,
            'completed' => false,
        ]);

        // Attempting to access lesson 2 must be forbidden (locked because lesson 1 is not completed)
        $this->getJson("/api/lessons/{$this->lesson2->id}")
            ->assertForbidden();
    }

    /**
     * Test 2: Incomplete task submissions are rejected with 422 Unprocessable Content.
     */
    public function test_incomplete_task_submission_is_rejected_with_422(): void
    {
        Sanctum::actingAs($this->student);

        // Seed 100% video progress (completed remains false)
        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson1->id,
            'watched_percent' => 100,
            'completed' => false,
        ]);

        // Missing questions 3 and 4
        $payload = [
            'answers' => [
                1 => 0,
                2 => 0,
            ],
        ];

        $response = $this->postJson("/api/lessons/{$this->lesson1->id}/task", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('answers');

        $this->assertSame(
            'يجب الإجابة على جميع الأسئلة لتسليم المهمة',
            $response->json('message')
        );

        $this->assertDatabaseCount('task_attempts', 0);
        $this->assertDatabaseHas('user_lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson1->id,
            'completed' => false,
        ]);
    }

    /**
     * Test 3: Zero-index first option is accepted as a valid answer (not treated as missing/empty).
     */
    public function test_zero_index_first_option_is_accepted_as_valid_answer(): void
    {
        Sanctum::actingAs($this->student);

        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson1->id,
            'watched_percent' => 100,
            'completed' => false,
        ]);

        // All questions answered with index 0
        $payload = [
            'answers' => [
                1 => 0,
                2 => 0,
                3 => 0,
                4 => 0,
            ],
        ];

        $response = $this->postJson("/api/lessons/{$this->lesson1->id}/task", $payload);

        // Must succeed without 422 validation failure
        $response->assertOk();
    }

    /**
     * Test 4: Submitting all questions atomically unlocks next lesson regardless of score.
     */
    public function test_submitting_all_questions_atomically_unlocks_next_lesson_regardless_of_score(): void
    {
        Sanctum::actingAs($this->student);

        // Student watched 85% of video
        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson1->id,
            'watched_percent' => 85,
            'completed' => false,
        ]);

        // Answers that score only 25% (Q1 correct = 0, Q2 incorrect = 1, Q3 incorrect = 0, Q4 incorrect = 0)
        $payload = [
            'answers' => [
                1 => 0, // correct
                2 => 1, // incorrect (correct is 0)
                3 => 0, // incorrect (correct is 1)
                4 => 0, // incorrect (correct is 1)
            ],
        ];

        $response = $this->postJson("/api/lessons/{$this->lesson1->id}/task", $payload);

        $response->assertOk()
            ->assertJsonPath('score', 25)
            ->assertJsonPath('passed', false);

        // Must be saved in task_attempts
        $this->assertDatabaseHas('task_attempts', [
            'user_id' => $this->student->id,
            'task_id' => $this->task->id,
            'score' => 25,
            'passed' => false,
        ]);

        // Progress on lesson 1 must now be completed
        $this->assertDatabaseHas('user_lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson1->id,
            'completed' => true,
        ]);

        // Next lesson (lesson 2) must now be accessible!
        $this->getJson("/api/lessons/{$this->lesson2->id}")
            ->assertOk();
    }

    /**
     * Test 5: Database failure during progress update rolls back task attempt.
     */
    public function test_database_failure_during_progress_update_rolls_back_task_attempt(): void
    {
        Sanctum::actingAs($this->student);

        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson1->id,
            'watched_percent' => 95,
            'completed' => false,
        ]);

        // Simulate database exception when updating progress
        UserLessonProgress::saving(function () {
            throw new \RuntimeException('Simulated Database Deadlock or Disconnect during progress update');
        });

        $payload = [
            'answers' => [
                1 => 0,
                2 => 0,
                3 => 1,
                4 => 1,
            ],
        ];

        try {
            $this->postJson("/api/lessons/{$this->lesson1->id}/task", $payload);
        } catch (\Throwable $e) {
            // Caught as expected
        }

        // Both mutations must have rolled back
        $this->assertDatabaseCount('task_attempts', 0);
        $this->assertDatabaseHas('user_lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson1->id,
            'completed' => false,
        ]);
    }

    /**
     * Test 6: Taskless lesson progression unlocks next lesson at 80% watch immediately.
     */
    public function test_taskless_lesson_unlocks_next_lesson_at_80_percent_watch(): void
    {
        Sanctum::actingAs($this->student);

        // Lesson 2 has no task
        $this->assertFalse($this->lesson2->task()->exists());

        // Create a 3rd lesson to verify unlock
        $lesson3 = Lesson::create([
            'section_id' => $this->section->id,
            'title' => 'Authentication Vulnerabilities',
            'duration' => 600,
            'order' => 3,
            'is_preview' => false,
        ]);

        // Complete lesson 1 first
        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson1->id,
            'watched_percent' => 100,
            'completed' => true,
        ]);

        // Watch 80% on lesson 2 (taskless)
        $response = $this->postJson("/api/lessons/{$this->lesson2->id}/progress", [
            'watched_percent' => 80,
            'last_position' => 720,
        ]);

        $response->assertOk()
            ->assertJsonPath('completed', true);

        $this->assertDatabaseHas('user_lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson2->id,
            'completed' => true,
        ]);

        // Lesson 3 is now unlocked
        $this->getJson("/api/lessons/{$lesson3->id}")
            ->assertOk();
    }

    /**
     * Test 7: Re-watching a completed lesson preserves its completed status.
     */
    public function test_rewatching_completed_lesson_preserves_completed_status(): void
    {
        Sanctum::actingAs($this->student);

        // Lesson 1 is already completed
        UserLessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson1->id,
            'watched_percent' => 100,
            'last_position' => 600,
            'completed' => true,
        ]);

        // Student scrubs backward or restarts the video at 15%
        $response = $this->postJson("/api/lessons/{$this->lesson1->id}/progress", [
            'watched_percent' => 15,
            'last_position' => 90,
        ]);

        $response->assertOk()
            ->assertJsonPath('completed', true);

        $this->assertDatabaseHas('user_lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson1->id,
            'completed' => true,
        ]);
    }
}
