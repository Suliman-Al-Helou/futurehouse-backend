<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_does_not_enumerate_users_for_non_existent_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'إذا كان هذا البريد مسجلاً في منصتنا، فقد أرسلنا رابط إعادة التعيين إلى بريدك الإلكتروني الحين.',
            ]);

        Notification::assertNothingSent();
    }

    public function test_forgot_password_sends_notification_for_valid_user(): void
    {
        Notification::fake();

        $user = User::factory()->student()->create([
            'email' => 'student@example.com',
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'student@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'إذا كان هذا البريد مسجلاً في منصتنا، فقد أرسلنا رابط إعادة التعيين إلى بريدك الإلكتروني الحين.',
            ]);

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) {
                return ! empty($notification->token);
            }
        );
    }

    public function test_reset_password_succeeds_with_valid_token_and_revokes_active_tokens(): void
    {
        $user = User::factory()->student()->create([
            'email' => 'student@example.com',
            'password' => 'OldPassword123',
        ]);

        $activeToken = $user->createToken('session_device')->plainTextToken;
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $resetToken = Password::broker()->createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'student@example.com',
            'token' => $resetToken,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'تم إعادة تعيين كلمة المرور بنجاح، يمكنك الآن تسجيل الدخول ببياناتك الجديدة.',
            ]);

        // Assert password updated in database
        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));

        // Assert all prior active personal access tokens were purged
        $this->assertDatabaseEmpty('personal_access_tokens');

        // Assert old token can no longer authenticate
        $this->withToken($activeToken)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_reset_password_token_is_single_use(): void
    {
        $user = User::factory()->student()->create([
            'email' => 'student@example.com',
            'password' => 'OldPassword123',
        ]);

        $resetToken = Password::broker()->createToken($user);

        // First reset must succeed
        $this->postJson('/api/auth/reset-password', [
            'email' => 'student@example.com',
            'token' => $resetToken,
            'password' => 'FirstNewPass123',
            'password_confirmation' => 'FirstNewPass123',
        ])->assertOk();

        // Second reset with the same token must fail (single-use constraint)
        $this->postJson('/api/auth/reset-password', [
            'email' => 'student@example.com',
            'token' => $resetToken,
            'password' => 'SecondNewPass123',
            'password_confirmation' => 'SecondNewPass123',
        ])->assertStatus(422)
            ->assertJson([
                'message' => 'الرابط غير صالح، أو تم استخدامه مسبقاً، أو انتهت صلاحيته الأمنية.',
            ]);
    }

    public function test_reset_password_fails_when_token_is_expired(): void
    {
        $user = User::factory()->student()->create([
            'email' => 'student@example.com',
            'password' => 'OldPassword123',
        ]);

        $resetToken = Password::broker()->createToken($user);

        // Fast-forward 61 minutes into the future (expiry configured to 60 mins)
        Carbon::setTestNow(now()->addMinutes(61));

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'student@example.com',
            'token' => $resetToken,
            'password' => 'ExpiredTokenPass123',
            'password_confirmation' => 'ExpiredTokenPass123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'الرابط غير صالح، أو تم استخدامه مسبقاً، أو انتهت صلاحيته الأمنية.',
            ]);

        Carbon::setTestNow(); // Reset time
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        User::factory()->student()->create([
            'email' => 'student@example.com',
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'student@example.com',
            'token' => 'invalid-garbage-token',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'الرابط غير صالح، أو تم استخدامه مسبقاً، أو انتهت صلاحيته الأمنية.',
            ]);
    }

    public function test_protected_routes_reject_garbage_and_malformed_tokens(): void
    {
        $this->withToken('garbage-unrecognized-bearer-token')
            ->getJson('/api/auth/me')
            ->assertUnauthorized();

        $this->withHeader('Authorization', 'MalformedTokenWithoutBearer')
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }
}
