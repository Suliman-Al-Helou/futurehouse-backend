<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\VideoAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PaginationAndBoundedQueriesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_users_and_students_are_paginated_and_bounded(): void
    {
        Sanctum::actingAs($this->admin);

        User::factory()->student()->count(55)->create();

        // 1. /api/admin/users is paginated with default 50
        $usersRes = $this->getJson('/api/admin/users');
        $usersRes->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonCount(50, 'data');

        // 2. /api/admin/students is paginated with default 50
        $studentsRes = $this->getJson('/api/admin/students');
        $studentsRes->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonCount(50, 'data');
    }

    public function test_admin_enrollments_are_paginated_and_bounded(): void
    {
        Sanctum::actingAs($this->admin);

        $course = Course::factory()->create();
        $students = User::factory()->student()->count(55)->create();

        foreach ($students as $student) {
            Enrollment::create([
                'user_id' => $student->id,
                'course_id' => $course->id,
                'status' => 'approved',
            ]);
        }

        $response = $this->getJson('/api/admin/enrollments');
        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonCount(50, 'data');
    }

    public function test_admin_video_assets_are_paginated_with_max_limit_enforced(): void
    {
        Sanctum::actingAs($this->admin);

        VideoAsset::factory()->count(10)->create();

        // Requesting an excessively large per_page is capped at 100
        $response = $this->getJson('/api/admin/video-assets?per_page=500');
        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.per_page', 100);
    }
}
