<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['email' => 'student@example.com', 'password' => 'Passw0rdX']);
    }

    public function test_login_returns_token(): void
    {
        $this->makeUser();

        $this->postJson('/api/auth/login', ['email' => 'student@example.com', 'password' => 'Passw0rdX'])
            ->assertOk()
            ->assertJsonStructure(['message', 'user' => ['id', 'email'], 'token']);
    }

    public function test_wrong_password_and_unknown_email_return_same_401(): void
    {
        $this->makeUser();

        $a = $this->postJson('/api/auth/login', ['email' => 'student@example.com', 'password' => 'wrong']);
        $b = $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);

        $a->assertUnauthorized();
        $b->assertUnauthorized();
        $this->assertSame($a->json('message'), $b->json('message'));
    }

    public function test_missing_fields_return_422(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_sixth_attempt_in_a_minute_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'x'])->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'x'])->assertStatus(429);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'Passw0rdX',
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'Passw0rdX',
        ])
            ->assertUnauthorized()
            ->assertJson(['message' => 'تم تعطيل هذا الحساب، يرجى التواصل مع الإدارة']);
    }
}
