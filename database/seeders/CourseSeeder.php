<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Course;
use App\Models\Section;
use App\Models\Lesson;
use App\Models\Task;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        // ═══════════════════════════════════════════════════════════
        // الكورسات المنشورة — published (4 كورسات)
        // ═══════════════════════════════════════════════════════════

        // ─── كورس 1: Python ───────────────────────────────────────
        $python = Course::create([
            'title'           => 'Python من الصفر إلى الاحتراف',
            'description'     => 'كورس شامل يأخذك من لا تعرف شيئاً عن البرمجة إلى مستوى يُمكّنك من بناء مشاريع حقيقية بلغة Python — أكثر لغات البرمجة طلباً في سوق العمل.',
            'cover_image'     => 'https://images.unsplash.com/photo-1526379095098-d400fd0bf935?w=800&h=450&fit=crop',
            'level'           => 'beginner',
            'status'          => 'published',
            'total_duration'  => 14400,
            'what_you_learn'  => [
                'كتابة كود Python نظيف واحترافي',
                'بناء مشاريع ويب باستخدام Flask',
                'التعامل مع قواعد البيانات',
                'برمجة الـ APIs وتحليل البيانات',
                'تطبيق مبادئ OOP في مشاريع حقيقية',
            ],
            'requirements'    => 'لا يُشترط أي خبرة سابقة. جهاز كمبيوتر واتصال بالإنترنت.',
            'target_audience' => 'المبتدئون الراغبون في دخول عالم البرمجة.',
            'students_count'  => 1240,
        ]);

        $s1 = Section::create(['course_id' => $python->id, 'title' => 'أساسيات Python', 'order' => 1]);

        Lesson::create(['section_id' => $s1->id, 'title' => 'مقدمة وتثبيت البيئة', 'video_id' => 'dQw4w9WgXcQ', 'duration' => 900, 'order' => 1, 'is_preview' => true]);

        $l2 = Lesson::create(['section_id' => $s1->id, 'title' => 'المتغيرات وأنواع البيانات', 'video_id' => 'dQw4w9WgXcQ', 'duration' => 1320, 'order' => 2, 'is_preview' => false]);
        Task::create([
            'lesson_id' => $l2->id, 'pass_percentage' => 70, 'max_attempts' => 3, 'cooldown_minutes' => 60,
            'questions' => [
                ['id' => 'q1', 'type' => 'mcq', 'text' => 'ما نوع البيانات الناتج عن: type(3.14)؟', 'options' => ['int', 'float', 'str', 'bool'], 'correct_answer' => 'float', 'explanation' => 'الأرقام العشرية من نوع float'],
                ['id' => 'q2', 'type' => 'true_false', 'text' => 'في Python يمكن تغيير نوع المتغير بعد تعريفه.', 'options' => ['صح', 'خطأ'], 'correct_answer' => 'صح', 'explanation' => 'Python لغة ديناميكية النوع'],
                ['id' => 'q3', 'type' => 'mcq', 'text' => 'أي من التالي يُعرَّف كـ boolean؟', 'options' => ['1.0', '"True"', 'True', '1'], 'correct_answer' => 'True', 'explanation' => 'القيم البولية True و False بحرف كبير'],
            ],
        ]);

        $l3 = Lesson::create(['section_id' => $s1->id, 'title' => 'الشروط والحلقات', 'video_id' => 'dQw4w9WgXcQ', 'duration' => 1800, 'order' => 3, 'is_preview' => false]);
        Task::create([
            'lesson_id' => $l3->id, 'pass_percentage' => 70, 'max_attempts' => 3, 'cooldown_minutes' => 60,
            'questions' => [
                ['id' => 'q1', 'type' => 'mcq', 'text' => 'ما الكلمة التي تُوقف الحلقة فوراً؟', 'options' => ['stop', 'exit', 'break', 'end'], 'correct_answer' => 'break', 'explanation' => 'break تُوقف الحلقة وتخرج منها'],
                ['id' => 'q2', 'type' => 'true_false', 'text' => 'حلقة while تنفذ الكود طالما الشرط صحيح.', 'options' => ['صح', 'خطأ'], 'correct_answer' => 'صح', 'explanation' => 'while تستمر طالما الشرط True'],
                ['id' => 'q3', 'type' => 'mcq', 'text' => 'ما ناتج range(1, 5)؟', 'options' => ['1,2,3,4,5', '1,2,3,4', '0,1,2,3,4', '1,2,3'], 'correct_answer' => '1,2,3,4', 'explanation' => 'range(1,5) من 1 إلى 4 فقط'],
            ],
        ]);

        $s2 = Section::create(['course_id' => $python->id, 'title' => 'الدوال والوحدات', 'order' => 2]);

        $l4 = Lesson::create(['section_id' => $s2->id, 'title' => 'تعريف الدوال واستخدامها', 'video_id' => 'dQw4w9WgXcQ', 'duration' => 1500, 'order' => 1, 'is_preview' => false]);
        Task::create([
            'lesson_id' => $l4->id, 'pass_percentage' => 70, 'max_attempts' => 3, 'cooldown_minutes' => 60,
            'questions' => [
                ['id' => 'q1', 'type' => 'mcq', 'text' => 'ما الكلمة المفتاحية لتعريف دالة؟', 'options' => ['func', 'function', 'def', 'define'], 'correct_answer' => 'def', 'explanation' => 'def هي الكلمة المفتاحية للدوال'],
                ['id' => 'q2', 'type' => 'true_false', 'text' => 'الدالة يمكنها إرجاع أكثر من قيمة.', 'options' => ['صح', 'خطأ'], 'correct_answer' => 'صح', 'explanation' => 'يمكن إرجاع tuple يحتوي عدة قيم'],
                ['id' => 'q3', 'type' => 'open', 'text' => 'اكتب دالة تستقبل رقمين وترجع مجموعهما.', 'options' => [], 'correct_answer' => '', 'explanation' => 'مثال: def add(a, b): return a + b'],
            ],
        ]);

        $s3 = Section::create(['course_id' => $python->id, 'title' => 'البرمجة الكائنية OOP', 'order' => 3]);

        $l5 = Lesson::create(['section_id' => $s3->id, 'title' => 'مفهوم الكلاسات والكائنات', 'video_id' => 'dQw4w9WgXcQ', 'duration' => 2100, 'order' => 1, 'is_preview' => false]);
        Task::create([
            'lesson_id' => $l5->id, 'pass_percentage' => 70, 'max_attempts' => 3, 'cooldown_minutes' => 60,
            'questions' => [
                ['id' => 'q1', 'type' => 'mcq', 'text' => 'ما الدالة الخاصة عند إنشاء كائن جديد؟', 'options' => ['__start__', '__new__', '__init__', '__create__'], 'correct_answer' => '__init__', 'explanation' => '__init__ هي constructor الكلاس'],
                ['id' => 'q2', 'type' => 'true_false', 'text' => 'self يشير إلى الكائن الحالي داخل الكلاس.', 'options' => ['صح', 'خطأ'], 'correct_answer' => 'صح', 'explanation' => 'self يُمرَّر تلقائياً ويشير للـ instance الحالي'],
            ],
        ]);

        // ─── كورس 2: React ────────────────────────────────────────
        $react = Course::create([
            'title'           => 'تطوير تطبيقات الويب بـ React',
            'description'     => 'تعلم بناء تطبيقات ويب تفاعلية احترافية باستخدام React من المفاهيم الأساسية حتى Hooks المتقدمة وإدارة الحالة.',
            'cover_image'     => 'https://images.unsplash.com/photo-1633356122544-f134324a6cee?w=800&h=450&fit=crop',
            'level'           => 'intermediate',
            'status'          => 'published',
            'total_duration'  => 18000,
            'what_you_learn'  => [
                'بناء components تفاعلية باحترافية',
                'إدارة الحالة مع useState و useReducer',
                'جلب البيانات مع useEffect',
                'React Router للتنقل بين الصفحات',
                'التكامل مع REST APIs',
            ],
            'requirements'    => 'معرفة أساسية بـ HTML وCSS وJavaScript.',
            'target_audience' => 'المطورون الذين يعرفون JavaScript ويريدون تعلم React.',
            'students_count'  => 890,
        ]);

        $rs1 = Section::create(['course_id' => $react->id, 'title' => 'أساسيات React', 'order' => 1]);

        Lesson::create(['section_id' => $rs1->id, 'title' => 'مقدمة لـ React و JSX', 'video_id' => 'dQw4w9WgXcQ', 'duration' => 1200, 'order' => 1, 'is_preview' => true]);

        $rl2 = Lesson::create(['section_id' => $rs1->id, 'title' => 'Components و Props', 'video_id' => 'dQw4w9WgXcQ', 'duration' => 1500, 'order' => 2, 'is_preview' => false]);
        Task::create([
            'lesson_id' => $rl2->id, 'pass_percentage' => 70, 'max_attempts' => 3, 'cooldown_minutes' => 60,
            'questions' => [
                ['id' => 'q1', 'type' => 'mcq', 'text' => 'ما الفرق الأساسي بين Props و State؟', 'options' => ['لا فرق', 'Props للقراءة فقط، State قابل للتغيير', 'State للقراءة فقط', 'كلاهما قابل للتغيير'], 'correct_answer' => 'Props للقراءة فقط، State قابل للتغيير', 'explanation' => 'Props تُمرَّر من الأب ولا تُعدَّل'],
                ['id' => 'q2', 'type' => 'true_false', 'text' => 'يمكن للـ Component تعديل Props المُمرَّرة إليه.', 'options' => ['صح', 'خطأ'], 'correct_answer' => 'خطأ', 'explanation' => 'Props للقراءة فقط'],
            ],
        ]);

        $rs2 = Section::create(['course_id' => $react->id, 'title' => 'React Hooks', 'order' => 2]);

        $rl3 = Lesson::create(['section_id' => $rs2->id, 'title' => 'useState و useEffect', 'video_id' => 'dQw4w9WgXcQ', 'duration' => 1800, 'order' => 1, 'is_preview' => false]);
        Task::create([
            'lesson_id' => $rl3->id, 'pass_percentage' => 70, 'max_attempts' => 3, 'cooldown_minutes' => 60,
            'questions' => [
                ['id' => 'q1', 'type' => 'mcq', 'text' => 'متى يُنفَّذ useEffect مع [] كـ dependency؟', 'options' => ['عند كل render', 'مرة واحدة بعد أول render', 'عند الـ unmount فقط', 'لا يُنفَّذ'], 'correct_answer' => 'مرة واحدة بعد أول render', 'explanation' => 'dependency array فارغ = componentDidMount'],
                ['id' => 'q2', 'type' => 'true_false', 'text' => 'useState يمكن استخدامه خارج الـ Component.', 'options' => ['صح', 'خطأ'], 'correct_answer' => 'خطأ', 'explanation' => 'Hooks لا تعمل إلا داخل Function Components'],
            ],
        ]);

        // ─── كورس 3: JavaScript ───────────────────────────────────
        $js = Course::create([
            'title'           => 'JavaScript الحديث ES6+',
            'description'     => 'إتقان JavaScript الحديثة من الأساسيات حتى المفاهيم المتقدمة مثل Promises وAsync/Await والـ Modules.',
            'cover_image'     => 'https://images.unsplash.com/photo-1579468118864-1b9ea3c0db4a?w=800&h=450&fit=crop',
            'level'           => 'beginner',
            'status'          => 'published',
            'total_duration'  => 21600,
            'what_you_learn'  => [
                'أساسيات JavaScript الحديثة ES6+',
                'التعامل مع DOM وإدارة الأحداث',
                'البرمجة غير المتزامنة Async/Await',
                'الـ Modules وتنظيم الكود',
                'بناء مشاريع تفاعلية كاملة',
            ],
            'requirements'    => 'معرفة أساسية بـ HTML وCSS فقط.',
            'target_audience' => 'كل من يريد تعلم برمجة الويب من الصفر.',
            'students_count'  => 2100,
        ]);

        $js1 = Section::create(['course_id' => $js->id, 'title' => 'أساسيات JavaScript', 'order' => 1]);

        Lesson::create(['section_id' => $js1->id, 'title' => 'مقدمة لـ JavaScript', 'video_id' => 'dQw4w9WgXcQ', 'duration' => 800, 'order' => 1, 'is_preview' => true]);

        $jl2 = Lesson::create(['section_id' => $js1->id, 'title' => 'المتغيرات let و const و var', 'video_id' => 'dQw4w9WgXcQ', 'duration' => 1200, 'order' => 2, 'is_preview' => false]);
        Task::create([
            'lesson_id' => $jl2->id, 'pass_percentage' => 70, 'max_attempts' => 3, 'cooldown_minutes' => 60,
            'questions' => [
                ['id' => 'q1', 'type' => 'mcq', 'text' => 'ما الفرق بين let و const؟', 'options' => ['لا فرق', 'let قابل للتغيير، const ثابت', 'const قابل للتغيير، let ثابت', 'كلاهما ثابت'], 'correct_answer' => 'let قابل للتغيير، const ثابت', 'explanation' => 'const لا يمكن إعادة تعيينه'],
                ['id' => 'q2', 'type' => 'true_false', 'text' => 'var له block scope في JavaScript.', 'options' => ['صح', 'خطأ'], 'correct_answer' => 'خطأ', 'explanation' => 'var له function scope وليس block scope'],
            ],
        ]);

        $js2 = Section::create(['course_id' => $js->id, 'title' => 'البرمجة غير المتزامنة', 'order' => 2]);

        $jl3 = Lesson::create(['section_id' => $js2->id, 'title' => 'Promises و Async/Await', 'video_id' => 'dQw4w9WgXcQ', 'duration' => 2400, 'order' => 1, 'is_preview' => false]);
        Task::create([
            'lesson_id' => $jl3->id, 'pass_percentage' => 70, 'max_attempts' => 3, 'cooldown_minutes' => 60,
            'questions' => [
                ['id' => 'q1', 'type' => 'mcq', 'text' => 'ما حالات الـ Promise الثلاث؟', 'options' => ['start/running/end', 'pending/fulfilled/rejected', 'loading/success/error', 'init/done/fail'], 'correct_answer' => 'pending/fulfilled/rejected', 'explanation' => 'الحالات الثلاث للـ Promise'],
                ['id' => 'q2', 'type' => 'true_false', 'text' => 'async/await هي مجرد syntactic sugar فوق Promises.', 'options' => ['صح', 'خطأ'], 'correct_answer' => 'صح', 'explanation' => 'async/await تجعل الكود غير المتزامن يبدو متزامناً'],
            ],
        ]);

        // ─── كورس 4: Next.js ──────────────────────────────────────
        $nextjs = Course::create([
            'title'           => 'Next.js 14 — تطوير مواقع احترافية',
            'description'     => 'تعلم بناء مواقع ويب كاملة بـ Next.js 14 مع App Router وServer Components وOptimization احترافية.',
            'cover_image'     => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=800&h=450&fit=crop',
            'level'           => 'advanced',
            'status'          => 'published',
            'total_duration'  => 25200,
            'what_you_learn'  => [
                'App Router وServer Components',
                'Server Side Rendering و Static Generation',
                'API Routes وMiddleware',
                'Authentication مع NextAuth',
                'Deployment على Vercel',
            ],
            'requirements'    => 'خبرة جيدة في React وTypeScript.',
            'target_audience' => 'مطورو React الذين يريدون الانتقال لـ Next.js.',
            'students_count'  => 650,
        ]);

        $ns1 = Section::create(['course_id' => $nextjs->id, 'title' => 'App Router', 'order' => 1]);

        Lesson::create(['section_id' => $ns1->id, 'title' => 'مقدمة لـ Next.js 14', 'video_id' => 'dQw4w9WgXcQ', 'duration' => 1100, 'order' => 1, 'is_preview' => true]);

        $nl2 = Lesson::create(['section_id' => $ns1->id, 'title' => 'Server Components vs Client Components', 'video_id' => 'dQw4w9WgXcQ', 'duration' => 2400, 'order' => 2, 'is_preview' => false]);
        Task::create([
            'lesson_id' => $nl2->id, 'pass_percentage' => 70, 'max_attempts' => 3, 'cooldown_minutes' => 60,
            'questions' => [
                ['id' => 'q1', 'type' => 'mcq', 'text' => 'ما الـ directive لتحديد Client Component؟', 'options' => ['"use server"', '"use client"', '"client only"', '"use browser"'], 'correct_answer' => '"use client"', 'explanation' => '"use client" في أول الملف'],
                ['id' => 'q2', 'type' => 'true_false', 'text' => 'Server Components يمكنها استخدام useState.', 'options' => ['صح', 'خطأ'], 'correct_answer' => 'خطأ', 'explanation' => 'Hooks تعمل فقط في Client Components'],
            ],
        ]);

        // ═══════════════════════════════════════════════════════════
        // الكورسات القادمة — coming_soon (3 كورسات)
        // ═══════════════════════════════════════════════════════════

        Course::create([
            'title'           => 'Flutter — تطوير تطبيقات الجوال',
            'description'     => 'بناء تطبيقات iOS وAndroid احترافية بكود واحد باستخدام Flutter ولغة Dart.',
            'cover_image'     => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=800&h=450&fit=crop',
            'level'           => 'intermediate',
            'status'          => 'coming_soon',
            'total_duration'  => 28800,
            'what_you_learn'  => [
                'أساسيات لغة Dart',
                'بناء واجهات Flutter',
                'إدارة الحالة مع Provider',
                'التكامل مع Firebase',
                'نشر التطبيق على المتاجر',
            ],
            'requirements'    => 'معرفة بأساسيات البرمجة.',
            'target_audience' => 'المطورون الراغبون في دخول عالم تطوير الجوال.',
            'students_count'  => 0,
        ]);

        Course::create([
            'title'           => 'DevOps و Docker من الصفر',
            'description'     => 'تعلم DevOps الحديث مع Docker وKubernetes وCI/CD وكل ما تحتاجه لرفع مشاريعك باحترافية.',
            'cover_image'     => 'https://images.unsplash.com/photo-1667372393119-3d4c48d07fc9?w=800&h=450&fit=crop',
            'level'           => 'advanced',
            'status'          => 'coming_soon',
            'total_duration'  => 32400,
            'what_you_learn'  => [
                'Docker وDocker Compose',
                'Kubernetes أساسيات',
                'CI/CD مع GitHub Actions',
                'نشر التطبيقات على Cloud',
                'Monitoring وLogging',
            ],
            'requirements'    => 'خبرة في Linux وبناء تطبيقات ويب.',
            'target_audience' => 'المطورون الذين يريدون تعلم DevOps.',
            'students_count'  => 0,
        ]);

        Course::create([
            'title'           => 'الذكاء الاصطناعي مع Python',
            'description'     => 'مدخل شامل لعالم الذكاء الاصطناعي وتعلم الآلة باستخدام Python وأشهر المكتبات.',
            'cover_image'     => 'https://images.unsplash.com/photo-1677442135703-1787eea5ce01?w=800&h=450&fit=crop',
            'level'           => 'advanced',
            'status'          => 'coming_soon',
            'total_duration'  => 36000,
            'what_you_learn'  => [
                'أساسيات Machine Learning',
                'Deep Learning مع TensorFlow',
                'معالجة اللغة الطبيعية NLP',
                'Computer Vision',
                'بناء نماذج AI حقيقية',
            ],
            'requirements'    => 'خبرة جيدة في Python والرياضيات الأساسية.',
            'target_audience' => 'المطورون المهتمون بالذكاء الاصطناعي.',
            'students_count'  => 0,
        ]);

        // ═══════════════════════════════════════════════════════════
        // الكورسات المسودة — draft (3 كورسات)
        // ═══════════════════════════════════════════════════════════

        Course::create([
            'title'           => 'TypeScript من المبتدئ للمحترف',
            'description'     => 'إتقان TypeScript وكيفية استخدامه لكتابة كود JavaScript أكثر أماناً وقابلية للصيانة.',
            'cover_image'     => 'https://images.unsplash.com/photo-1516116216624-53e697fedbea?w=800&h=450&fit=crop',
            'level'           => 'intermediate',
            'status'          => 'draft',
            'total_duration'  => 16200,
            'what_you_learn'  => [
                'أنواع البيانات في TypeScript',
                'Interfaces و Types',
                'Generics والبرمجة المتقدمة',
                'TypeScript مع React',
                'إعداد المشاريع الاحترافية',
            ],
            'requirements'    => 'معرفة جيدة بـ JavaScript.',
            'target_audience' => 'مطورو JavaScript الذين يريدون تعلم TypeScript.',
            'students_count'  => 0,
        ]);

        Course::create([
            'title'           => 'قواعد البيانات SQL وPostgreSQL',
            'description'     => 'تعلم التعامل مع قواعد البيانات العلائقية من الأساسيات حتى الاستعلامات المتقدمة والأداء.',
            'cover_image'     => 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?w=800&h=450&fit=crop',
            'level'           => 'beginner',
            'status'          => 'draft',
            'total_duration'  => 12600,
            'what_you_learn'  => [
                'أساسيات SQL',
                'تصميم قواعد البيانات',
                'الاستعلامات المتقدمة',
                'PostgreSQL وميزاته',
                'تحسين الأداء والـ Indexing',
            ],
            'requirements'    => 'لا يُشترط خبرة سابقة.',
            'target_audience' => 'المبتدئون في قواعد البيانات.',
            'students_count'  => 0,
        ]);

        Course::create([
            'title'           => 'Laravel — بناء APIs احترافية',
            'description'     => 'بناء RESTful APIs كاملة ومحكمة باستخدام Laravel مع المصادقة والصلاحيات والاختبارات.',
            'cover_image'     => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=800&h=450&fit=crop',
            'level'           => 'intermediate',
            'status'          => 'draft',
            'total_duration'  => 19800,
            'what_you_learn'  => [
                'بناء RESTful APIs',
                'المصادقة مع Sanctum وPassport',
                'الصلاحيات مع Spatie',
                'اختبار الـ APIs',
                'التوثيق مع Swagger',
            ],
            'requirements'    => 'معرفة بـ PHP وأساسيات Laravel.',
            'target_audience' => 'مطورو PHP الذين يريدون بناء APIs.',
            'students_count'  => 0,
        ]);

        $this->command->info('✅ تم إنشاء ' . Course::count() . ' كورس');
        $this->command->info('✅ ' . \App\Models\Lesson::count() . ' درس');
        $this->command->info('✅ ' . Task::count() . ' مهمة');
    }
}