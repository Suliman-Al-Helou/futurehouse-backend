<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Instructor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructorLogicTraceTest extends TestCase
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
    }

    // EDGE: E-01
    // COVERS: D-01
    // VERDICT: SAME
    // CURRENT: unauthenticated request to POST /api/admin/instructors is rejected with 401
    // RECOMMENDED: 401 response per laravel.com/docs/11.x/sanctum
    // WHY: unauthenticated clients must never access admin resources
    // EVIDENCE: bootstrap/app.php:44 ; P-01
    public function test_trace_unauthenticated_request_is_rejected(): void
    {
        app('auth')->forgetGuards();
        $response = $this->postJson('/api/admin/instructors', ['name' => 'تجربة غير مصرح']);
        $response->assertStatus(401);
    }

    // EDGE: E-02
    // COVERS: D-02
    // VERDICT: SAME
    // CURRENT: authenticated student user is rejected with 403
    // RECOMMENDED: 403 forbidden per laravel.com/docs/11.x/authorization
    // WHY: admin endpoints must be strictly restricted to administrators
    // EVIDENCE: app/Policies/UserPolicy.php:14 ; P-02
    public function test_trace_student_request_is_rejected_with_403(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);
        $response = $this->postJson('/api/admin/instructors', ['name' => 'تجربة طالب']);
        $response->assertStatus(403);
    }

    // COVERS: D-03
    public function test_trace_admin_request_passes_authorization_gate(): void
    {
        Sanctum::actingAs($this->admin);
        $response = $this->postJson('/api/admin/instructors', ['name' => 'مدرب مسموح']);
        $response->assertCreated();
    }

    // EDGE: E-03
    // COVERS: D-04
    // VERDICT: SAME
    // CURRENT: valid student user linking creates profile and returns 201
    // RECOMMENDED: 201 created per laravel.com/docs/11.x/responses
    // WHY: linking an operational user profile establishes instructor capabilities
    // EVIDENCE: app/Http/Controllers/Api/Admin/InstructorController.php:57 ; P-04
    public function test_trace_linking_valid_user_creates_profile(): void
    {
        Sanctum::actingAs($this->admin);
        $user = User::factory()->student()->create();
        $response = $this->postJson('/api/admin/instructors', [
            'name' => 'المدرب أحمد',
            'user_id' => $user->id,
        ]);
        $response->assertCreated()
            ->assertJsonPath('user_id', $user->id);
    }

    // EDGE: E-04
    // COVERS: D-05
    // VERDICT: SAME
    // CURRENT: creating profile with user_id null creates display-only instructor and returns 201
    // RECOMMENDED: 201 created per laravel.com/docs/11.x/responses
    // WHY: marketing and guest instructor profiles do not require platform accounts
    // EVIDENCE: app/Models/Instructor.php:62 ; P-05
    public function test_trace_creating_display_only_profile(): void
    {
        Sanctum::actingAs($this->admin);
        $response = $this->postJson('/api/admin/instructors', [
            'name' => 'مدرب للعرض فقط',
            'user_id' => null,
        ]);
        $response->assertCreated()
            ->assertJsonPath('is_display_only', true);
    }

    // COVERS: D-06
    public function test_trace_empty_name_fails_validation(): void
    {
        Sanctum::actingAs($this->admin);
        $response = $this->postJson('/api/admin/instructors', ['name' => '']);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    // COVERS: D-07
    public function test_trace_non_integer_user_id_fails_validation(): void
    {
        Sanctum::actingAs($this->admin);
        $response = $this->postJson('/api/admin/instructors', ['name' => 'مدرب', 'user_id' => 'invalid']);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    // COVERS: D-08
    public function test_trace_non_existent_user_id_fails_validation(): void
    {
        Sanctum::actingAs($this->admin);
        $response = $this->postJson('/api/admin/instructors', ['name' => 'مدرب', 'user_id' => 999999]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    // EDGE: E-05
    // COVERS: D-09
    // VERDICT: SAME
    // CURRENT: linking soft-deleted user is rejected with 422
    // RECOMMENDED: 422 unprocessable entity per laravel.com/docs/11.x/validation
    // WHY: soft-deleted accounts must not be linked to active instructors
    // EVIDENCE: app/Http/Requests/InstructorRequest.php:28 ; P-09
    public function test_trace_linking_soft_deleted_user_fails(): void
    {
        Sanctum::actingAs($this->admin);
        $user = User::factory()->student()->create();
        $user->delete();
        $response = $this->postJson('/api/admin/instructors', ['name' => 'مدرب', 'user_id' => $user->id]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    // EDGE: E-06
    // COVERS: D-10
    // VERDICT: SAME
    // CURRENT: linking already linked user is rejected with 422
    // RECOMMENDED: 422 unprocessable entity per laravel.com/docs/11.x/validation
    // WHY: one user can only be linked to a single instructor profile
    // EVIDENCE: app/Http/Requests/InstructorRequest.php:28 ; P-10
    public function test_trace_linking_already_linked_user_fails(): void
    {
        Sanctum::actingAs($this->admin);
        $user = User::factory()->student()->create();
        Instructor::create(['name' => 'مدرب أول', 'user_id' => $user->id]);
        $response = $this->postJson('/api/admin/instructors', ['name' => 'مدرب ثان', 'user_id' => $user->id]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    // EDGE: E-07
    // COVERS: D-11
    // VERDICT: SAME
    // CURRENT: linking admin user is rejected with 422
    // RECOMMENDED: 422 unprocessable entity per laravel.com/docs/11.x/validation
    // WHY: admin accounts cannot be linked as instructors to preserve separation of duties
    // EVIDENCE: app/Http/Requests/InstructorRequest.php:28 ; P-11
    public function test_trace_linking_admin_user_fails(): void
    {
        Sanctum::actingAs($this->admin);
        $adminUser = User::factory()->admin()->create();
        $response = $this->postJson('/api/admin/instructors', ['name' => 'مدرب مسؤول', 'user_id' => $adminUser->id]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    // COVERS: D-12
    public function test_trace_linking_assigns_instructor_role(): void
    {
        Sanctum::actingAs($this->admin);
        $user = User::factory()->student()->create();
        $this->assertFalse($user->hasRole('instructor'));
        $this->postJson('/api/admin/instructors', ['name' => 'مدرب دور', 'user_id' => $user->id]);
        $this->assertTrue($user->fresh()->hasRole('instructor'));
    }

    // COVERS: D-13
    public function test_trace_display_only_skips_role_assignment(): void
    {
        Sanctum::actingAs($this->admin);
        $response = $this->postJson('/api/admin/instructors', ['name' => 'مدرب مستقل', 'user_id' => null]);
        $response->assertCreated();
        $this->assertNull($response->json('user_id'));
    }

    // COVERS: D-14
    public function test_trace_soft_deleting_user_nullifies_instructor_user_id(): void
    {
        $user = User::factory()->student()->create();
        $instructor = Instructor::create(['name' => 'مدرب حذف', 'user_id' => $user->id]);
        $user->delete();
        $this->assertNull($instructor->fresh()->user_id);
    }

    // COVERS: D-15
    public function test_trace_creating_instructor_clears_cache(): void
    {
        Sanctum::actingAs($this->admin);
        Cache::put('instructors:all', 'cached', 3600);
        $this->postJson('/api/admin/instructors', ['name' => 'مدرب كاش']);
        $this->assertFalse(Cache::has('instructors:all'));
    }

    // EDGE: E-08
    // COVERS: D-16
    // VERDICT: SAME
    // CURRENT: admin response exposes user_id and is_display_only with 201
    // RECOMMENDED: 201 response with admin fields per laravel.com/docs/11.x/eloquent-resources
    // WHY: administrators require full visibility of linked user identity
    // EVIDENCE: app/Http/Resources/InstructorResource.php:25 ; P-16
    public function test_trace_admin_response_exposes_user_id(): void
    {
        Sanctum::actingAs($this->admin);
        $response = $this->postJson('/api/admin/instructors', ['name' => 'مدرب كشف بيانات']);
        $response->assertCreated()
            ->assertJsonPath('is_display_only', true);
        $this->assertArrayHasKey('user_id', $response->json());
    }

    // EDGE: E-09
    // COVERS: D-17
    // VERDICT: SAME
    // CURRENT: public response excludes user_id with 200
    // RECOMMENDED: 200 response without internal identifiers per laravel.com/docs/11.x/eloquent-resources
    // WHY: internal user accounts must not be exposed to the public
    // EVIDENCE: app/Http/Resources/InstructorResource.php:25 ; P-17
    public function test_trace_public_response_excludes_user_id(): void
    {
        $instructor = Instructor::create(['name' => 'مدرب عام']);
        app('auth')->forgetGuards();
        $response = $this->getJson("/api/instructors/{$instructor->id}");
        $response->assertOk();
        $this->assertArrayNotHasKey('user_id', $response->json());
    }
}
