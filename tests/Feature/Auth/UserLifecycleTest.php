<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\ZoomMeeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_soft_deleted_user_cannot_login(): void
    {
        $user = User::factory()->student()->create([
            'email' => 'softdeleted@example.com',
            'password' => 'Passw0rdX',
        ]);
        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $this->postJson('/api/auth/login', [
            'email' => 'softdeleted@example.com',
            'password' => 'Passw0rdX',
        ])->assertUnauthorized();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->student()->create([
            'email' => 'inactive_user@example.com',
            'password' => 'Passw0rdX',
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'inactive_user@example.com',
            'password' => 'Passw0rdX',
        ])
            ->assertUnauthorized()
            ->assertJson(['message' => 'تم تعطيل هذا الحساب، يرجى التواصل مع الإدارة']);
    }

    public function test_soft_deleted_and_inactive_users_are_excluded_from_student_and_user_lists_or_marked(): void
    {
        Sanctum::actingAs($this->admin);

        $activeStudent = User::factory()->student()->create(['name' => 'Active Student', 'is_active' => true]);
        $inactiveStudent = User::factory()->student()->create(['name' => 'Inactive Student', 'is_active' => false]);
        $deletedStudent = User::factory()->student()->create(['name' => 'Deleted Student', 'is_active' => true]);
        $deletedStudent->delete();

        // 1. Check admin students list excludes soft-deleted users
        $studentsRes = $this->getJson('/api/admin/students');
        $studentsRes->assertOk();

        $studentIds = collect($studentsRes->json('data'))->pluck('id')->all();
        $this->assertContains($activeStudent->id, $studentIds);
        $this->assertNotContains($deletedStudent->id, $studentIds);

        // 2. Check admin users list excludes soft-deleted users
        $usersRes = $this->getJson('/api/admin/users');
        $usersRes->assertOk();

        $userIds = collect($usersRes->json('data'))->pluck('id')->all();
        $this->assertContains($activeStudent->id, $userIds);
        $this->assertNotContains($deletedStudent->id, $userIds);
    }

    public function test_soft_deleted_student_is_excluded_from_zoom_meeting_roster(): void
    {
        Sanctum::actingAs($this->admin);

        $course = Course::create([
            'title' => 'Roster Course',
            'description' => 'Test',
            'status' => 'published',
        ]);

        $student = User::factory()->student()->create(['name' => 'Will Be Deleted']);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'approved',
        ]);

        $meeting = ZoomMeeting::create([
            'course_id' => $course->id,
            'title' => 'Meeting for Roster',
            'zoom_link' => 'https://zoom.us/j/0000000000',
            'starts_at' => now()->addHour(),
        ]);

        // Prior to soft delete: in roster
        $rosterBefore = $this->getJson("/api/admin/zoom-meetings/{$meeting->id}/roster");
        $rosterBefore->assertOk();
        $this->assertCount(1, $rosterBefore->json());

        // Soft delete the student
        $student->delete();

        // After soft delete: excluded from roster
        $rosterAfter = $this->getJson("/api/admin/zoom-meetings/{$meeting->id}/roster");
        $rosterAfter->assertOk();
        $this->assertCount(0, $rosterAfter->json());
    }
}
