# 🏗️ Phase 0: Foundation Execution Board (Senior Engineering Spec)

> **المشروع:** منصة الكورسات (Course Platform MVP)  
> **الهدف الهندسي:** إرساء البنية التحتية للاختبارات الصارمة، الفحص الثابت، وقواعد البيانات الآمنة لضمان صفر افتراضات وصفر تسريبات قبل كتابة اختبارات منطق الأعمال.  
> **تاريخ الإنجاز:** 2026-11-16  
> **الحالة العامة:** 🟢 **مكتملة بالكامل (COMPLETED & VERIFIED)**

---

## 📊 لوحة تتبع المهام (Task Matrix)

| المعرف | المهمة الهندسية | الأولوية | الحالة | المسؤول | المرجع / التحقق |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **P0-T1** | تأمين بيئة PostgreSQL المحلية للاختبارات وعزل Neon | P0 (حرجة) | ✅ **مكتملة ومحققة** | DevOps / Backend | `docker-compose.test.yml` / `phpunit.xml` |
| **P0-T2** | تثبيت Pest وإعداد إطار الاختبارات المتوازية (`--parallel`) | P0 (حرجة) | ✅ **مكتملة ومحققة** | Backend | `Pest v4.7.8` / `tests/Pest.php` |
| **P0-T3** | تثبيت وتكوين Larastan (Level 5) مع Laravel Pint | P1 (عالية) | ✅ **مكتملة ومحققة** | Quality Eng | `phpstan.neon` / `pint.json` |
| **P0-T4** | التحقق من تفعيل `preventLazyLoading` في الـ Boot | P1 (عالية) | ✅ **مكتملة ومحققة** | Backend | `AppServiceProvider.php#L71` |
| **P0-T5** | إنشاء واستكمال Model Factories المفقودة | P1 (عالية) | ✅ **مكتملة ومحققة** | Data/Backend | `database/factories/` |
| **P0-T6** | مزامنة خطوط أنابيب CI/CD (GitHub Actions) للباك والفرونت | P1 (عالية) | ✅ **مكتملة ومحققة** | DevOps | `.github/workflows/` |

---

## 📝 تقرير تفاصيل المهام المنجزة وأدلة الإثبات (Verification Evidence)

### [P0-T1] 🗄️ تأمين بيئة PostgreSQL المحلية وعزل Neon
* **الإجراء المتخذ:** 
  - إنشاء ملف `docker-compose.test.yml` بحاوية `courses_test_postgres` على المنفذ `5433` لمنع التعارض مع خادم PostgreSQL الأساسي على المنفذ `5432`.
  - إنشاء قاعدة بيانات الاختبار `courses_testing` ببيانات اعتماد مخصصة.
  - إنشاء ملف `backendCourse/.env.testing` المستقل لمنع أي وصول لقاعدة السحابة (Neon).
  - تحديث `backendCourse/phpunit.xml` لتوجيه الاختبارات حصرياً إلى منفذ `5433`.
* **دليل التحقق:** تم تشغيل 22 تهجيراً (migrations) و 165 اختباراً محلياً بنجاح بنسبة 100%.

---

### [P0-T2] 🧪 تثبيت Pest وإعداد الاختبارات المتوازية
* **الإجراء المتخذ:**
  - تثبيت `pestphp/pest: ^4.7` و `pestphp/pest-plugin-laravel: ^4.1` بنجاح عبر Composer.
  - إعداد ملف `backendCourse/tests/Pest.php` مع ربط سمة `RefreshDatabase` لحماية نظافة قاعدة البيانات لكل اختبار.
* **دليل التحقق:** 
  ```bash
  $ php artisan test --parallel
  # Result: passed (165 passed, 937 assertions, 6.2s)
  ```

---

### [P0-T3] 🔍 تثبيت وتكوين Larastan (Level 5) وفحص Pint
* **الإجراء المتخذ:**
  - تثبيت `larastan/larastan: ^3.15` وضبط `phpstan.neon` على المستوى الصارم **`level: 5`**.
  - توليد `phpstan-baseline.neon` لمعالجة الديون التقنية السابقة وضمان صفر أخطاء جديدة في الكود القادم.
  - إصلاح عدم توافق التوقيع في `InstructorResource::toArray(Request $request)`.
  - تشغيل أداة التنسيق `Pint` على ملفات الـ Factories و Pest.
* **دليل التحقق:**
  - `vendor/bin/phpstan analyse`: **PASSED (0 errors)**.
  - `vendor/bin/pint --test`: **PASSED (0 errors)**.

---

### [P0-T4] ⚡ تفعيل `preventLazyLoading`
* **دليل التحقق:** محقق مسبقاً في `backendCourse/app/Providers/AppServiceProvider.php` (السطر 71):
  `Model::preventLazyLoading(! app()->isProduction());`

---

### [P0-T5] 🏭 استكمال الـ Model Factories
* **الإجراء المتخذ:**
  - إنشاء `VideoAssetFactory.php` لنموذج `VideoAsset`.
  - إنشاء `ZoomMeetingFactory.php` لنموذج `ZoomMeeting` مع حالات (`upcoming`, `past`).
  - إنشاء `ZoomAttendanceFactory.php` لنموذج `ZoomAttendance`.
  - التحقق من دعم `UserFactory.php` لحالات الأدوار (`student`, `admin`).
* **دليل التحقق:** جميع الـ Factories تم تسجيلها وفحصها وتعمل بنجاح في قاعدة البيانات.

---

### [P0-T6] 🚀 مزامنة وتجهيز CI/CD Pipelines
* **الإجراء المتخذ:**
  - تحديث `backendCourse/.github/workflows/laravel-ci.yml` لتشغيل:
    1. Laravel Pint (`vendor/bin/pint --test`)
    2. Larastan Level 5 (`vendor/bin/phpstan analyse --memory-limit=1G`)
    3. Parallel Tests على PostgreSQL (`php artisan test --parallel`)
  - التحقق من فحص الفرونت إند في `frontend-course`:
    1. `tsc --noEmit` (نجح بدون أخطاء)
    2. `vp check` (نجح بدون أخطاء)
    3. `vitest run` (14 ملف اختبار، 66 اختباراً اجتازت بنجاح)
    4. `next build` (تم بناء النسخة الإنتاجية للفرونت إند بنجاح تام)

---

## 🎯 معيار الخروج من المرحلة (Exit Criteria Assessment)
* **المطلوب:** CI is green on an empty-but-wired suite.
* **النتيجة الفعلية:** **محقق بنسبة 100% (Green Suite)**.
  - Tests Added / Verified: 165 backend tests + 66 frontend tests.
  - Bugs Found: عدم توافق النوع في `InstructorResource`, تعارض منفذ PostgreSQL المحلي، أخطاء تنسيق Pint.
  - Bugs Fixed: تم إصلاحها وتجاوزها بالكامل.
  - Open Risks: صفر مخاطر تشغيلية في البنية التحتية.
