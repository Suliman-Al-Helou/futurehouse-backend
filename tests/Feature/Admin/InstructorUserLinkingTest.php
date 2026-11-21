<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Instructor;
use App\Models\User;
use App\Services\InstructorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructorUserLinkingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('instructor', 'web');
        $this->admin = User::factory()->admin()->create();
        Sanctum::actingAs($this->admin);
    }

    public function test_admin_can_link_user_to_instructor_profile(): void
    {
        $user = User::factory()->student()->create();
        $this->assertFalse($user->hasRole('instructor'));

        $response = $this->postJson('/api/admin/instructors', [
            'name' => 'المدرب أحمد',
            'title' => 'كبير مهندسي النظم',
            'user_id' => $user->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);

        $this->assertDatabaseHas('instructors', [
            'name' => 'المدرب أحمد',
            'user_id' => $user->id,
        ]);

        $this->assertTrue($user->fresh()->hasRole('instructor'));
    }

    public function test_linking_admin_user_is_rejected_with_422(): void
    {
        $adminCandidate = User::factory()->admin()->create();

        $response = $this->postJson('/api/admin/instructors', [
            'name' => 'المدرب خالد',
            'user_id' => $adminCandidate->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_duplicate_link_rejected_with_422(): void
    {
        $user = User::factory()->student()->create();

        Instructor::create([
            'name' => 'المدرب الأول',
            'user_id' => $user->id,
        ]);

        $response = $this->postJson('/api/admin/instructors', [
            'name' => 'المدرب الثاني',
            'user_id' => $user->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_updating_instructor_to_already_linked_user_is_rejected_with_422(): void
    {
        $userA = User::factory()->student()->create();
        $userB = User::factory()->student()->create();

        Instructor::create([
            'name' => 'المدرب الأول',
            'user_id' => $userA->id,
        ]);

        $instructorB = Instructor::create([
            'name' => 'المدرب الثاني',
            'user_id' => $userB->id,
        ]);

        $response = $this->putJson("/api/admin/instructors/{$instructorB->id}", [
            'user_id' => $userA->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_migration_rollback_works(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('instructors', 'user_id'));

        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2026_11_18_153000_add_user_id_to_instructors_table.php'])->assertSuccessful();

        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('instructors', 'user_id'));

        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_18_153000_add_user_id_to_instructors_table.php'])->assertSuccessful();

        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('instructors', 'user_id'));
    }

    public function test_linking_non_existent_user_is_rejected_with_422(): void
    {
        $response = $this->postJson('/api/admin/instructors', [
            'name' => 'مدرب شبح',
            'user_id' => 999999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_linking_soft_deleted_user_is_rejected_with_422(): void
    {
        $user = User::factory()->student()->create();
        $user->delete();
        $this->assertSoftDeleted($user);

        $response = $this->postJson('/api/admin/instructors', [
            'name' => 'مدرب محذوف',
            'user_id' => $user->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_unlinking_user_removes_instructor_role(): void
    {
        $user = User::factory()->student()->create();
        $instructor = app(InstructorService::class)->create([
            'name' => 'المدرب سامي',
            'user_id' => $user->id,
        ]);

        $this->assertTrue($user->fresh()->hasRole('instructor'));

        $response = $this->putJson("/api/admin/instructors/{$instructor->id}", [
            'user_id' => null,
        ]);

        $response->assertOk()
            ->assertJsonPath('user_id', null);

        $this->assertFalse($user->fresh()->hasRole('instructor'));
    }

    public function test_deleting_instructor_removes_instructor_role(): void
    {
        $user = User::factory()->student()->create();
        $instructor = app(InstructorService::class)->create([
            'name' => 'مدرب مؤقت',
            'user_id' => $user->id,
        ]);

        $this->assertTrue($user->fresh()->hasRole('instructor'));

        $response = $this->deleteJson("/api/admin/instructors/{$instructor->id}");
        $response->assertNoContent();

        $this->assertFalse($user->fresh()->hasRole('instructor'));
    }

    public function test_updating_instructor_without_user_id_keeps_existing_link(): void
    {
        $user = User::factory()->student()->create();
        $instructor = app(InstructorService::class)->create([
            'name' => 'المدرب الأصلي',
            'user_id' => $user->id,
        ]);

        $response = $this->putJson("/api/admin/instructors/{$instructor->id}", [
            'name' => 'المدرب الأصلي بعد التعديل',
        ]);

        $response->assertOk()
            ->assertJsonPath('name', 'المدرب الأصلي بعد التعديل')
            ->assertJsonPath('user_id', $user->id);

        $this->assertEquals($user->id, $instructor->fresh()->user_id);
        $this->assertTrue($user->fresh()->hasRole('instructor'));
    }

    public function test_relinking_to_another_user_moves_role(): void
    {
        $userA = User::factory()->student()->create();
        $userB = User::factory()->student()->create();

        $instructor = app(InstructorService::class)->create([
            'name' => 'مدرب متنقل',
            'user_id' => $userA->id,
        ]);

        $this->assertTrue($userA->fresh()->hasRole('instructor'));
        $this->assertFalse($userB->fresh()->hasRole('instructor'));

        $response = $this->putJson("/api/admin/instructors/{$instructor->id}", [
            'user_id' => $userB->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('user_id', $userB->id);

        $this->assertFalse($userA->fresh()->hasRole('instructor'));
        $this->assertTrue($userB->fresh()->hasRole('instructor'));
    }

    public function test_service_throws_exception_if_instructor_role_missing(): void
    {
        $user = User::factory()->student()->create();

        // Delete the instructor role to simulate missing seed/migration
        Role::where('name', 'instructor')->delete();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Role 'instructor' not found in database. Run database migrations or seeders.");

        app(InstructorService::class)->create([
            'name' => 'مدرب بدون دور',
            'user_id' => $user->id,
        ]);
    }

    public function test_updating_instructor_with_admin_user_is_rejected_with_422(): void
    {
        $instructor = Instructor::create([
            'name' => 'مدرب أصلي',
        ]);
        $adminCandidate = User::factory()->admin()->create();

        $response = $this->putJson("/api/admin/instructors/{$instructor->id}", [
            'user_id' => $adminCandidate->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_admin_users_endpoint_caps_limit_at_twenty(): void
    {
        User::factory()->count(25)->student()->create();

        $response = $this->getJson('/api/admin/users?limit=50');

        $response->assertOk();
        $this->assertLessThanOrEqual(20, count($response->json('data')));
        $this->assertEquals(20, $response->json('meta.per_page'));
    }

    public function test_admin_can_create_display_only_instructor_profile(): void
    {
        $response = $this->postJson('/api/admin/instructors', [
            'name' => 'الدكتور الزائر',
            'title' => 'أستاذ زائر في هندسة البرمجيات',
            'user_id' => null,
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'الدكتور الزائر')
            ->assertJsonPath('user_id', null)
            ->assertJsonPath('is_display_only', true)
            ->assertJsonPath('user', null);

        $this->assertDatabaseHas('instructors', [
            'name' => 'الدكتور الزائر',
            'user_id' => null,
        ]);

        $instructor = Instructor::where('name', 'الدكتور الزائر')->firstOrFail();
        $this->assertTrue($instructor->isDisplayOnly());
        $this->assertNull($instructor->user);
    }

    public function test_admin_can_view_display_only_instructor_profile_with_flag(): void
    {
        $displayOnly = app(InstructorService::class)->create([
            'name' => 'مدرب عرض فقط',
            'user_id' => null,
        ]);

        $linkedUser = User::factory()->student()->create();
        $linked = app(InstructorService::class)->create([
            'name' => 'مدرب مربوط بحساب',
            'user_id' => $linkedUser->id,
        ]);

        // 1. Show endpoint: display-only profile
        $showDisplayOnly = $this->getJson("/api/admin/instructors/{$displayOnly->id}");
        $showDisplayOnly->assertOk()
            ->assertJsonPath('id', $displayOnly->id)
            ->assertJsonPath('user_id', null)
            ->assertJsonPath('is_display_only', true)
            ->assertJsonPath('user', null);

        // 2. Show endpoint: linked profile
        $showLinked = $this->getJson("/api/admin/instructors/{$linked->id}");
        $showLinked->assertOk()
            ->assertJsonPath('id', $linked->id)
            ->assertJsonPath('user_id', $linkedUser->id)
            ->assertJsonPath('is_display_only', false)
            ->assertJsonPath('user.id', $linkedUser->id);

        // 3. Index endpoint: list both profiles
        $indexResponse = $this->getJson('/api/admin/instructors');
        $indexResponse->assertOk();

        $displayOnlyItem = collect($indexResponse->json('data'))->firstWhere('id', $displayOnly->id);
        $this->assertNotNull($displayOnlyItem);
        $this->assertNull($displayOnlyItem['user_id']);
        $this->assertTrue($displayOnlyItem['is_display_only']);
        $this->assertNull($displayOnlyItem['user']);

        $linkedItem = collect($indexResponse->json('data'))->firstWhere('id', $linked->id);
        $this->assertNotNull($linkedItem);
        $this->assertEquals($linkedUser->id, $linkedItem['user_id']);
        $this->assertFalse($linkedItem['is_display_only']);
        $this->assertEquals($linkedUser->id, $linkedItem['user']['id']);
    }

    public function test_public_and_student_endpoints_strictly_isolate_user_data_for_both_display_only_and_linked_profiles(): void
    {
        $linkedUser = User::factory()->student()->create();
        $linked = app(InstructorService::class)->create([
            'name' => 'مدرب مرتبط',
            'user_id' => $linkedUser->id,
        ]);

        $displayOnly = app(InstructorService::class)->create([
            'name' => 'مدرب للعرض فقط',
            'user_id' => null,
        ]);

        // 1. Guest public index
        app('auth')->forgetGuards();
        $guestIndex = $this->getJson('/api/instructors');
        $guestIndex->assertOk();

        foreach ([$linked->id, $displayOnly->id] as $id) {
            $item = collect($guestIndex->json('data'))->firstWhere('id', $id);
            $this->assertNotNull($item);
            $this->assertArrayNotHasKey('user_id', $item, 'public index must not expose user_id');
            $this->assertArrayNotHasKey('user', $item, 'public index must not expose user object');
            $this->assertArrayNotHasKey('is_display_only', $item, 'public index must not expose is_display_only');
        }

        // 2. Guest public show
        foreach ([$linked, $displayOnly] as $profile) {
            $guestShow = $this->getJson("/api/instructors/{$profile->id}");
            $guestShow->assertOk();
            $data = $guestShow->json();
            $this->assertArrayNotHasKey('user_id', $data, 'public show must not expose user_id');
            $this->assertArrayNotHasKey('user', $data, 'public show must not expose user object');
            $this->assertArrayNotHasKey('is_display_only', $data, 'public show must not expose is_display_only');
        }

        // 3. Authenticated student requests
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $studentIndex = $this->getJson('/api/instructors');
        $studentIndex->assertOk();
        foreach ([$linked->id, $displayOnly->id] as $id) {
            $item = collect($studentIndex->json('data'))->firstWhere('id', $id);
            $this->assertNotNull($item);
            $this->assertArrayNotHasKey('user_id', $item, 'student index must not expose user_id');
            $this->assertArrayNotHasKey('user', $item, 'student index must not expose user object');
            $this->assertArrayNotHasKey('is_display_only', $item, 'student index must not expose is_display_only');
        }

        foreach ([$linked, $displayOnly] as $profile) {
            $studentShow = $this->getJson("/api/instructors/{$profile->id}");
            $studentShow->assertOk();
            $data = $studentShow->json();
            $this->assertArrayNotHasKey('user_id', $data, 'student show must not expose user_id');
            $this->assertArrayNotHasKey('user', $data, 'student show must not expose user object');
            $this->assertArrayNotHasKey('is_display_only', $data, 'student show must not expose is_display_only');
        }
    }

    public function test_malformed_user_id_inputs_fail_with_422_on_create_and_update(): void
    {
        $existingInstructor = app(InstructorService::class)->create([
            'name' => 'مدرب قائم',
            'user_id' => null,
        ]);

        $malformedInputs = [
            'string' => 'invalid-text-identifier',
            'negative' => -999,
            'zero' => 0,
            'boolean' => true,
            'array' => [1, 2],
        ];

        foreach ($malformedInputs as $type => $badValue) {
            // Test POST (Create)
            $createResponse = $this->postJson('/api/admin/instructors', [
                'name' => "مدرب مشوه {$type}",
                'user_id' => $badValue,
            ]);
            $createResponse->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);

            // Test PUT (Update)
            $updateResponse = $this->putJson("/api/admin/instructors/{$existingInstructor->id}", [
                'user_id' => $badValue,
            ]);
            $updateResponse->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);
        }
    }

    public function test_eloquent_model_helpers_and_relations_for_display_only_and_user(): void
    {
        $displayOnly = Instructor::create([
            'name' => 'مدرب حر',
            'user_id' => null,
        ]);

        $this->assertTrue($displayOnly->isDisplayOnly());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $displayOnly->user());
        $this->assertNull($displayOnly->user);

        $user = User::factory()->student()->create();
        $this->assertFalse($user->isInstructor());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class, $user->instructor());

        $linked = app(InstructorService::class)->create([
            'name' => 'مدرب معتمد',
            'user_id' => $user->id,
        ]);

        $this->assertFalse($linked->isDisplayOnly());
        $this->assertTrue($user->fresh()->isInstructor());
        $this->assertEquals($linked->id, $user->fresh()->instructor->id);
    }

    public function test_soft_deleting_user_automatically_unlinks_instructor_profile(): void
    {
        $user = User::factory()->student()->create();
        $instructor = app(InstructorService::class)->create([
            'name' => 'مدرب مرتبط',
            'user_id' => $user->id,
        ]);

        $this->assertEquals($user->id, $instructor->fresh()->user_id);

        $user->delete();
        $this->assertSoftDeleted($user);

        $this->assertNull($instructor->fresh()->user_id);
    }

    public function test_demoting_instructor_user_role_via_admin_controller_automatically_unlinks_instructor_profile(): void
    {
        $user = User::factory()->student()->create();
        $instructor = app(InstructorService::class)->create([
            'name' => 'مدرب ترقية',
            'user_id' => $user->id,
        ]);

        $this->assertTrue($user->fresh()->hasRole('instructor'));
        $this->assertEquals($user->id, $instructor->fresh()->user_id);

        // Admin demotes role to student
        Sanctum::actingAs($this->admin);
        $response = $this->patchJson("/api/admin/users/{$user->id}", [
            'role' => 'student',
        ]);

        $response->assertOk();
        $this->assertFalse($user->fresh()->hasRole('instructor'));
        $this->assertNull($instructor->fresh()->user_id);
    }
}
