<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Faq;
use App\Models\Instructor;
use App\Models\User;
use App\Support\ContentCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CacheGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_catalog_cache_invalidation_on_course_update_and_delete(): void
    {
        ContentCache::forgetCourses();

        $course = Course::factory()->create([
            'title' => 'Original Course Title',
            'status' => 'published',
        ]);

        // 1. Initial Read populates cache
        $firstRes = $this->getJson('/api/courses');
        $firstRes->assertOk()->assertJsonPath('data.0.title', 'Original Course Title');
        $this->assertTrue(Cache::has('courses:published:all'));

        // 2. Write (Update): should invalidate cache
        $course->update(['title' => 'Updated Course Title']);
        $this->assertFalse(Cache::has('courses:published:all'), 'Cache should be invalidated after course update');

        // 3. Subsequent Read returns fresh data
        $secondRes = $this->getJson('/api/courses');
        $secondRes->assertOk()->assertJsonPath('data.0.title', 'Updated Course Title');

        // 4. Write (Delete): should invalidate cache
        $course->delete();
        $this->assertFalse(Cache::has('courses:published:all'), 'Cache should be invalidated after course delete');

        // 5. Read returns empty list
        $thirdRes = $this->getJson('/api/courses');
        $thirdRes->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_faqs_cache_invalidation_on_write(): void
    {
        ContentCache::forgetFaqs();

        $faq = Faq::create([
            'question' => 'Original Question?',
            'answer' => 'Original Answer',
            'order' => 1,
            'is_active' => true,
        ]);

        // Initial read warms cache
        $res1 = $this->getJson('/api/faqs');
        $res1->assertOk()->assertJsonPath('data.0.question', 'Original Question?');
        $this->assertTrue(Cache::has('faqs:all'));

        // Admin updates FAQ
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/faqs/{$faq->id}", [
            'question' => 'Updated Question?',
            'answer' => 'Updated Answer',
        ])->assertOk();

        // Cache must be invalidated
        $this->assertFalse(Cache::has('faqs:all'));

        // Public read returns fresh data
        $res2 = $this->getJson('/api/faqs');
        $res2->assertOk()->assertJsonPath('data.0.question', 'Updated Question?');
    }

    public function test_instructor_cache_invalidation_on_write(): void
    {
        ContentCache::forgetInstructors();

        $instructor = Instructor::create(['name' => 'Original Instructor']);

        $res1 = $this->getJson('/api/instructors');
        $res1->assertOk()->assertJsonPath('data.0.name', 'Original Instructor');
        $this->assertTrue(Cache::has('instructors:all'));

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/instructors/{$instructor->id}", [
            'name' => 'Updated Instructor Name',
        ])->assertOk();

        $this->assertFalse(Cache::has('instructors:all'));

        $res2 = $this->getJson('/api/instructors');
        $res2->assertOk()->assertJsonPath('data.0.name', 'Updated Instructor Name');
    }

    public function test_cache_miss_and_cache_hit_parity(): void
    {
        ContentCache::forgetCourses();

        $instructor = Instructor::create(['name' => 'Parity Instructor']);
        Course::factory()->count(3)->create([
            'status' => 'published',
            'instructor_id' => $instructor->id,
        ]);

        // 1. Cache Miss response
        $missRes = $this->getJson('/api/courses');
        $missRes->assertOk();
        $missPayload = $missRes->json();

        // 2. Cache Hit response
        $hitRes = $this->getJson('/api/courses');
        $hitRes->assertOk();
        $hitPayload = $hitRes->json();

        // Bit-for-bit identical parity assertion
        $this->assertSame($missPayload, $hitPayload, 'Cache hit response must be 100% identical to cache miss response');
    }

    public function test_user_specific_data_is_never_shared_across_users(): void
    {
        $studentA = User::factory()->student()->create();
        $studentB = User::factory()->student()->create();

        $course = Course::factory()->create(['status' => 'published']);

        Enrollment::create([
            'user_id' => $studentA->id,
            'course_id' => $course->id,
            'status' => 'approved',
        ]);

        // Student A has 1 enrolled course
        Sanctum::actingAs($studentA);
        $resA = $this->getJson('/api/dashboard/stats');
        $resA->assertOk()->assertJsonPath('stats.enrolled_courses', 1);

        // Student B has 0 enrolled courses (must never receive Student A cached stats)
        Sanctum::actingAs($studentB);
        $resB = $this->getJson('/api/dashboard/stats');
        $resB->assertOk()->assertJsonPath('stats.enrolled_courses', 0);
    }
}
