<?php

declare(strict_types=1);

namespace Tests\Feature\Instructor;

use App\Models\Course;
use App\Models\CourseActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructorCourseMetadataUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $instructorA;

    private User $instructorB;

    private User $admin;

    private Course $courseA1;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('instructor', 'web');
        Role::findOrCreate('student', 'web');

        $this->admin = User::factory()->admin()->create();

        // Instructor A
        $this->instructorA = User::factory()->create(['name' => 'المدرب سامر']);
        $this->instructorA->assignRole('instructor');
        $profileA = $this->instructorA->instructor;

        // Instructor B
        $this->instructorB = User::factory()->create(['name' => 'المدرب كمال']);
        $this->instructorB->assignRole('instructor');

        // Course assigned to Instructor A
        $this->courseA1 = Course::factory()->create([
            'instructor_id' => $profileA->id,
            'title' => 'كورس الدوكير المتقدم',
            'description' => 'دورة شاملة في بناء وإدارة حاويات الدوكير الاحترافية',
            'level' => 'intermediate',
            'what_you_learn' => ['أساسيات الدوكير', 'Docker Compose'],
            'requirements' => ['معرفة مسبقة بنظام لينكس'],
            'target_audience' => ['مهندسو الديف أوبس'],
            'price' => 199.99,
            'status' => 'published',
            'is_public' => true,
            'is_popular' => false,
            'rating' => 4.5,
            'youtube_playlist_id' => 'PL_ORIGINAL_DOCKER_123',
            'cover_image' => 'https://example.com/initial-cover.jpg',
        ]);
    }

    public function test_unauthenticated_user_cannot_update_course_metadata(): void
    {
        app('auth')->forgetGuards();

        $response = $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'title' => 'محاولة اختراق غير مصرح بها',
        ]);

        $response->assertStatus(401);
    }

    public function test_instructor_cannot_update_another_instructors_course_idor_guard(): void
    {
        Sanctum::actingAs($this->instructorB);

        $response = $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'title' => 'محاولة تعديل كورس مدرب آخر',
        ]);

        $response->assertStatus(403);
    }

    public function test_instructor_can_successfully_update_educational_metadata_and_diff_is_logged(): void
    {
        Sanctum::actingAs($this->instructorA);

        $payload = [
            'title' => 'كورس الدوكير وهندسة الحاويات المتقدمة 2026',
            'description' => 'دورة محدثة بالكامل ومكثفة تشمل أحدث ممارسات دوكير وكوبيرنتس',
            'level' => 'advanced',
            'what_you_learn' => ['أساسيات الدوكير', 'Docker Compose', 'Kubernetes Architecture'],
            'requirements' => ['معرفة ممتازة بلينكس', 'أساسيات الشبكات'],
            'target_audience' => ['مهندسو النظم والديف أوبس'],
        ];

        $response = $this->putJson("/api/instructor/courses/{$this->courseA1->id}", $payload);

        $response->assertStatus(200);

        // Verify Course Table in Database
        $this->assertDatabaseHas('courses', [
            'id' => $this->courseA1->id,
            'title' => 'كورس الدوكير وهندسة الحاويات المتقدمة 2026',
            'description' => 'دورة محدثة بالكامل ومكثفة تشمل أحدث ممارسات دوكير وكوبيرنتس',
            'level' => 'advanced',
        ]);

        // Verify Activity Log in Database
        $this->assertDatabaseHas('course_activity_logs', [
            'course_id' => $this->courseA1->id,
            'user_id' => $this->instructorA->id,
            'action' => 'course_metadata_updated',
        ]);

        $log = CourseActivityLog::where('course_id', $this->courseA1->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertIsArray($log->changes);

        // Assert exact diff
        $this->assertEquals('كورس الدوكير المتقدم', $log->changes['title']['old']);
        $this->assertEquals('كورس الدوكير وهندسة الحاويات المتقدمة 2026', $log->changes['title']['new']);
        $this->assertEquals('intermediate', $log->changes['level']['old']);
        $this->assertEquals('advanced', $log->changes['level']['new']);
    }

    public function test_silent_stripping_guarantees_unwhitelisted_attributes_are_never_tampered(): void
    {
        Sanctum::actingAs($this->instructorA);

        $payload = [
            'title' => 'كورس الدوكير المحدث شرعياً',
            // Tamper attempt with unwhitelisted parameters
            'is_popular' => true,
            'rating' => 5.0,
            'students_count' => 9999,
        ];

        $response = $this->putJson("/api/instructor/courses/{$this->courseA1->id}", $payload);

        // Silent stripping returns 200 OK so attacker receives no probing telemetry
        $response->assertStatus(200);

        $freshCourse = $this->courseA1->fresh();

        // Educational attribute was updated
        $this->assertEquals('كورس الدوكير المحدث شرعياً', $freshCourse->title);

        // Unwhitelisted attributes remain strictly unchanged in the DB
        $this->assertFalse((bool) $freshCourse->is_popular);
        $this->assertEquals(4.5, (float) $freshCourse->rating);

        // Audit log only records the legitimate title change
        $log = CourseActivityLog::where('course_id', $this->courseA1->id)->first();
        $this->assertNotNull($log);
        $this->assertArrayHasKey('title', $log->changes);
        $this->assertArrayNotHasKey('is_popular', $log->changes);
        $this->assertArrayNotHasKey('rating', $log->changes);
        $this->assertArrayNotHasKey('students_count', $log->changes);
    }

    public function test_prohibited_commercial_and_administrative_attributes_return_422(): void
    {
        Sanctum::actingAs($this->instructorA);

        $response = $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'price' => 0.00,
            'status' => 'draft',
            'is_public' => false,
            'instructor_id' => 9999,
            'youtube_playlist_id' => 'PL_ATTACKER_INJECTED',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'price',
                'status',
                'is_public',
                'instructor_id',
                'youtube_playlist_id',
            ]);
    }

    public function test_submitting_identical_metadata_does_not_create_redundant_activity_log(): void
    {
        Sanctum::actingAs($this->instructorA);

        $payload = [
            'title' => $this->courseA1->title,
            'description' => $this->courseA1->description,
            'level' => $this->courseA1->level,
            'what_you_learn' => $this->courseA1->what_you_learn,
            'requirements' => $this->courseA1->requirements,
            'target_audience' => $this->courseA1->target_audience,
        ];

        $response = $this->putJson("/api/instructor/courses/{$this->courseA1->id}", $payload);

        $response->assertStatus(200);

        // No redundant log created
        $this->assertEquals(0, CourseActivityLog::where('course_id', $this->courseA1->id)->count());
    }

    public function test_instructor_can_upload_new_cover_image_file(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->instructorA);

        $file = UploadedFile::fake()->image('custom_cover.png', 640, 480);

        $response = $this->postJson("/api/instructor/courses/{$this->courseA1->id}", [
            '_method' => 'PUT',
            'cover_image' => $file,
        ]);

        $response->assertStatus(200);

        $freshCourse = $this->courseA1->fresh();
        $this->assertNotNull($freshCourse->cover_image);
        $this->assertStringContainsString('courses/covers/', $freshCourse->cover_image);

        // Verify stored in fake storage
        $storedPath = str_replace('/storage/', '', parse_url($freshCourse->cover_image, PHP_URL_PATH) ?? $freshCourse->cover_image);
        Storage::disk('public')->assertExists($storedPath);

        // Verify logged in activity log
        $log = CourseActivityLog::where('course_id', $this->courseA1->id)->first();
        $this->assertNotNull($log);
        $this->assertArrayHasKey('cover_image', $log->changes);
    }

    public function test_soft_deleting_user_preserves_activity_logs_with_user_id_null(): void
    {
        Sanctum::actingAs($this->instructorA);

        $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'title' => 'تحديث قبل الحذف',
        ])->assertStatus(200);

        $this->assertDatabaseHas('course_activity_logs', [
            'course_id' => $this->courseA1->id,
            'user_id' => $this->instructorA->id,
        ]);

        // Soft-delete the instructor user
        $this->instructorA->delete();

        // Forensic record remains preserved with user_id = null (nullOnDelete)
        $this->assertDatabaseHas('course_activity_logs', [
            'course_id' => $this->courseA1->id,
            'user_id' => null,
        ]);
    }

    public function test_supervising_admin_can_update_course_metadata_and_is_logged_as_acting_user(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'title' => 'تحديث إداري بإشراف الإدارة العامة',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('course_activity_logs', [
            'course_id' => $this->courseA1->id,
            'user_id' => $this->admin->id,
            'action' => 'course_metadata_updated',
        ]);
    }

    public function test_validation_rules_fail_on_invalid_data(): void
    {
        Sanctum::actingAs($this->instructorA);

        // Title too short (< 3)
        $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'title' => 'أب',
        ])->assertStatus(422)->assertJsonValidationErrors(['title']);

        // Description too short (< 10)
        $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'description' => 'قصير جدا',
        ])->assertStatus(422)->assertJsonValidationErrors(['description']);

        // Invalid level
        $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'level' => 'legendary',
        ])->assertStatus(422)->assertJsonValidationErrors(['level']);

        // Non-image file upload
        $file = UploadedFile::fake()->create('document.pdf', 100);
        $this->postJson("/api/instructor/courses/{$this->courseA1->id}", [
            '_method' => 'PUT',
            'cover_image' => $file,
        ])->assertStatus(422)->assertJsonValidationErrors(['cover_image']);

        // Image file exceeding 2MB
        $largeFile = UploadedFile::fake()->image('oversized.jpg')->size(3000);
        $this->postJson("/api/instructor/courses/{$this->courseA1->id}", [
            '_method' => 'PUT',
            'cover_image' => $largeFile,
        ])->assertStatus(422)->assertJsonValidationErrors(['cover_image']);

        // Unbounded array items (> 30)
        $excessiveArray = array_fill(0, 31, 'مخرج تعليمي اختباري');
        $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'what_you_learn' => $excessiveArray,
        ])->assertStatus(422)->assertJsonValidationErrors(['what_you_learn']);

        // Description exceeding 5000 chars
        $this->putJson("/api/instructor/courses/{$this->courseA1->id}", [
            'description' => str_repeat('أ', 5001),
        ])->assertStatus(422)->assertJsonValidationErrors(['description']);
    }

    public function test_old_cover_image_file_is_pruned_when_new_image_is_uploaded(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->instructorA);

        // Upload first image
        $firstFile = UploadedFile::fake()->image('first_cover.jpg', 600, 400);
        $this->postJson("/api/instructor/courses/{$this->courseA1->id}", [
            '_method' => 'PUT',
            'cover_image' => $firstFile,
        ])->assertStatus(200);

        $firstStoredUrl = $this->courseA1->fresh()->cover_image;
        $firstStoredPath = str_replace('/storage/', '', parse_url($firstStoredUrl, PHP_URL_PATH) ?? $firstStoredUrl);
        Storage::disk('public')->assertExists($firstStoredPath);

        // Upload second image replacing the first
        $secondFile = UploadedFile::fake()->image('second_cover.png', 800, 600);
        $this->postJson("/api/instructor/courses/{$this->courseA1->id}", [
            '_method' => 'PUT',
            'cover_image' => $secondFile,
        ])->assertStatus(200);

        $secondStoredUrl = $this->courseA1->fresh()->cover_image;
        $secondStoredPath = str_replace('/storage/', '', parse_url($secondStoredUrl, PHP_URL_PATH) ?? $secondStoredUrl);

        // Second image exists
        Storage::disk('public')->assertExists($secondStoredPath);

        // First image was pruned from disk
        Storage::disk('public')->assertMissing($firstStoredPath);
    }
}
