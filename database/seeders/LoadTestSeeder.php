<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use App\Models\ZoomMeeting;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Seeds realistic data for k6 load testing (Phase 5).
 *
 * Creates:
 * - 120 students with pre-generated Sanctum tokens
 * - 20 published courses with sections & lessons
 * - Enrollments (each student in 2-5 courses, status=approved)
 * - Lesson progress (varied completion)
 * - Active ZoomMeetings with canJoin()=true for attendance testing
 *
 * Outputs token file: tests/K6/tokens.json
 */
class LoadTestSeeder extends Seeder
{
    private const STUDENT_COUNT = 120;

    private const COURSE_COUNT = 20;

    private const SECTIONS_PER_COURSE = 3;

    private const LESSONS_PER_SECTION = 4;

    public function run(): void
    {
        $this->command->info('🚀 Phase 5: Seeding load test data...');

        DB::statement('SET session_replication_role = replica;');

        $role = Role::findOrCreate('student', 'web');
        $adminRole = Role::findOrCreate('admin', 'web');

        // ─── 1. Create Students ─────────────────────────────────
        $this->command->info('👥 Creating '.self::STUDENT_COUNT.' students...');
        $hashedPassword = Hash::make('loadtest123');
        $tokens = [];

        for ($i = 1; $i <= self::STUDENT_COUNT; $i++) {
            $user = User::create([
                'name' => "Student {$i}",
                'email' => "loadtest{$i}@test.com",
                'password' => $hashedPassword,
                'email_verified_at' => now(),
            ]);
            $user->assignRole($role);

            // Pre-generate Sanctum token (avoids login bottleneck in k6)
            $token = $user->createToken('k6-load-test')->plainTextToken;
            $tokens[] = [
                'user_id' => $user->id,
                'token' => $token,
            ];
        }

        // Create one admin for potential admin-endpoint tests
        $admin = User::create([
            'name' => 'Admin LoadTest',
            'email' => 'admin-loadtest@test.com',
            'password' => $hashedPassword,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole($adminRole);
        $adminToken = $admin->createToken('k6-admin')->plainTextToken;

        // ─── 2. Create Courses with Sections & Lessons ──────────
        $this->command->info('📚 Creating '.self::COURSE_COUNT.' courses...');
        $allLessonIds = [];
        $courseIds = [];

        for ($c = 1; $c <= self::COURSE_COUNT; $c++) {
            $course = Course::create([
                'title' => "Load Test Course {$c}",
                'description' => "Description for load test course {$c}. This is a comprehensive course for testing.",
                'level' => ['beginner', 'intermediate', 'advanced'][$c % 3],
                'status' => 'published',
                'total_duration' => rand(7200, 36000),
                'what_you_learn' => ['Skill A', 'Skill B', 'Skill C'],
                'is_public' => $c <= 5,   // first 5 are free
                'price' => $c <= 5 ? 0 : rand(50, 200),
            ]);
            $courseIds[] = $course->id;

            for ($s = 1; $s <= self::SECTIONS_PER_COURSE; $s++) {
                $section = Section::create([
                    'course_id' => $course->id,
                    'title' => "Section {$s} of Course {$c}",
                    'order' => $s,
                ]);

                for ($l = 1; $l <= self::LESSONS_PER_SECTION; $l++) {
                    $lesson = Lesson::create([
                        'section_id' => $section->id,
                        'title' => "Lesson {$l} in Section {$s}, Course {$c}",
                        'video_id' => "LOAD_VIDEO_{$c}_{$s}_{$l}",
                        'duration' => rand(300, 1800),
                        'order' => $l,
                        'is_preview' => $l === 1 && $s === 1, // first lesson is preview
                    ]);
                    $allLessonIds[] = $lesson->id;
                }
            }
        }

        // ─── 3. Enroll Students in Courses ──────────────────────
        $this->command->info('📝 Creating enrollments...');
        $enrollmentData = [];

        foreach ($tokens as $idx => $t) {
            $userId = $t['user_id'];
            // Each student enrolls in 2-5 random courses
            $enrollCount = rand(2, 5);
            $enrolledCourseIds = array_rand(array_flip($courseIds), min($enrollCount, count($courseIds)));
            if (! is_array($enrolledCourseIds)) {
                $enrolledCourseIds = [$enrolledCourseIds];
            }

            foreach ($enrolledCourseIds as $cid) {
                $enrollmentData[] = [
                    'user_id' => $userId,
                    'course_id' => $cid,
                    'status' => 'approved',
                    'enrolled_at' => now()->subDays(rand(1, 60)),
                    'created_at' => now()->subDays(rand(1, 60)),
                    'updated_at' => now(),
                ];
            }
        }

        // Bulk insert enrollments
        foreach (array_chunk($enrollmentData, 500) as $chunk) {
            DB::table('enrollments')->insert($chunk);
        }

        // ─── 4. Lesson Progress (varied completion) ─────────────
        $this->command->info('📊 Creating lesson progress...');
        $progressData = [];

        foreach ($tokens as $t) {
            $userId = $t['user_id'];
            // Each student completes 30-70% of available lessons
            $count = (int) (count($allLessonIds) * (rand(30, 70) / 100));
            $lessonSample = array_rand(array_flip($allLessonIds), max(1, min($count, count($allLessonIds))));
            if (! is_array($lessonSample)) {
                $lessonSample = [$lessonSample];
            }

            foreach ($lessonSample as $lid) {
                $progressData[] = [
                    'user_id' => $userId,
                    'lesson_id' => $lid,
                    'completed' => true,
                    'watched_percent' => 100,
                    'last_position' => rand(300, 1800),
                    'created_at' => now()->subDays(rand(0, 30)),
                    'updated_at' => now()->subDays(rand(0, 5)),
                ];
            }
        }

        foreach (array_chunk($progressData, 1000) as $chunk) {
            DB::table('user_lesson_progress')->insert($chunk);
        }

        // ─── 5. Active Zoom Meetings (canJoin = true) ───────────
        $this->command->info('📹 Creating active Zoom meetings...');
        $meetingIds = [];

        foreach ($courseIds as $cid) {
            // 2 meetings per course: 1 active (can join), 1 ended
            $activeMeeting = ZoomMeeting::create([
                'course_id' => $cid,
                'title' => "Live Session — Course {$cid}",
                'description' => 'Active meeting for load testing',
                'zoom_link' => "https://zoom.us/j/loadtest{$cid}",
                'starts_at' => Carbon::now()->subMinutes(5), // started 5 min ago
                'duration_minutes' => 120, // ends in 115 min
            ]);
            $meetingIds[] = $activeMeeting->id;

            ZoomMeeting::create([
                'course_id' => $cid,
                'title' => "Past Session — Course {$cid}",
                'description' => 'Ended meeting',
                'zoom_link' => "https://zoom.us/j/past{$cid}",
                'starts_at' => Carbon::now()->subDays(7),
                'duration_minutes' => 60,
            ]);
        }

        DB::statement('SET session_replication_role = DEFAULT;');

        // ─── 6. Export tokens for k6 ────────────────────────────
        $tokensFile = base_path('tests/K6/tokens.json');
        if (! is_dir(dirname($tokensFile))) {
            mkdir(dirname($tokensFile), 0755, true);
        }

        $exportData = [
            'students' => $tokens,
            'admin' => ['user_id' => $admin->id, 'token' => $adminToken],
            'lesson_ids' => array_slice($allLessonIds, 0, 50), // first 50 for variety
            'meeting_ids' => $meetingIds,
            'course_ids' => $courseIds,
        ];

        file_put_contents($tokensFile, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->command->info('');
        $this->command->info('✅ Load test data seeded successfully!');
        $this->command->info('   👥 Students: '.self::STUDENT_COUNT);
        $this->command->info('   📚 Courses: '.self::COURSE_COUNT);
        $this->command->info('   📖 Lessons: '.count($allLessonIds));
        $this->command->info('   📝 Enrollments: '.count($enrollmentData));
        $this->command->info('   📊 Progress records: '.count($progressData));
        $this->command->info('   📹 Active meetings: '.count($meetingIds));
        $this->command->info('   🔑 Tokens exported to: tests/K6/tokens.json');
    }
}
