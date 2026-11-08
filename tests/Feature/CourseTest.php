<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_public_courses_index_returns_published_courses_as_resource_collection(): void
    {
        $published = Course::create([
            'title' => 'Laravel Masterclass',
            'description' => 'Learn Laravel from scratch',
            'level' => 'intermediate',
            'status' => 'published',
            'what_you_learn' => ['Models', 'Controllers'],
            'requirements' => ['PHP basics'],
            'target_audience' => ['Web developers'],
            'rating' => 4.8,
            'price' => 99.0,
            'is_public' => true,
        ]);

        $student = User::factory()->student()->create();
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $published->id,
            'status' => 'approved',
        ]);

        $section = Section::create(['course_id' => $published->id, 'title' => 'Intro', 'order' => 1]);
        Lesson::create([
            'section_id' => $section->id,
            'title' => 'Lesson 1',
            'duration' => 10,
            'order' => 1,
            'is_preview' => true,
        ]);

        // Draft course should NOT appear
        Course::create([
            'title' => 'Unpublished Draft',
            'description' => 'Draft content',
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/courses');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'title',
                        'description',
                        'level',
                        'status',
                        'students_count',
                        'lessons',
                        'what_you_learn',
                        'requirements',
                        'target_audience',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.students_count', 1)
            ->assertJsonPath('data.0.lessons', 1);
    }

    public function test_public_courses_index_filters_by_level(): void
    {
        Course::create(['title' => 'Beg', 'description' => 'D', 'level' => 'beginner', 'status' => 'published']);
        Course::create(['title' => 'Adv', 'description' => 'D', 'level' => 'advanced', 'status' => 'published']);

        $response = $this->getJson('/api/courses?level=beginner');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Beg');
    }

    public function test_public_course_show_returns_course_detail_resource_with_sections_and_lessons(): void
    {
        $course = Course::create([
            'title' => 'Detail Course',
            'description' => 'Desc',
            'status' => 'published',
            'what_you_learn' => ['Skill 1'],
            'requirements' => ['Req 1'],
            'target_audience' => ['Aud 1'],
        ]);

        $section = Section::create(['course_id' => $course->id, 'title' => 'Section 1', 'order' => 1]);
        $lesson = Lesson::create([
            'section_id' => $section->id,
            'title' => 'Lesson 1',
            'duration' => 15,
            'order' => 1,
            'is_preview' => true,
        ]);

        $response = $this->getJson("/api/courses/{$course->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'status',
                    'what_you_learn',
                    'requirements',
                    'target_audience',
                    'sections' => [
                        [
                            'id',
                            'title',
                            'order',
                            'lessons' => [
                                [
                                    'id',
                                    'title',
                                    'duration',
                                    'order',
                                    'is_preview',
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.id', $course->id)
            ->assertJsonPath('data.sections.0.id', $section->id)
            ->assertJsonPath('data.sections.0.lessons.0.id', $lesson->id);
    }

    public function test_public_course_show_aborts_404_for_draft_course(): void
    {
        $draft = Course::create(['title' => 'Draft Course', 'description' => 'Desc', 'status' => 'draft']);

        $response = $this->getJson("/api/courses/{$draft->id}");

        $response->assertNotFound();
    }

    public function test_public_courses_index_returns_instructor_name_when_assigned(): void
    {
        $instructor = Instructor::create(['name' => 'Prof. Alex Smith']);

        $course = Course::create([
            'title' => 'Alex Course',
            'description' => 'Desc',
            'status' => 'published',
            'instructor_id' => $instructor->id,
        ]);

        $response = $this->getJson('/api/courses');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $course->id)
            ->assertJsonPath('data.0.instructor', 'Prof. Alex Smith');
    }

    public function test_public_course_show_returns_instructor_name_and_id(): void
    {
        $instructor = Instructor::create(['name' => 'Dr. Jane Watson']);

        $course = Course::create([
            'title' => 'Jane Course',
            'description' => 'Desc',
            'status' => 'published',
            'instructor_id' => $instructor->id,
        ]);

        $response = $this->getJson("/api/courses/{$course->id}");

        $response->assertOk()
            ->assertJsonPath('data.instructor_name', 'Dr. Jane Watson')
            ->assertJsonPath('data.instructor_id', $instructor->id);
    }

    public function test_instructor_name_accessor_handles_unloaded_and_loaded_safely(): void
    {
        $instructor = Instructor::create(['name' => 'Sarah Connor']);

        $course = Course::create([
            'title' => 'Safe Course',
            'description' => 'Desc',
            'instructor_id' => $instructor->id,
        ]);

        // When instructor relation is not loaded, it should return null without LazyLoadingViolationException
        $this->assertNull($course->instructor_name);

        // When instructor relation is loaded, it should return the name
        $course->load('instructor');
        $this->assertSame('Sarah Connor', $course->instructor_name);
    }

    public function test_free_course_enroll_creates_approved_enrollment_immediately(): void
    {
        $course = Course::create([
            'title' => 'Free React Course',
            'description' => 'Learn React Free',
            'status' => 'published',
            'is_public' => true,
            'price' => 0,
        ]);

        $student = User::factory()->student()->create();

        $response = $this->actingAs($student, 'sanctum')
            ->postJson("/api/courses/{$course->id}/enroll");

        $response->assertCreated()
            ->assertJsonPath('status', 'approved');

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'approved',
        ]);
    }

    public function test_free_course_watching_lesson_progress_auto_enrolls_in_my_courses(): void
    {
        $course = Course::create([
            'title' => 'Free Vue Course',
            'description' => 'Learn Vue Free',
            'status' => 'published',
            'is_public' => true,
            'price' => 0,
        ]);

        $section = Section::create(['course_id' => $course->id, 'title' => 'Intro', 'order' => 1]);
        $lesson = Lesson::create([
            'section_id' => $section->id,
            'title' => 'Lesson 1',
            'duration' => 15,
            'order' => 1,
            'is_preview' => false,
        ]);

        $student = User::factory()->student()->create();

        // Update progress on lesson
        $this->actingAs($student, 'sanctum')
            ->postJson("/api/lessons/{$lesson->id}/progress", [
                'watched_percent' => 50,
                'last_position' => 120,
            ])
            ->assertOk();

        // Check enrollment exists as approved
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'approved',
        ]);

        // Check it appears in /api/my-courses
        $myCoursesResponse = $this->actingAs($student, 'sanctum')
            ->getJson('/api/my-courses');

        $myCoursesResponse->assertOk()
            ->assertJsonFragment(['course_id' => $course->id]);
    }
}
