<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Validates backend API contracts relied upon by the Next.js BFF layer (ADR 0003).
 */
class BffSessionContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_me_returns_full_user_resource_contract(): void
    {
        /** @var User $user */
        $user = User::factory()->student()->create([
            'name' => 'Ahmad Dev',
            'email' => 'ahmad@example.com',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJson([
                'id' => $user->id,
                'name' => 'Ahmad Dev',
                'email' => 'ahmad@example.com',
                'role' => 'student',
                'is_active' => true,
            ]);
    }

    public function test_auth_me_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_logout_revokes_current_access_token(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJson(['message' => 'تم تسجيل الخروج بنجاح']);

        $this->assertDatabaseEmpty('personal_access_tokens');
    }

    public function test_user_update_contract_returns_updated_user_for_bff_resealing(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['name' => 'Old Name']);
        Sanctum::actingAs($user, ['*']);

        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => $user->email,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role', 'is_active', 'created_at'],
            ])
            ->assertJsonPath('user.name', 'Updated Name');
    }
}
