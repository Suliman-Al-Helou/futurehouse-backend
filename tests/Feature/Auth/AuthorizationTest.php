<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_access_admin_routes(): void
    {
        $student = User::factory()->student()->create();

        Sanctum::actingAs($student);

        $this->getJson('/api/admin/users')
            ->assertForbidden()
            ->assertJson([
                'message' => 'ليس لديك الصلاحية للوصول إلى هذا المورد',
            ]);
    }

    public function test_student_cannot_update_another_student_profile(): void
    {
        $studentA = User::factory()->student()->create();
        $studentB = User::factory()->student()->create();

        Sanctum::actingAs($studentA);

        $this->putJson("/api/users/{$studentB->id}", [
            'name' => 'Hacked Name',
        ])
            ->assertForbidden()
            ->assertJson([
                'message' => 'ليس لديك الصلاحية للوصول إلى هذا المورد',
            ]);
    }

    public function test_student_can_update_own_profile(): void
    {
        $student = User::factory()->student()->create();

        Sanctum::actingAs($student);

        $this->putJson("/api/users/{$student->id}", [
            'name' => 'Updated Name',
        ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertOk();
    }

    public function test_admin_can_update_any_user_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$student->id}", [
            'name' => 'Admin Updated Name',
        ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Admin Updated Name']);
    }

    public function test_pure_spatie_has_role_and_role_names(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($student->hasRole('student'));
        $this->assertTrue($student->hasRole(UserRole::STUDENT));
        $this->assertTrue($student->hasRole('student|admin'));
        $this->assertFalse($student->hasRole('admin'));
        $this->assertFalse($student->isAdmin());
        $this->assertTrue($student->isStudent());
        $this->assertTrue($student->getRoleNames()->contains('student'));

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->hasRole(UserRole::ADMIN));
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->hasRole('student'));
        $this->assertFalse($admin->isStudent());
        $this->assertTrue($admin->getRoleNames()->contains('admin'));
    }

    public function test_spatie_has_roles_integration_with_explicit_spatie_roles(): void
    {
        Role::findOrCreate('instructor', 'web');

        $user = User::factory()->student()->create();
        $user->assignRole('instructor');

        $this->assertTrue($user->hasRole('instructor'));
        $this->assertTrue($user->hasRole('student'));
        $this->assertTrue($user->isStudent());
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->getRoleNames()->contains('instructor'));
        $this->assertTrue($user->hasAllRoles(['student', 'instructor']));
        $this->assertTrue($user->hasRole($user->roles));
    }

    public function test_global_gate_before_authorizes_admin_across_policies(): void
    {
        $admin = User::factory()->admin()->create();
        $studentA = User::factory()->student()->create();
        $studentB = User::factory()->student()->create();

        $course = Course::create([
            'title' => 'Paid Architecture Course',
            'description' => 'Detailed course description',
            'level' => 'advanced',
            'status' => 'published',
            'what_you_learn' => ['Architecture'],
            'requirements' => ['PHP'],
            'target_audience' => ['Architects'],
            'rating' => 5.0,
            'price' => 100.0,
            'is_public' => false,
        ]);

        // Student A cannot watch paid course without enrollment
        $this->assertFalse(Gate::forUser($studentA)->allows('watch', $course));

        // Admin bypasses CoursePolicy via global Gate::before
        $this->assertTrue(Gate::forUser($admin)->allows('watch', $course));

        // Student A cannot update Student B (UserPolicy)
        $this->assertFalse(Gate::forUser($studentA)->allows('update', $studentB));

        // Admin bypasses UserPolicy via global Gate::before
        $this->assertTrue(Gate::forUser($admin)->allows('update', $studentB));

        // Admin bypasses LessonPolicy via global Gate::before
        $lesson = new Lesson;
        $this->assertFalse(Gate::forUser($studentA)->allows('update', $lesson));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $lesson));
    }
}
