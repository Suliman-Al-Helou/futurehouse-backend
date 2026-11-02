<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_access_admin_routes(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/admin/users')
            ->assertForbidden()
            ->assertJson([
                'message' => 'ليس لديك الصلاحية للوصول إلى هذا المورد',
            ]);
    }

    public function test_student_cannot_update_another_student_profile(): void
    {
        $studentA = User::factory()->create([
            'role' => UserRole::STUDENT,
        ]);
        $studentB = User::factory()->create([
            'role' => UserRole::STUDENT,
        ]);

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
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
        ]);

        Sanctum::actingAs($student);

        $this->putJson("/api/users/{$student->id}", [
            'name' => 'Updated Name',
        ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertOk();
    }

    public function test_admin_can_update_any_user_profile(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);
        $student = User::factory()->create([
            'role' => UserRole::STUDENT,
        ]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$student->id}", [
            'name' => 'Admin Updated Name',
        ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Admin Updated Name']);
    }
}

