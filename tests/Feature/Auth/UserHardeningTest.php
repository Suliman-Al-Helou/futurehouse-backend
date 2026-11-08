<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $o = []): array
    {
        return array_merge([
            'name' => 'T', 'email' => 'student@example.com',
            'password' => 'Passw0rdX', 'password_confirmation' => 'Passw0rdX',
        ], $o);
    }

    public function test_schema_changes_applied(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'remember_token'));
        $this->assertTrue(Schema::hasColumn('users', 'is_active'));
        $this->assertTrue(Schema::hasColumn('users', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('users', 'role'));
    }

    public function test_model_lowercases_email_and_defaults_are_safe(): void
    {
        $u = User::factory()->create(['email' => '  MiXed@Example.COM ']);

        $this->assertSame('mixed@example.com', $u->email);
        $this->assertTrue($u->fresh()->is_active);
        $this->assertTrue($u->fresh()->hasRole('student'));
    }

    public function test_role_is_not_mass_assignable(): void
    {
        $u = User::create(['name' => 'x', 'email' => 'x@example.com', 'password' => 'Passw0rdX', 'role' => 'admin']);

        $this->assertFalse($u->fresh()->hasRole('admin'));
    }

    public function test_case_variants_cannot_register_twice(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['email' => 'MiXed@Example.com']))->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'mixed@example.com']);

        $this->postJson('/api/auth/register', $this->payload(['email' => 'mixed@EXAMPLE.com']))
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_login_is_case_insensitive_on_email(): void
    {
        User::factory()->create(['email' => 'student@example.com', 'password' => 'Passw0rdX']);

        $this->postJson('/api/auth/login', ['email' => 'STUDENT@example.com', 'password' => 'Passw0rdX'])->assertOk();
    }

    public function test_archived_email_can_register_again(): void
    {
        User::factory()->create(['email' => 'student@example.com'])->delete();

        $this->postJson('/api/auth/register', $this->payload())->assertCreated();
    }

    public function test_db_rejects_uppercase_email_on_postgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('CHECK constraint is Postgres-only.');
        }

        $this->expectException(QueryException::class);
        DB::table('users')->insert(['name' => 'x', 'email' => 'UP@example.com', 'password' => 'x']);
    }

    public function test_duplicate_active_email_rejected_by_database_constraint(): void
    {
        DB::table('users')->insert([
            'name' => 'First User',
            'email' => 'active_unique@example.com',
            'password' => 'secret',
        ]);

        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'name' => 'Second User',
            'email' => 'active_unique@example.com',
            'password' => 'secret',
        ]);
    }
}
