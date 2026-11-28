<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VideoSecurityAndProviderEdgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_edge_unenrolled_student_cannot_access_protected_lesson(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::create([
            'title' => 'Protected Course',
            'description' => 'Private course',
            'status' => 'published',
            'is_public' => false,
            'is_free' => false,
        ]);
        $section = Section::create([
            'course_id' => $course->id,
            'title' => 'Section 1',
            'order' => 1,
        ]);
        $lesson = Lesson::create([
            'section_id' => $section->id,
            'title' => 'Secret Lesson',
            'order' => 1,
            'is_published' => true,
            'is_preview' => false,
            'duration' => 120,
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/lessons/{$lesson->id}")->assertForbidden();
    }
}
