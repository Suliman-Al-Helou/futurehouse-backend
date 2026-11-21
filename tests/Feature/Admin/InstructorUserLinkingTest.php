<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\User;
use App\Services\InstructorService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructorUserLinkingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('instructor', 'web');
        Role::findOrCreate('student', 'web');
        $this->admin = User::factory()->admin()->create();
        Sanctum::actingAs($this->admin);
    }

    // EDGE: E-01
    // COVERS: D-01
    // CURRENT: unauthenticated request to POST /api/admin/instructors is rejected with 401
    // RECOMMENDED: 401 (precedent: bootstrap/app.php:44)
    // WHY: unauthenticated clients must never access admin resources
    // EVIDENCE: app/Http/Controllers/Api/Admin/InstructorController.php:53 ; P-01
    public function test_unauthenticated_request_is_rejected(): void
    {
        app('auth')->forgetGuards();
        $response = $this->postJson('/api/admin/instructors', ['name' => 'تجربة غير مصرح']);
        $response->assertStatus(401);
    }

    // EDGE: E-02
    // COVERS: D-02
    // CURRENT: non-admin student caller is rejected with 403
    // RECOMMENDED: 403 (precedent: app/Policies/UserPolicy.php:14)
    // WHY: administrative endpoints are strictly reserved for platform administrators
    // EVIDENCE: app/Policies/UserPolicy.php:14 ; P-02
    public function test_non_admin_student_caller_is_rejected_with_403(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);
        $response = $this->postJson('/api/admin/instructors', ['name' => 'تجربة طالب']);
        $response->assertStatus(403);
    }

    // COVERS: D-03
    public function test_admin_passes_authorization_gate(): void
    {
        Sanctum::actingAs($this->admin);
        $response = $this->postJson('/api/admin/instructors', ['name' => 'تجربة مسموحة']);
        $response->assertCreated();
    }

    // EDGE: E-04
    // COVERS: D-04
    // CURRENT: valid student user linking creates profile and grants instructor role
    // RECOMMENDED: 201 with role assigned (precedent: app/Services/InstructorService.php:24)
    // WHY: deterministic linkage grants operational instructor permissions automatically
    // EVIDENCE: app/Services/InstructorService.php:24 ; P-04
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

    // EDGE: E-05
    // COVERS: D-05
    // CURRENT: creating profile with user_id null creates display-only instructor
    // RECOMMENDED: 201 with is_display_only true (precedent: app/Models/Instructor.php:61)
    // WHY: guest lecturers and catalog marketing profiles do not require platform login credentials
    // EVIDENCE: app/Models/Instructor.php:61 ; P-05
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

    // COVERS: D-06
    public function test_creating_instructor_without_name_fails_with_422(): void
    {
        $response = $this->postJson('/api/admin/instructors', [
            'name' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    // COVERS: D-07
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

    // COVERS: D-08
    public function test_linking_non_existent_user_is_rejected_with_422(): void
    {
        $response = $this->postJson('/api/admin/instructors', [
            'name' => 'مدرب شبح',
            'user_id' => 999999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    // EDGE: E-09
    // COVERS: D-09
    // CURRENT: linking soft-deleted user is rejected with 422
    // RECOMMENDED: 422 validation error (precedent: app/Http/Requests/InstructorRequest.php:31)
    // WHY: deleted or inactive accounts must not be linked to active faculty profiles
    // EVIDENCE: app/Http/Requests/InstructorRequest.php:31 ; P-09
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

    // EDGE: E-10
    // COVERS: D-10
    // CURRENT: linking already linked user to another instructor profile is rejected with 422
    // RECOMMENDED: 422 validation error (precedent: app/Http/Requests/InstructorRequest.php:32)
    // WHY: strict 1-to-1 linkage prevents profile collisions and ambiguous identity ownership
    // EVIDENCE: app/Http/Requests/InstructorRequest.php:32 ; P-10
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

    // COVERS: D-10
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

    // EDGE: E-11
    // COVERS: D-11
    // CURRENT: linking admin user to instructor profile is rejected with 422
    // RECOMMENDED: 422 validation error (precedent: app/Http/Requests/InstructorRequest.php:38)
    // WHY: platform administrators must maintain separation of duties from instructor operational roles
    // EVIDENCE: app/Http/Requests/InstructorRequest.php:38 ; P-11
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

    // COVERS: D-11
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

    // COVERS: D-12
    public function test_invalid_course_id_in_payload_fails_with_422(): void
    {
        $response = $this->postJson('/api/admin/instructors', [
            'name' => 'مدرب مواد غير صحيحة',
            'courses' => [999999],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['courses.0']);
    }

    // COVERS: D-13
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
        $this->assertTrue($userA->fresh()->hasRole('student'));
        $this->assertTrue($userB->fresh()->hasRole('instructor'));
    }

    // COVERS: D-14
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
        $this->assertTrue($user->fresh()->hasRole('student'));
    }

    // COVERS: D-14
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
        $this->assertTrue($user->fresh()->hasRole('student'));
    }

    // COVERS: D-15
    public function test_service_throws_exception_if_instructor_role_missing(): void
    {
        $user = User::factory()->student()->create();

        Role::where('name', 'instructor')->delete();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Role 'instructor' not found in database. Run database migrations or seeders.");

        app(InstructorService::class)->create([
            'name' => 'مدرب بدون دور',
            'user_id' => $user->id,
        ]);
    }

    // COVERS: D-16
    public function test_associating_courses_on_create_updates_courses(): void
    {
        $course = Course::factory()->create();

        $response = $this->postJson('/api/admin/instructors', [
            'name' => 'مدرب مادة',
            'courses' => [$course->id],
        ]);

        $response->assertCreated();
        $this->assertEquals($response->json('id'), $course->fresh()->instructor_id);
    }

    // COVERS: D-17
    public function test_creating_instructor_flushes_instructors_cache(): void
    {
        Cache::put('instructors:all', ['dummy'], 3600);
        $this->assertTrue(Cache::has('instructors:all'));

        $this->postJson('/api/admin/instructors', [
            'name' => 'مدرب كاش',
        ])->assertCreated();

        $this->assertFalse(Cache::has('instructors:all'));
    }

    // EDGE: E-18
    // COVERS: D-18
    // CURRENT: admin endpoint exposes user_id, is_display_only, and user object
    // RECOMMENDED: admin visibility includes internal identifiers (precedent: app/Http/Resources/InstructorResource.php:18)
    // WHY: administrators need full audit visibility into linked user accounts
    // EVIDENCE: app/Http/Resources/InstructorResource.php:18 ; P-18
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

    // EDGE: E-19
    // COVERS: D-19
    // CURRENT: public and student endpoints omit user_id, is_display_only, and user object
    // RECOMMENDED: sensitive user accounts are strictly sanitized for non-admins (precedent: app/Http/Resources/InstructorResource.php:18)
    // WHY: internal user accounts and personal contact emails must not leak to public visitors
    // EVIDENCE: app/Http/Resources/InstructorResource.php:18 ; P-19
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

    // COVERS: D-04
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

    // COVERS: D-04
    public function test_admin_users_search_can_filter_unlinked_only(): void
    {
        $linkedUser = User::factory()->student()->create(['name' => 'مرشح مربوط']);
        $unlinkedUser = User::factory()->student()->create(['name' => 'مرشح حر']);

        app(InstructorService::class)->create([
            'name' => 'مدرب شاغر',
            'user_id' => $linkedUser->id,
        ]);

        // Default query returns both
        $defaultResponse = $this->getJson('/api/admin/users?search=مرشح');
        $defaultResponse->assertOk();
        $defaultIds = collect($defaultResponse->json('data'))->pluck('id')->all();
        $this->assertContains($linkedUser->id, $defaultIds);
        $this->assertContains($unlinkedUser->id, $defaultIds);

        // unlinked_only=1 filters out the linked user
        $filteredResponse = $this->getJson('/api/admin/users?search=مرشح&unlinked_only=1');
        $filteredResponse->assertOk();
        $filteredIds = collect($filteredResponse->json('data'))->pluck('id')->all();
        $this->assertNotContains($linkedUser->id, $filteredIds);
        $this->assertContains($unlinkedUser->id, $filteredIds);
    }

    // COVERS: D-04
    public function test_admin_users_endpoint_caps_limit_at_twenty(): void
    {
        User::factory()->count(25)->student()->create();

        $response = $this->getJson('/api/admin/users?limit=50');

        $response->assertOk();
        $this->assertLessThanOrEqual(20, count($response->json('data')));
        $this->assertEquals(20, $response->json('meta.per_page'));
    }

    // COVERS: D-04
    public function test_migration_rollback_works(): void
    {
        $this->assertTrue(Schema::hasColumn('instructors', 'user_id'));

        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2026_11_18_153000_add_user_id_to_instructors_table.php'])->assertSuccessful();

        $this->assertFalse(Schema::hasColumn('instructors', 'user_id'));

        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_18_153000_add_user_id_to_instructors_table.php'])->assertSuccessful();

        $this->assertTrue(Schema::hasColumn('instructors', 'user_id'));
    }

    // COVERS: D-04
    public function test_eloquent_model_helpers_and_relations_for_display_only_and_user(): void
    {
        $displayOnly = Instructor::create([
            'name' => 'مدرب حر',
            'user_id' => null,
        ]);

        $this->assertTrue($displayOnly->isDisplayOnly());
        $this->assertInstanceOf(BelongsTo::class, $displayOnly->user());
        $this->assertNull($displayOnly->user);

        $user = User::factory()->student()->create();
        $this->assertFalse($user->isInstructor());
        $this->assertInstanceOf(HasOne::class, $user->instructor());

        $linked = app(InstructorService::class)->create([
            'name' => 'مدرب معتمد',
            'user_id' => $user->id,
        ]);

        $this->assertFalse($linked->isDisplayOnly());
        $this->assertTrue($user->fresh()->isInstructor());
        $this->assertEquals($linked->id, $user->fresh()->instructor->id);
    }

    // COVERS: D-09
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

    // COVERS: D-14
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
