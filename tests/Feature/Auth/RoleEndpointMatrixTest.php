<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Faq;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use App\Models\VideoAsset;
use App\Models\ZoomMeeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleEndpointMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Complete matrix of all 78 registered API routes.
     * Format: [Method, Route URI, Expected Guest Status, Expected Student Status, Admin Accessible]
     *
     * @return array<string, array{0: string, 1: string, 2: int, 3: int, 4: bool}>
     */
    public static function routeMatrixProvider(): array
    {
        return [
            // ─── Health & Public Content ──────────────────────────────────
            'GET api/health' => ['GET', 'api/health', 200, 200, true],
            'GET api/courses' => ['GET', 'api/courses', 200, 200, true],
            'GET api/courses/{course}' => ['GET', 'api/courses/{course}', 200, 200, true],
            'GET api/instructors' => ['GET', 'api/instructors', 200, 200, true],
            'GET api/instructors/{id}' => ['GET', 'api/instructors/{id}', 200, 200, true],
            'GET api/faqs' => ['GET', 'api/faqs', 200, 200, true],

            // ─── Public Auth ──────────────────────────────────────────────
            'POST api/auth/forgot-password' => ['POST', 'api/auth/forgot-password', 422, 422, true],
            'POST api/auth/reset-password' => ['POST', 'api/auth/reset-password', 422, 422, true],
            'POST api/auth/register' => ['POST', 'api/auth/register', 422, 422, true],
            'POST api/auth/login' => ['POST', 'api/auth/login', 422, 422, true],
            'POST api/auth/google' => ['POST', 'api/auth/google', 422, 422, true],

            // ─── Student Protected Endpoints ──────────────────────────────
            'GET api/auth/me' => ['GET', 'api/auth/me', 401, 200, true],
            'POST api/auth/logout' => ['POST', 'api/auth/logout', 401, 200, true],
            'GET api/dashboard/stats' => ['GET', 'api/dashboard/stats', 401, 200, true],
            'PUT api/users/{user}' => ['PUT', 'api/users/{user}', 401, 200, true],
            'POST api/courses/{course}/enroll' => ['POST', 'api/courses/{course}/enroll', 401, 200, true],
            'DELETE api/courses/{course}/enroll' => ['DELETE', 'api/courses/{course}/enroll', 401, 200, true],
            'GET api/courses/{course}/enrollment' => ['GET', 'api/courses/{course}/enrollment', 401, 200, true],
            'GET api/my-courses' => ['GET', 'api/my-courses', 401, 200, true],
            'GET api/lessons/in-progress' => ['GET', 'api/lessons/in-progress', 401, 200, true],
            'GET api/lessons/{id}/progress' => ['GET', 'api/lessons/{id}/progress', 401, 200, true],
            'POST api/lessons/{id}/progress' => ['POST', 'api/lessons/{id}/progress', 401, 200, true],
            'GET api/courses/{course}/progress-summary' => ['GET', 'api/courses/{course}/progress-summary', 401, 200, true],
            'GET api/lessons/{lesson}/task' => ['GET', 'api/lessons/{lesson}/task', 401, 200, true],
            'POST api/lessons/{lesson}/task' => ['POST', 'api/lessons/{lesson}/task', 401, 422, true],
            'GET api/lessons/{lesson}' => ['GET', 'api/lessons/{lesson}', 401, 200, true],
            'GET api/zoom-meetings' => ['GET', 'api/zoom-meetings', 401, 200, true],
            'GET api/zoom-meetings/attendance' => ['GET', 'api/zoom-meetings/attendance', 401, 200, true],
            'GET api/zoom-meetings/attendance-summary' => ['GET', 'api/zoom-meetings/attendance-summary', 401, 200, true],
            'POST api/zoom-meetings/{zoomMeeting}/attend' => ['POST', 'api/zoom-meetings/{zoomMeeting}/attend', 401, 422, true],

            // ─── Admin Endpoints (Must be 401 Guest, 403 Student, 200/201/204/422 Admin) ─
            'GET api/admin/stats' => ['GET', 'api/admin/stats', 401, 403, true],
            'GET api/admin/users' => ['GET', 'api/admin/users', 401, 403, true],
            'GET api/admin/students' => ['GET', 'api/admin/students', 401, 403, true],
            'DELETE api/admin/users/{user}' => ['DELETE', 'api/admin/users/{user}', 401, 403, true],
            'PATCH api/admin/users/{user}' => ['PATCH', 'api/admin/users/{user}', 401, 403, true],
            'GET api/admin/enrollments' => ['GET', 'api/admin/enrollments', 401, 403, true],
            'POST api/admin/enrollments' => ['POST', 'api/admin/enrollments', 401, 403, true],
            'PATCH api/admin/enrollments/{enrollment}' => ['PATCH', 'api/admin/enrollments/{enrollment}', 401, 403, true],
            'GET api/admin/courses' => ['GET', 'api/admin/courses', 401, 403, true],
            'POST api/admin/courses' => ['POST', 'api/admin/courses', 401, 403, true],
            'GET api/admin/courses/{course}' => ['GET', 'api/admin/courses/{course}', 401, 403, true],
            'PUT api/admin/courses/{course}' => ['PUT', 'api/admin/courses/{course}', 401, 403, true],
            'DELETE api/admin/courses/{course}' => ['DELETE', 'api/admin/courses/{course}', 401, 403, true],
            'GET api/admin/courses/{course}/sections' => ['GET', 'api/admin/courses/{course}/sections', 401, 403, true],
            'POST api/admin/courses/{course}/sections' => ['POST', 'api/admin/courses/{course}/sections', 401, 403, true],
            'DELETE api/admin/sections/{section}' => ['DELETE', 'api/admin/sections/{section}', 401, 403, true],
            'POST api/admin/sections/{section}/lessons' => ['POST', 'api/admin/sections/{section}/lessons', 401, 403, true],
            'PUT api/admin/lessons/{lesson}' => ['PUT', 'api/admin/lessons/{lesson}', 401, 403, true],
            'DELETE api/admin/lessons/{lesson}' => ['DELETE', 'api/admin/lessons/{lesson}', 401, 403, true],
            'GET api/admin/lessons/{lesson}/task' => ['GET', 'api/admin/lessons/{lesson}/task', 401, 403, true],
            'POST api/admin/lessons/{lesson}/task' => ['POST', 'api/admin/lessons/{lesson}/task', 401, 403, true],
            'PUT api/admin/lessons/{lesson}/task' => ['PUT', 'api/admin/lessons/{lesson}/task', 401, 403, true],
            'GET api/admin/instructors' => ['GET', 'api/admin/instructors', 401, 403, true],
            'POST api/admin/instructors' => ['POST', 'api/admin/instructors', 401, 403, true],
            'GET api/admin/instructors/{id}' => ['GET', 'api/admin/instructors/{id}', 401, 403, true],
            'PUT api/admin/instructors/{instructor}' => ['PUT', 'api/admin/instructors/{instructor}', 401, 403, true],
            'DELETE api/admin/instructors/{instructor}' => ['DELETE', 'api/admin/instructors/{instructor}', 401, 403, true],
            'GET api/admin/faqs' => ['GET', 'api/admin/faqs', 401, 403, true],
            'POST api/admin/faqs' => ['POST', 'api/admin/faqs', 401, 403, true],
            'PUT api/admin/faqs/{faq}' => ['PUT', 'api/admin/faqs/{faq}', 401, 403, true],
            'DELETE api/admin/faqs/{faq}' => ['DELETE', 'api/admin/faqs/{faq}', 401, 403, true],
            'GET api/admin/zoom-meetings' => ['GET', 'api/admin/zoom-meetings', 401, 403, true],
            'POST api/admin/zoom-meetings' => ['POST', 'api/admin/zoom-meetings', 401, 403, true],
            'PUT api/admin/zoom-meetings/{zoomMeeting}' => ['PUT', 'api/admin/zoom-meetings/{zoomMeeting}', 401, 403, true],
            'DELETE api/admin/zoom-meetings/{zoomMeeting}' => ['DELETE', 'api/admin/zoom-meetings/{zoomMeeting}', 401, 403, true],
            'GET api/admin/zoom-meetings/{zoomMeeting}/attendances' => ['GET', 'api/admin/zoom-meetings/{zoomMeeting}/attendances', 401, 403, true],
            'GET api/admin/zoom-meetings/{zoomMeeting}/roster' => ['GET', 'api/admin/zoom-meetings/{zoomMeeting}/roster', 401, 403, true],
            'PUT api/admin/zoom-meetings/{zoomMeeting}/attendances/{user}' => ['PUT', 'api/admin/zoom-meetings/{zoomMeeting}/attendances/{user}', 401, 403, true],
            'GET api/admin/video-assets' => ['GET', 'api/admin/video-assets', 401, 403, true],
            'POST api/admin/video-assets' => ['POST', 'api/admin/video-assets', 401, 403, true],
            'POST api/admin/video-assets/upload-chunk' => ['POST', 'api/admin/video-assets/upload-chunk', 401, 403, true],
            'GET api/admin/video-assets/{videoAsset}' => ['GET', 'api/admin/video-assets/{videoAsset}', 401, 403, true],
            'PUT api/admin/video-assets/{videoAsset}' => ['PUT', 'api/admin/video-assets/{videoAsset}', 401, 403, true],
            'DELETE api/admin/video-assets/{videoAsset}' => ['DELETE', 'api/admin/video-assets/{videoAsset}', 401, 403, true],
            'GET api/admin/youtube/status' => ['GET', 'api/admin/youtube/status', 401, 403, true],
            'GET api/admin/youtube/auth-url' => ['GET', 'api/admin/youtube/auth-url', 401, 403, true],
            'POST api/admin/youtube/callback' => ['POST', 'api/admin/youtube/callback', 401, 403, true],
            'POST api/admin/youtube/disconnect' => ['POST', 'api/admin/youtube/disconnect', 401, 403, true],
        ];
    }

    /**
     * Architectural test: asserts that every route registered under `api/` is covered in the matrix dataset.
     */
    public function test_architectural_every_api_route_is_covered_in_the_matrix_dataset(): void
    {
        $matrixRoutes = array_keys(self::routeMatrixProvider());
        /** @var iterable<\Illuminate\Routing\Route> $routes */
        $routes = Route::getRoutes()->getRoutes();

        $registeredApiRoutes = [];
        foreach ($routes as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'api/')) {
                $methods = array_diff($route->methods(), ['HEAD']);
                foreach ($methods as $method) {
                    $key = "{$method} {$uri}";
                    $registeredApiRoutes[] = $key;
                    $this->assertContains(
                        $key,
                        $matrixRoutes,
                        "Route [{$key}] is registered in Laravel router but missing from Role × Endpoint Matrix dataset!"
                    );
                }
            }
        }

        // Assert exactly matching count
        $this->assertCount(
            count($registeredApiRoutes),
            $matrixRoutes,
            'The matrix dataset contains extra routes that are not registered in the Laravel API router.'
        );
    }

    /**
     * Role × Endpoint Matrix Test:
     * Verifies that:
     * - Guest access complies with expected public/401 status.
     * - Student access strictly receives 403 Forbidden on all Admin routes.
     * - Admin access is never blocked by 401 Unauthorized or 403 Forbidden.
     */
    #[DataProvider('routeMatrixProvider')]
    public function test_role_endpoint_matrix(
        string $method,
        string $uri,
        int $expectedGuestStatus,
        int $expectedStudentStatus,
        bool $adminAccessible
    ): void {
        $fixtures = $this->createFixtures();
        $resolvedUri = $this->resolveUri($uri, $fixtures);

        // 1. ─── GUEST ATTEMPT ───
        $guestResponse = $this->json($method, $resolvedUri);
        $this->assertSame(
            $expectedGuestStatus,
            $guestResponse->status(),
            "Guest on [{$method} {$uri}] received status {$guestResponse->status()}, expected {$expectedGuestStatus}."
        );

        // 2. ─── STUDENT ATTEMPT ───
        Sanctum::actingAs($fixtures['student'], ['*']);
        $studentResponse = $this->json($method, $resolvedUri);
        if ($expectedStudentStatus === 403) {
            $this->assertSame(
                403,
                $studentResponse->status(),
                "BFLA VULNERABILITY: Student on [{$method} {$uri}] received status {$studentResponse->status()}, expected 403 Forbidden."
            );
        } else {
            // Student is authorized on student/public endpoints (never 403 or 401)
            $this->assertNotSame(
                401,
                $studentResponse->status(),
                "Student on [{$method} {$uri}] received unexpected 401 Unauthorized."
            );
            $this->assertNotSame(
                403,
                $studentResponse->status(),
                "Student on [{$method} {$uri}] received unexpected 403 Forbidden."
            );
        }

        // 3. ─── ADMIN ATTEMPT ───
        Sanctum::actingAs($fixtures['admin'], ['*']);
        $adminResponse = $this->json($method, $resolvedUri);
        $this->assertNotSame(
            401,
            $adminResponse->status(),
            "Admin on [{$method} {$uri}] was unexpectedly rejected with 401 Unauthorized."
        );
        $this->assertNotSame(
            403,
            $adminResponse->status(),
            "Admin on [{$method} {$uri}] was unexpectedly blocked with 403 Forbidden."
        );
    }

    private function createFixtures(): array
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $instructor = Instructor::create([
            'name' => 'Instructor Test',
            'title' => 'Engineer',
            'bio' => 'Bio',
        ]);
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'is_public' => true,
        ]);

        $section = Section::factory()->create([
            'course_id' => $course->id,
        ]);

        $lesson = Lesson::factory()->create([
            'section_id' => $section->id,
        ]);

        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'approved',
        ]);

        $faq = Faq::create([
            'question' => 'Question',
            'answer' => 'Answer',
            'category' => 'general',
            'order' => 1,
            'is_active' => true,
        ]);

        $meeting = ZoomMeeting::factory()->create([
            'course_id' => $course->id,
            'starts_at' => now()->subMinutes(5),
            'duration_minutes' => 60,
        ]);

        $unenrolledCourse = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'is_public' => true,
        ]);

        $videoAsset = VideoAsset::factory()->create();

        return [
            'admin' => $admin,
            'student' => $student,
            'course' => $course,
            'unenrolledCourse' => $unenrolledCourse,
            'section' => $section,
            'lesson' => $lesson,
            'enrollment' => $enrollment,
            'faq' => $faq,
            'instructor' => $instructor,
            'zoomMeeting' => $meeting,
            'videoAsset' => $videoAsset,
        ];
    }

    private function resolveUri(string $uri, array $f): string
    {
        if ($uri === 'api/courses/{course}/enroll') {
            $courseId = (string) $f['unenrolledCourse']->id;
        } else {
            $courseId = (string) $f['course']->id;
        }

        $map = [
            '{course}' => $courseId,
            '{section}' => (string) $f['section']->id,
            '{lesson}' => (string) $f['lesson']->id,
            '{enrollment}' => (string) $f['enrollment']->id,
            '{faq}' => (string) $f['faq']->id,
            '{instructor}' => (string) $f['instructor']->id,
            '{zoomMeeting}' => (string) $f['zoomMeeting']->id,
            '{videoAsset}' => (string) $f['videoAsset']->id,
            '{user}' => (string) $f['student']->id,
        ];

        if (str_contains($uri, 'api/instructors/{id}')) {
            $uri = str_replace('{id}', (string) $f['instructor']->id, $uri);
        } elseif (str_contains($uri, 'api/lessons/{id}/progress')) {
            $uri = str_replace('{id}', (string) $f['lesson']->id, $uri);
        }

        return strtr($uri, $map);
    }
}
