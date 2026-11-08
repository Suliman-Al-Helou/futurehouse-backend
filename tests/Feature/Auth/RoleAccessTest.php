<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_owner_admin_routes(): void
    {
        $this->getJson('/api/admin/users')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'غير مصرح لك بالوصول، يرجى تسجيل الدخول أولاً',
            ]);

        $this->getJson('/api/admin/stats')
            ->assertUnauthorized();
    }

    public function test_student_cannot_access_owner_admin_routes(): void
    {
        $student = User::factory()->student()->create();

        Sanctum::actingAs($student);

        // Assert student receives 403 Forbidden
        $this->getJson('/api/admin/users')
            ->assertForbidden()
            ->assertJson([
                'message' => 'ليس لديك الصلاحية للوصول إلى هذا المورد',
            ]);

        $this->getJson('/api/admin/stats')
            ->assertForbidden();

        $this->getJson('/api/admin/students')
            ->assertForbidden();
    }

    public function test_admin_can_access_owner_admin_routes(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertOk();

        $this->getJson('/api/admin/students')
            ->assertOk();
    }

    public function test_role_cannot_be_manipulated_via_mass_assignment(): void
    {
        // 1. Attempting to register as admin
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'Passw0rdX',
            'password_confirmation' => 'Passw0rdX',
            'role' => 'admin',
        ]);

        $response->assertCreated();

        // Verify the created user has strictly 'student' role in DB via Spatie
        $this->assertDatabaseHas('users', [
            'email' => 'attacker@example.com',
        ]);

        $attacker = User::where('email', 'attacker@example.com')->firstOrFail();
        $this->assertTrue($attacker->hasRole('student'));
        $this->assertFalse($attacker->hasRole('admin'));

        // Verify newly registered user cannot access admin routes
        Sanctum::actingAs($attacker);

        $this->getJson('/api/admin/users')
            ->assertForbidden();
    }
}
