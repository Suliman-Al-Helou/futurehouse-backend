<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_factory_creates_valid_course(): void
    {
        $course = Course::factory()->create([
            'title' => 'Mastering Laravel',
        ]);

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => 'Mastering Laravel',
        ]);
        $this->assertNotNull($course->description);
        $this->assertIsArray($course->what_you_learn);
    }

    public function test_course_factory_states(): void
    {
        $draft = Course::factory()->draft()->create();
        $this->assertSame('draft', $draft->status);

        $published = Course::factory()->published()->create();
        $this->assertSame('published', $published->status);

        $comingSoon = Course::factory()->comingSoon()->create();
        $this->assertSame('coming_soon', $comingSoon->status);

        $free = Course::factory()->free()->create();
        $this->assertTrue($free->is_public);
        $this->assertEquals(0, $free->price);

        $paid = Course::factory()->paid()->create();
        $this->assertFalse($paid->is_public);
        $this->assertGreaterThan(0, $paid->price);
    }

    public function test_section_factory_creates_valid_section(): void
    {
        $section = Section::factory()->create([
            'title' => 'Getting Started',
        ]);

        $this->assertDatabaseHas('sections', [
            'id' => $section->id,
            'title' => 'Getting Started',
            'course_id' => $section->course_id,
        ]);
        $this->assertInstanceOf(Course::class, $section->course);
    }

    public function test_lesson_factory_creates_valid_lesson(): void
    {
        $lesson = Lesson::factory()->create([
            'title' => 'Introduction Video',
        ]);

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'title' => 'Introduction Video',
            'section_id' => $lesson->section_id,
        ]);
        $this->assertInstanceOf(Section::class, $lesson->section);
        $this->assertFalse($lesson->is_preview);

        $previewLesson = Lesson::factory()->preview()->create();
        $this->assertTrue($previewLesson->is_preview);
    }

    public function test_enrollment_factory_creates_valid_enrollment(): void
    {
        $enrollment = Enrollment::factory()->create();

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
            'status' => 'pending',
        ]);
        $this->assertInstanceOf(User::class, $enrollment->user);
        $this->assertInstanceOf(Course::class, $enrollment->course);
    }

    public function test_enrollment_factory_states(): void
    {
        $approved = Enrollment::factory()->approved()->create();
        $this->assertSame('approved', $approved->status);

        $rejected = Enrollment::factory()->rejected()->create();
        $this->assertSame('rejected', $rejected->status);

        $completed = Enrollment::factory()->completed()->create();
        $this->assertSame('approved', $completed->status);
        $this->assertNotNull($completed->completed_at);
    }
}
