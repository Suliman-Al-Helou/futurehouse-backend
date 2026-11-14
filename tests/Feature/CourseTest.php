<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Resources\CourseDetailResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
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

    public function test_public_courses_index_limits_and_orders_by_latest(): void
    {
        $c1 = Course::create(['title' => 'Course 1', 'description' => 'D1', 'status' => 'published']);
        $c1->created_at = now()->subDays(3);
        $c1->save();

        $c2 = Course::create(['title' => 'Course 2', 'description' => 'D2', 'status' => 'published']);
        $c2->created_at = now()->subDays(2);
        $c2->save();

        $c3 = Course::create(['title' => 'Course 3', 'description' => 'D3', 'status' => 'published']);
        $c3->created_at = now()->subDays(1);
        $c3->save();

        $c4 = Course::create(['title' => 'Course 4 (Latest)', 'description' => 'D4', 'status' => 'published']);
        $c4->created_at = now();
        $c4->save();

        $response = $this->getJson('/api/courses?limit=3');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.title', 'Course 4 (Latest)');
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
            'description' => 'Lesson 1 description',
            'video_id' => 'W6NZfCO5SIk',
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
            ->assertJsonPath('data.sections.0.lessons.0.id', $lesson->id)
            ->assertJsonPath('data.sections.0.lessons.0.video_id', 'W6NZfCO5SIk')
            ->assertJsonPath('data.sections.0.lessons.0.description', 'Lesson 1 description');
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

    public function test_public_courses_index_applies_limit_parameter(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            Course::create([
                'title' => "Course {$i}",
                'description' => "Desc {$i}",
                'status' => 'published',
            ]);
        }

        $response = $this->getJson('/api/courses?limit=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_public_courses_index_caches_resolved_resource_array_keyed_by_limit(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            Course::create([
                'title' => "Course {$i}",
                'description' => "Desc {$i}",
                'status' => 'published',
            ]);
        }

        $this->getJson('/api/courses?limit=2')->assertOk();

        $cacheKey = 'courses:published:all:limit_2';
        $this->assertTrue(Cache::has($cacheKey));

        $cachedData = Cache::get($cacheKey);
        $this->assertIsArray($cachedData);
        $this->assertCount(2, $cachedData);
        $this->assertIsArray($cachedData[0]);
        $this->assertArrayHasKey('id', $cachedData[0]);
        $this->assertArrayHasKey('title', $cachedData[0]);

        // Default call without limit uses the base cache key
        $this->getJson('/api/courses')->assertOk();
        $this->assertTrue(Cache::has('courses:published:all'));
        $cachedAll = Cache::get('courses:published:all');
        $this->assertIsArray($cachedAll);
        $this->assertCount(3, $cachedAll);
    }

    public function test_public_courses_index_invalidates_dynamic_limit_cache_keys_on_content_change(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            Course::create([
                'title' => "Course {$i}",
                'description' => "Desc {$i}",
                'status' => 'published',
            ]);
        }

        $this->getJson('/api/courses?limit=2')->assertOk();
        $this->assertTrue(Cache::has('courses:published:all:limit_2'));

        // Creating a new course triggers ContentCache::forgetCourses()
        Course::create([
            'title' => 'Course 4',
            'description' => 'Desc 4',
            'status' => 'published',
        ]);

        $this->assertFalse(Cache::has('courses:published:all:limit_2'));
        $this->assertFalse(Cache::has('courses:published:all'));
    }

    public function test_public_courses_index_ignores_non_positive_limit(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            Course::create([
                'title' => "Course {$i}",
                'description' => "Desc {$i}",
                'status' => 'published',
            ]);
        }

        $responseNeg = $this->getJson('/api/courses?limit=-5');
        $responseNeg->assertOk()->assertJsonCount(3, 'data');

        $responseZero = $this->getJson('/api/courses?limit=0');
        $responseZero->assertOk()->assertJsonCount(3, 'data');

        $responseStr = $this->getJson('/api/courses?limit=abc');
        $responseStr->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_public_courses_index_works_with_file_cache_store(): void
    {
        config(['cache.default' => 'file']);

        Course::create([
            'title' => 'File Cached Course',
            'description' => 'Desc',
            'status' => 'published',
        ]);

        $response = $this->getJson('/api/courses?limit=1');
        $response->assertOk()->assertJsonCount(1, 'data');

        // Second hit loads from file cache, verifying unserialize into array without __PHP_Incomplete_Class
        $response2 = $this->getJson('/api/courses?limit=1');
        $response2->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'File Cached Course');
    }

    public function test_public_course_show_students_count_uses_approved_count_over_cached_counter(): void
    {
        $course = Course::create([
            'title' => 'Counter Test Course',
            'description' => 'Desc',
            'status' => 'published',
            'students_count' => 99,
        ]);

        $student1 = User::factory()->student()->create();
        $student2 = User::factory()->student()->create();
        $student3 = User::factory()->student()->create();
        $student4 = User::factory()->student()->create();

        // 2 approved
        Enrollment::create(['user_id' => $student1->id, 'course_id' => $course->id, 'status' => 'approved']);
        Enrollment::create(['user_id' => $student2->id, 'course_id' => $course->id, 'status' => 'approved']);
        // 1 pending
        Enrollment::create(['user_id' => $student3->id, 'course_id' => $course->id, 'status' => 'pending']);
        // 1 rejected
        Enrollment::create(['user_id' => $student4->id, 'course_id' => $course->id, 'status' => 'rejected']);

        $response = $this->getJson("/api/courses/{$course->id}");

        $response->assertOk()
            ->assertJsonPath('data.students_count', 2);
    }

    public function test_public_course_show_students_count_falls_back_when_no_enrollments(): void
    {
        $course = Course::create([
            'title' => 'Empty Course',
            'description' => 'Desc',
            'status' => 'published',
            'students_count' => 0,
        ]);

        $response = $this->getJson("/api/courses/{$course->id}");

        $response->assertOk()
            ->assertJsonPath('data.students_count', 0);
    }

    public function test_course_detail_resource_falls_back_to_students_count_attribute_when_approved_count_not_loaded(): void
    {
        $course = Course::create([
            'title' => 'Direct Resource Course',
            'description' => 'Desc',
            'students_count' => 42,
        ]);

        $resource = new CourseDetailResource($course);
        $array = $resource->toArray(request());

        $this->assertSame(42, $array['students_count']);
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

    public function test_public_courses_index_serves_from_cache_safely_on_subsequent_requests(): void
    {
        Course::create([
            'title' => 'Warm Cache Safe Course',
            'description' => 'Test warm cache deserialization',
            'level' => 'beginner',
            'status' => 'published',
            'price' => 0.0,
            'is_public' => true,
        ]);

        // Configure file cache store to verify allowed_classes => false safety
        Config::set('cache.default', 'file');
        Cache::forget('courses:published:all');

        // Hit 1: Cold cache miss
        $response1 = $this->getJson('/api/courses');
        $response1->assertOk()
            ->assertJsonPath('data.0.title', 'Warm Cache Safe Course');

        // Hit 2: Warm cache hit (verifies no __PHP_Incomplete_Class or property-on-array error)
        $response2 = $this->getJson('/api/courses');
        $response2->assertOk()
            ->assertJsonPath('data.0.title', 'Warm Cache Safe Course');
    }
}
