<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use App\Models\ZoomMeeting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class E2EPlatformSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Default Instructor for courses
        $instructor = Instructor::firstOrCreate(
            ['name' => 'المهندس أحمد خالد'],
            [
                'title' => 'كبير مهندسي البرمجيات',
                'bio' => 'مدرب معتمد في تطوير الويب والأنظمة الموزعة',
                'years_experience' => 10,
                'rating' => 4.9,
            ]
        );

        $adminRole = Role::findOrCreate('admin', 'web');
        $studentRole = Role::findOrCreate('student', 'web');
        $instructorRole = Role::findOrCreate('instructor', 'web');

        // 1. Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@e2e.test'],
            [
                'name' => 'مدير النظام',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles([$adminRole]);

        // 2. Student User (Primary student for happy paths)
        $student = User::firstOrCreate(
            ['email' => 'student@e2e.test'],
            [
                'name' => 'طالب تجريبي معتمد',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $student->syncRoles([$studentRole]);

        // 3. Intruder User (For negative security / BOLA testing)
        $intruder = User::firstOrCreate(
            ['email' => 'intruder@e2e.test'],
            [
                'name' => 'مستخدم غير مصرح له',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $intruder->syncRoles([$studentRole]);

        // 4. Free Published Course (Accessible to all students)
        $freeCourse = Course::firstOrCreate(
            ['title' => 'كورس مجاني تجريبي E2E'],
            [
                'instructor_id' => $instructor->id,
                'description' => 'كورس مجاني كامل لاختبار تدفقات الـ E2E ومشاهدة الدروس.',
                'level' => 'beginner',
                'status' => 'published',
                'price' => 0,
                'is_public' => true,
                'total_duration' => 3600,
                'what_you_learn' => ['أساسيات المنصة', 'تصفح الدروس وتشغيل الفيديو'],
            ]
        );

        $freeSection = Section::firstOrCreate(
            ['course_id' => $freeCourse->id, 'title' => 'القسم التمهيدي'],
            ['order' => 1]
        );

        $freeLesson = Lesson::firstOrCreate(
            ['section_id' => $freeSection->id, 'title' => 'الدرس الأول: مقدمة المنصة'],
            [
                'description' => 'مقدمة حول استخدام منصة الكورسات ومشاهدة الفيديوهات',
                'video_id' => 'dQw4w9WgXcQ',
                'duration' => 600,
                'order' => 1,
                'is_preview' => true,
            ]
        );

        // 5. Paid Published Course (Accessible only to enrolled & approved students)
        $paidCourse = Course::firstOrCreate(
            ['title' => 'كورس مدفوع محمي E2E'],
            [
                'instructor_id' => $instructor->id,
                'description' => 'كورس مدفوع ومحمي لاختبار الحماية الأمنية ومنع الوصول غير المصرح.',
                'level' => 'advanced',
                'status' => 'published',
                'price' => 199.99,
                'is_public' => false,
                'total_duration' => 7200,
                'what_you_learn' => ['هندسة البرمجيات المتقدمة', 'تأمين الأنظمة السحابية'],
            ]
        );

        $paidSection = Section::firstOrCreate(
            ['course_id' => $paidCourse->id, 'title' => 'قسم المحتوى المحمي'],
            ['order' => 1]
        );

        $paidLesson = Lesson::firstOrCreate(
            ['section_id' => $paidSection->id, 'title' => 'الدرس المحمي: أسرار الأمان'],
            [
                'description' => 'هذا الدرس مخصص فقط للطلاب المشتركين والمدفوع لهم',
                'video_id' => 'L_LUpnjgPso',
                'duration' => 1200,
                'order' => 1,
                'is_preview' => false,
            ]
        );

        // Enroll the primary student into the free course so they are actively registered
        Enrollment::firstOrCreate(
            ['course_id' => $freeCourse->id, 'user_id' => $student->id],
            ['status' => 'approved']
        );

        // Ensure intruder is NOT enrolled in the paid course

        // 6. Live Zoom Meeting for Live Attendance testing (starts 5 minutes ago -> canJoin is true)
        ZoomMeeting::firstOrCreate(
            ['title' => 'لقاء Zoom تفاعلي لاختبار الحضور E2E'],
            [
                'course_id' => $freeCourse->id,
                'description' => 'جلسة نقاش مباشرة حول المنهج واختبار آلية تسجيل الحضور',
                'zoom_link' => 'https://zoom.us/j/999888777666',
                'starts_at' => now()->subMinutes(5),
                'duration_minutes' => 60,
            ]
        );
    }
}
