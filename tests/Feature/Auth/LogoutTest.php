<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_logout_and_revokes_only_current_token(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'Passw0rdX',
        ]);

        $tokenA = $user->createToken('device_a')->plainTextToken;
        $tokenB = $user->createToken('device_b')->plainTextToken;

        // Ensure both tokens exist in the database initially
        $this->assertDatabaseCount('personal_access_tokens', 2);

        // Perform logout with tokenA
        $response = $this->withToken($tokenA)->postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJson([
                'message' => 'تم تسجيل الخروج بنجاح',
            ]);

        // Assert tokenA was revoked from the database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'name' => 'device_a',
        ]);

        // Assert tokenB remains active for the other device session
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'device_b',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Forget in-memory auth guard state between test requests
        app('auth')->forgetGuards();

        // Assert subsequent request with revoked tokenA returns 401 Unauthorized
        $this->withToken($tokenA)->getJson('/api/auth/me')->assertUnauthorized();

        app('auth')->forgetGuards();

        // Assert request with tokenB is still authenticated and functional
        $this->withToken($tokenB)->getJson('/api/auth/me')->assertOk();
    }

    public function test_unauthenticated_request_cannot_logout(): void
    {
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }
}
