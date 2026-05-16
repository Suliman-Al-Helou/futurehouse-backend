<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 1. مستخدم تجريبي ───────────────────────────────────────
        $userId = DB::table('users')->insertGetId([
            'name'              => 'طالب تجريبي',
            'email'             => 'test@test.com',
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
            'role'              => 'student',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // ─── 2. كورس أول ────────────────────────────────────────────
        $course1 = DB::table('courses')->insertGetId([
            'title'          => 'أساسيات البرمجة مع Python',
            'description'    => 'تعلم البرمجة من الصفر حتى الاحتراف مع لغة Python — أسهل لغة للمبتدئين',
            'cover_image'    => null,
            'level'          => 'beginner',
            'status'         => 'published',
            'total_duration' => '6 ساعات',
            'what_you_learn' => json_encode([
                'كتابة برامج Python من الصفر',
                'فهم المتغيرات والحلقات والدوال',
                'التعامل مع الملفات والبيانات',
                'بناء مشروع عملي كامل',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // قسم 1
        $sec1 = DB::table('sections')->insertGetId([
            'course_id'  => $course1,
            'title'      => 'المقدمة والإعداد',
            'order'      => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // درس 1-1
        $l1 = DB::table('lessons')->insertGetId([
            'section_id'  => $sec1,
            'title'       => 'مرحباً بك في Python',
            'video_id'    => 'DEMO_VIDEO_1',
            'duration'    => '12:30',
            'order'       => 1,
            'is_preview'  => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        DB::table('tasks')->insert([
            'lesson_id'      => $l1,
            'questions'      => json_encode([
                ['id'=>'q1','type'=>'mcq','text'=>'ما هي Python؟','options'=>['لغة برمجة','برنامج تصميم','نظام تشغيل','قاعدة بيانات'],'correct_answer'=>'لغة برمجة','explanation'=>'Python هي لغة برمجة عالية المستوى سهلة التعلم'],
                ['id'=>'q2','type'=>'true_false','text'=>'Python مناسبة للمبتدئين','options'=>['صح','خطأ'],'correct_answer'=>'صح','explanation'=>'نعم، Python من أسهل لغات البرمجة للمبتدئين'],
            ]),
            'pass_percentage' => 70,
            'max_attempts'    => 3,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // درس 1-2
        $l2 = DB::table('lessons')->insertGetId([
            'section_id'  => $sec1,
            'title'       => 'تثبيت Python وإعداد البيئة',
            'video_id'    => 'DEMO_VIDEO_2',
            'duration'    => '18:00',
            'order'       => 2,
            'is_preview'  => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        DB::table('tasks')->insert([
            'lesson_id'      => $l2,
            'questions'      => json_encode([
                ['id'=>'q1','type'=>'mcq','text'=>'من أين تنزل Python؟','options'=>['python.org','google.com','github.com','python.net'],'correct_answer'=>'python.org','explanation'=>'الموقع الرسمي لتحميل Python هو python.org'],
                ['id'=>'q2','type'=>'true_false','text'=>'يمكن كتابة كود Python بأي محرر نصوص','options'=>['صح','خطأ'],'correct_answer'=>'صح','explanation'=>'نعم، يمكن استخدام أي محرر، لكن VS Code و PyCharm الأفضل'],
            ]),
            'pass_percentage' => 70,
            'max_attempts'    => 3,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // قسم 2
        $sec2 = DB::table('sections')->insertGetId([
            'course_id'  => $course1,
            'title'      => 'أساسيات اللغة',
            'order'      => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // درس 2-1
        $l3 = DB::table('lessons')->insertGetId([
            'section_id'  => $sec2,
            'title'       => 'المتغيرات وأنواع البيانات',
            'video_id'    => 'DEMO_VIDEO_3',
            'duration'    => '22:15',
            'order'       => 1,
            'is_preview'  => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        DB::table('tasks')->insert([
            'lesson_id'      => $l3,
            'questions'      => json_encode([
                ['id'=>'q1','type'=>'mcq','text'=>'ما نوع البيانات الناتج عن: x = "مرحبا"','options'=>['str','int','float','bool'],'correct_answer'=>'str','explanation'=>'النص دائماً من نوع str في Python'],
                ['id'=>'q2','type'=>'mcq','text'=>'ما قيمة: x = 5 + 3.0','options'=>['8','8.0','خطأ','none'],'correct_answer'=>'8.0','explanation'=>'عند جمع int مع float، الناتج دائماً float'],
                ['id'=>'q3','type'=>'true_false','text'=>'Python لغة case-sensitive (تفرق بين الكبير والصغير)','options'=>['صح','خطأ'],'correct_answer'=>'صح','explanation'=>'نعم، name و Name و NAME ثلاثة متغيرات مختلفة في Python'],
            ]),
            'pass_percentage' => 70,
            'max_attempts'    => 3,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // ─── 3. كورس ثاني ───────────────────────────────────────────
        $course2 = DB::table('courses')->insertGetId([
            'title'          => 'تصميم واجهات المستخدم مع Figma',
            'description'    => 'تعلم تصميم واجهات احترافية من الصفر — من الفكرة حتى التسليم للمطور',
            'cover_image'    => null,
            'level'          => 'intermediate',
            'status'         => 'published',
            'total_duration' => '8 ساعات',
            'what_you_learn' => json_encode([
                'استخدام Figma من الصفر',
                'تصميم شاشات تطبيقات موبايل',
                'إنشاء Design System كامل',
                'تسليم التصاميم للمطورين',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sec3 = DB::table('sections')->insertGetId([
            'course_id'  => $course2,
            'title'      => 'البداية مع Figma',
            'order'      => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $l4 = DB::table('lessons')->insertGetId([
            'section_id'  => $sec3,
            'title'       => 'جولة في واجهة Figma',
            'video_id'    => 'DEMO_VIDEO_4',
            'duration'    => '15:00',
            'order'       => 1,
            'is_preview'  => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        DB::table('tasks')->insert([
            'lesson_id'      => $l4,
            'questions'      => json_encode([
                ['id'=>'q1','type'=>'mcq','text'=>'Figma برنامج مخصص لـ','options'=>['البرمجة','التصميم','الفيديو','الصوت'],'correct_answer'=>'التصميم','explanation'=>'Figma أداة تصميم واجهات المستخدم UI/UX'],
                ['id'=>'q2','type'=>'true_false','text'=>'Figma مجاني للاستخدام الفردي','options'=>['صح','خطأ'],'correct_answer'=>'صح','explanation'=>'نعم، Figma مجاني للأفراد مع إمكانية الترقية للباقات المدفوعة'],
            ]),
            'pass_percentage' => 70,
            'max_attempts'    => 3,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // ─── 4. اشترك الطالب في الكورس الأول ────────────────────────
        DB::table('enrollments')->insert([
            'user_id'      => $userId,
            'course_id'    => $course1,
            'enrolled_at'  => now(),
            'completed_at' => null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->command->info('✅ تم إنشاء البيانات التجريبية بنجاح!');
        $this->command->info('📧 الإيميل: test@test.com');
        $this->command->info('🔑 كلمة المرور: password');
        $this->command->info('📚 ' . DB::table('courses')->count() . ' كورسات، ' . DB::table('lessons')->count() . ' دروس');
    }
}