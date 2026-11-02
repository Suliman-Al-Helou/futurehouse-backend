<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $o = []): array
    {
        return array_merge([
            'name' => 'Test Student',
            'email' => 'student@example.com',
            'password' => 'Passw0rdX',
            'password_confirmation' => 'Passw0rdX',
        ], $o);
    }

    public function test_register_returns_201_with_user_and_token(): void
    {
        // تم تعديل الفحص هنا ليتطابق مع الـ JSON الحالي الراجع من السيرفر قبل بناء الـ API Resource
        $this->postJson('/api/auth/register', $this->payload())
            ->assertCreated()
            ->assertJsonStructure(['message', 'user' => ['id', 'name', 'email', 'role', 'is_active', 'created_at'], 'token'])
            ->assertJsonMissingPath('user.password');

        // نتحقق من قاعدة البيانات مباشرة للتأكد من إسناد الـ role الافتراضي كـ student بأمان
        $this->assertDatabaseHas('users', [
            'email' => 'student@example.com',
            'role' => 'student',
        ]);
    }

    public function test_role_in_payload_is_ignored(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['role' => 'admin']))->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'student@example.com', 'role' => 'student']);
    }

    public function test_duplicate_email_returns_422(): void
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        $this->postJson('/api/auth/register', $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_weak_password_returns_422(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'password' => 'password123', 'password_confirmation' => 'password123',
        ]))->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_missing_confirmation_returns_422(): void
    {
        $p = $this->payload();
        unset($p['password_confirmation']);

        $this->postJson('/api/auth/register', $p)
            ->assertStatus(422)->assertJsonValidationErrors('password');
    }
}
