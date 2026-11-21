# تقرير سلوك النظام (Behavior Report) — ربط المدربين بحسابات المستخدمين (Ticket 01: Phase 1)

**البيئة (Environment):** Laravel Framework 13.42.0, PHP 8.5.4, Runner: Pest/PHPUnit, DB: PostgreSQL (`courses_testing`)  
**الأوامر المنفذة (Commands run):**
- `php artisan test tests/Feature/Admin/InstructorUserLinkingTest.php` -> 29 tests passed, 186 assertions.
- `python3 .agents/skills/laravel-logic-trace-tests/scripts/verify_tests.py --path InstructorUserLinkingTest` -> 0 FAIL, 0 WARN.

| الحافة (Edge) | السلوك الحالي الملاحظ (Current) | السلوك الموصى به (Recommended) | السبب (Why) | الدليل الإثباتي (Evidence) |
|---|---|---|---|---|
| E-01 | الطلب غير المصادق عليه لـ `POST /api/admin/instructors` يُرفض برمز الحالة 401 | 401 (precedent: `bootstrap/app.php:44`) | حماية نقاط نهاية الإدارة من الوصول غير المصرح به. | `app/Http/Controllers/Api/Admin/InstructorController.php:53` ; P-01 |
| E-02 | طلب المستخدم المصادق عليه بدور طالب (Student) يُرفض برمز الحالة 403 | 403 (precedent: `app/Policies/UserPolicy.php:14`) | تخصيص لوحة الإدارة حصرًا لمديري النظام ومنع الطلاب من إنشائها. | `app/Policies/UserPolicy.php:14` ; P-02 |
| E-04 | ربط حساب مستخدم مؤهل بدور طالب ينشئ ملف المدرب ويمنح المستخدم دور `instructor` تلقائيًا برمز 201 | 201 مع ترقية الدور (precedent: `app/Services/InstructorService.php:24`) | ضمان التزامن الحتمي بين ملف المدرب وصلاحيات الدخول التشغيلية. | `app/Services/InstructorService.php:24` ; P-04 |
| E-05 | إنشاء ملف بـ `user_id = null` ينشئ بروفايل مدرب للعرض فقط برمز 201 مع `is_display_only = true` | 201 مع `is_display_only: true` (precedent: `app/Models/Instructor.php:61`) | دعم المحاضرين الضيوف والملفات التسويقية دون الحاجة لحسابات دخول للمنصة. | `app/Models/Instructor.php:61` ; P-05 |
| E-09 | محاولة ربط حساب مستخدم محذوف برمجيًا (Soft-deleted) تُرفض برمز 422 | 422 validation error (precedent: `app/Http/Requests/InstructorRequest.php:31`) | منع ربط حسابات معطلة أو محذوفة بالملفات التدريسية النشطة. | `app/Http/Requests/InstructorRequest.php:31` ; P-09 |
| E-10 | محاولة ربط مستخدم مربوط مسبقًا بملف مدرب آخر تُرفض برمز 422 بخطأ فريد (unique) | 422 validation error (precedent: `app/Http/Requests/InstructorRequest.php:32`) | فرض قاعدة 1-إلى-1 الصارمة لمنع تضارب الهويات وازدواجية الملفات. | `app/Http/Requests/InstructorRequest.php:32` ; P-10 |
| E-11 | محاولة ربط حساب مسؤول نظام (Admin) بملف مدرب تُرفض برمز 422 ("لا يمكن ربط حساب مسؤول بدور مدرب.") | 422 validation error (precedent: `app/Http/Requests/InstructorRequest.php:38`) | الحفاظ على مبدأ الفصل الصارم بين المهام الإدارية والتشغيلية. | `app/Http/Requests/InstructorRequest.php:38` ; P-11 |
| E-18 | استجابة لوحة الإدارة تكشف `user_id` و `is_display_only` وكائن `user` المربوط برمز 200/201 | كشف المعرفات للمسؤولين (precedent: `app/Http/Resources/InstructorResource.php:18`) | تمكين مسؤولي النظام من التحقق والتدقيق في الحسابات المربوطة. | `app/Http/Resources/InstructorResource.php:18` ; P-18 |
| E-19 | استجابة العرض العام والطلاب `/api/instructors` تحجب تمامًا `user_id` و `is_display_only` و `user` | عزل البيانات الحساسة عن العامة (precedent: `app/Http/Resources/InstructorResource.php:18`) | حماية خصوصية المستخدمين ومنع تسريب المعرفات والبريد الإلكتروني للعامة. | `app/Http/Resources/InstructorResource.php:18` ; P-19 |

## غير المتحقق منه (UNVERIFIED)
لا يوجد (كافة الحالات تم فحص مساراتها البرمجية وإثباتها عبر مجسات تجريبية probes واختبارات معتمدة).

## غير المغطى (Not covered / SKIP rows)
لا يوجد (كافة صفوف جدول القرارات D-01 إلى D-19 مغطاة باختبارات نشطة).
