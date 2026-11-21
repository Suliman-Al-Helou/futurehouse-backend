<?php

declare(strict_types=1);

namespace Tests\Feature\Instructor;

use App\Models\Instructor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructorProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $instructorUser;
    private Instructor $instructorProfile;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('instructor', 'web');
        Role::findOrCreate('student', 'web');

        $this->instructorUser = User::factory()->create([
            'name' => 'الأستاذ عبد الرحمن',
            'email' => 'instructor@academy.test',
        ]);
        $this->instructorUser->assignRole('instructor');

        // Retrieve the bootstrapped instructor and populate profile attributes for testing
        $this->instructorProfile = Instructor::where('user_id', $this->instructorUser->id)->first()
            ?? Instructor::create([
                'user_id' => $this->instructorUser->id,
                'name' => $this->instructorUser->name,
            ]);

        $this->instructorProfile->update([
            'title' => 'كبير مهندسي البرمجيات',
            'bio' => 'مدرب متخصص في هندسة النظم وتطوير الويب الكامل.',
            'specializations' => 'Laravel, Vue.js, PostgreSQL',
            'years_experience' => 8,
            'rating' => 4.9,
            'total_reviews' => 45,
            'students_count' => 320,
            'achievements' => ['شهادة مدرب معتمد', 'مؤلف كتب تقنية'],
            'twitter' => 'https://twitter.com/instructor',
            'linkedin' => 'https://linkedin.com/in/instructor',
            'youtube' => 'https://youtube.com/@instructor',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_profile_endpoint(): void
    {
        app('auth')->forgetGuards();

        $response = $this->getJson('/api/instructor/profile');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_update_profile_endpoint(): void
    {
        app('auth')->forgetGuards();

        $response = $this->putJson('/api/instructor/profile', [
            'bio' => 'تحديث غير مصرح به',
        ]);

        $response->assertStatus(401);
    }

    public function test_student_cannot_access_profile_endpoint(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/instructor/profile');

        $response->assertStatus(403);
    }

    public function test_student_cannot_update_profile_endpoint(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $response = $this->putJson('/api/instructor/profile', [
            'bio' => 'محاولة طالب لتعديل البروفايل',
        ]);

        $response->assertStatus(403);
    }

    public function test_skeleton_profile_is_automatically_bootstrapped_when_user_receives_instructor_role(): void
    {
        $candidate = User::factory()->student()->create([
            'name' => 'المهندس الجديد خالد',
        ]);

        $this->assertDatabaseMissing('instructors', [
            'user_id' => $candidate->id,
        ]);

        $candidate->assignRole('instructor');

        $this->assertDatabaseHas('instructors', [
            'user_id' => $candidate->id,
            'name' => 'المهندس الجديد خالد',
        ]);
    }

    public function test_skeleton_profile_is_bootstrapped_via_admin_role_update_endpoint(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $targetUser = User::factory()->student()->create([
            'name' => 'المطور سامي',
        ]);

        $this->assertDatabaseMissing('instructors', [
            'user_id' => $targetUser->id,
        ]);

        $response = $this->patchJson("/api/admin/users/{$targetUser->id}", [
            'role' => 'instructor',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('instructors', [
            'user_id' => $targetUser->id,
            'name' => 'المطور سامي',
        ]);
    }

    public function test_instructor_can_fetch_own_profile_with_read_only_metrics(): void
    {
        Sanctum::actingAs($this->instructorUser);

        $response = $this->getJson('/api/instructor/profile');

        $response->assertOk()
            ->assertJsonPath('name', $this->instructorUser->name)
            ->assertJsonPath('title', 'كبير مهندسي البرمجيات')
            ->assertJsonPath('bio', 'مدرب متخصص في هندسة النظم وتطوير الويب الكامل.')
            ->assertJsonPath('rating', 4.9)
            ->assertJsonPath('total_reviews', 45)
            ->assertJsonPath('students_count', 320)
            ->assertJsonPath('years_experience', 8)
            ->assertJsonPath('twitter', 'https://twitter.com/instructor')
            ->assertJsonPath('user.email', $this->instructorUser->email);
    }

    public function test_instructor_can_update_biographical_fields(): void
    {
        Sanctum::actingAs($this->instructorUser);

        $payload = [
            'title' => 'رئيس قسم التقنية والأمن السيبراني',
            'bio' => 'سيرة ذاتية محدثة تغطي خبرات الحوسبة السحابية.',
            'specializations' => 'Laravel, Docker, Kubernetes',
            'years_experience' => 12,
            'twitter' => 'https://twitter.com/updated_inst',
            'linkedin' => 'https://linkedin.com/in/updated_inst',
            'youtube' => 'https://youtube.com/@updated_inst',
            'achievements' => ['AWS Solutions Architect Professional'],
        ];

        $response = $this->putJson('/api/instructor/profile', $payload);

        $response->assertOk()
            ->assertJsonPath('title', $payload['title'])
            ->assertJsonPath('bio', $payload['bio'])
            ->assertJsonPath('years_experience', 12);

        $this->assertDatabaseHas('instructors', [
            'id' => $this->instructorProfile->id,
            'user_id' => $this->instructorUser->id,
            'title' => $payload['title'],
            'years_experience' => 12,
        ]);
    }

    public function test_updating_instructor_name_atomically_updates_user_account_name(): void
    {
        Sanctum::actingAs($this->instructorUser);

        $payload = [
            'name' => 'الدكتور عبد الرحمن المحدث',
        ];

        $response = $this->putJson('/api/instructor/profile', $payload);

        $response->assertOk();

        $this->assertEquals('الدكتور عبد الرحمن المحدث', $this->instructorProfile->fresh()->name);
        $this->assertEquals('الدكتور عبد الرحمن المحدث', $this->instructorUser->fresh()->name);

        $this->assertDatabaseHas('instructors', [
            'id' => $this->instructorProfile->id,
            'name' => 'الدكتور عبد الرحمن المحدث',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $this->instructorUser->id,
            'name' => 'الدكتور عبد الرحمن المحدث',
        ]);
    }

    public function test_updating_rating_is_prohibited_and_returns_422(): void
    {
        Sanctum::actingAs($this->instructorUser);

        $response = $this->putJson('/api/instructor/profile', [
            'rating' => 5.0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('rating');
    }

    public function test_updating_total_reviews_is_prohibited_and_returns_422(): void
    {
        Sanctum::actingAs($this->instructorUser);

        $response = $this->putJson('/api/instructor/profile', [
            'total_reviews' => 999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('total_reviews');
    }

    public function test_updating_students_count_is_prohibited_and_returns_422(): void
    {
        Sanctum::actingAs($this->instructorUser);

        $response = $this->putJson('/api/instructor/profile', [
            'students_count' => 10000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('students_count');
    }

    public function test_updating_user_id_is_prohibited_and_returns_422(): void
    {
        Sanctum::actingAs($this->instructorUser);

        $otherUser = User::factory()->create();

        $response = $this->putJson('/api/instructor/profile', [
            'user_id' => $otherUser->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    public function test_tampering_multiple_prohibited_fields_at_once_fails_with_422(): void
    {
        Sanctum::actingAs($this->instructorUser);

        $response = $this->putJson('/api/instructor/profile', [
            'rating' => 5.0,
            'total_reviews' => 100,
            'students_count' => 500,
            'user_id' => 99,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating', 'total_reviews', 'students_count', 'user_id']);
    }
}
