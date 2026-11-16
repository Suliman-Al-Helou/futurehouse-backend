# 🛡️ Phase 1: Authentication & Authorization Execution Board (OWASP API Top 10)

> **المشروع:** منصة الكورسات (Course Platform MVP: Laravel API + Next.js BFF)  
> **الهدف الهندسي:** تحصين واختبار طبقة المصادقة والصلاحيات بالكامل ضد أخطر ثغرات OWASP API Top 10 (2023): Broken Object Level Authorization (BOLA/IDOR - API1)، Broken Authentication (API2)، و Broken Function Level Authorization (BFLA - API5).  
> **تاريخ الإنجاز:** 2026-11-16  
> **الحالة العامة:** 🟢 **مكتملة بالكامل ومحققة (COMPLETED & VERIFIED - 100% GREEN)**

---

## 📊 مصفوفة تتبع المهام (Phase 1 Task Matrix)

| المعرف | البند والمهمة الهندسية | معيار الأمان (OWASP) | الحالة | المسؤول / التحقق | المرجع البرمجي |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **P1-T1** | **تحصين واختبار المصادقة (3.1 Authentication)**<br>- تسجيل/دخول/خروج بنجاح<br>- منع تعداد المستخدمين (User Enumeration)<br>- إبطال التوكن عند الخروج وتطهير الجلسات القديمة<br>- فحص التوكن المنتهي أو التالف (401)<br>- حماية رمز إعادة التعيين (Single-use & Expiry)<br>- منع التعيين الجماعي لحقل `role`<br>- فحص كوكيز BFF وخلو `NEXT_PUBLIC_*` من الأسرار | OWASP API2: Broken Auth<br>OWASP API3: BOPLA | ✅ **مكتملة ومحققة** | Security Eng / Backend | `tests/Feature/Auth/PasswordResetTest.php`<br>`tests/Feature/Auth/RegisterTest.php`<br>`tests/Feature/Auth/LoginTest.php`<br>`tests/Feature/Auth/LogoutTest.php` |
| **P1-T2** | **مصفوفة الصلاحيات (3.2 Role × Endpoint Matrix)**<br>- بناء Pest Dataset لـ 78 مساراً (100% من `api/*`)<br>- اختبار الأدوار الثلاثة المعتمدة: `(guest, student, admin)`<br>- اختبار تغطية المسارات آلياً (Architectural route coverage) لمنع إضافة أي مسار مستقبلي بدون تغطية في المصفوفة | OWASP API5: Broken Function Level Authorization (BFLA) | ✅ **مكتملة ومحققة** | Security Eng / Backend | `tests/Feature/Auth/RoleEndpointMatrixTest.php` |
| **P1-T3** | **فحص الأمان على مستوى الكائنات (3.3 BOLA / IDOR)**<br>- عزل بيانات الطلاب (Student A vs Student B)<br>- حظر غير المسجلين من محتوى الكورس المدفوع (دروس، مهام، لقاءات زووم)<br>- حجب تفاصيل مكتبة الفيديو وقنواتها عن غير المديرين (Raw JSON check)<br>- منع تسريب الحقول الحساسة (BOPLA) في الاستجابات<br>- فرض حد أقصى للترقيم (Pagination Max Page Size: 50) | OWASP API1: Broken Object Level Authorization (BOLA)<br>OWASP API3: BOPLA | ✅ **مكتملة ومحققة** | Security Eng / Backend | `tests/Feature/Auth/BolaProtectionTest.php`<br>`app/Http/Controllers/Api/CourseController.php` |
| **P1-T4** | **تموضع سياسات التفويض (3.4 Policies in HTTP layer)**<br>- تموضع `authorize()` والسياسات في طبقة HTTP (Controllers)<br>- إنشاء سياسات مستقلة لكافة النماذج المملوكة للمستخدمين (`Enrollment`, `TaskAttempt`, `UserLessonProgress`, `ZoomAttendance`)<br>- تسجيلها رسمياً في `AppServiceProvider` واختبارها | Architectural Hygiene & Least Privilege | ✅ **مكتملة ومحققة** | Backend Architecture | `app/Policies/EnrollmentPolicy.php`<br>`app/Policies/TaskAttemptPolicy.php`<br>`app/Policies/UserLessonProgressPolicy.php`<br>`app/Policies/ZoomAttendancePolicy.php`<br>`app/Providers/AppServiceProvider.php` |
| **P1-T5** | **تقرير خروج المرحلة 1 (Phase 1 Exit Report)**<br>- Tests Added: 95 tests<br>- Total Suite: 260 tests passed, 1,412 assertions<br>- Bugs Found & Fixed: 3 ثغرات وفجوات تم سدها فورياً<br>- Open Risks: صفر مخاطر مفتوحة | Governance Rule 2 & 7 | ✅ **معتمد 100%** | Quality Lead | `docs/tasks/phase-1-auth-and-authz.md` |

---

## 🔍 الأبحاث المرجعية الخمسة (Top 5 Engineering References)
وفقاً للقاعدة الرابعة الحاكمة (`agent.md: Rule 4`):
1. **OWASP API Security Top 10 (2023) - API1:2023 Broken Object Level Authorization (BOLA)**:
   - التحقق الصارم من ملكية الكائن على مستوى قاعدة البيانات أو عبر Laravel Policies قبل استرجاع أو تعديل المورد. تم تطبيق سياسات مخصصة لكافة النماذج المملوكة للمستخدمين.
2. **OWASP API Security Top 10 (2023) - API5:2023 Broken Function Level Authorization (BFLA)**:
   - عزل المسارات الإدارية والخاصة خلف Middleware/Gates صلبة، والتحقق عبر Matrix Dataset شامل لـ 78 مساراً يضمن رفض الطلاب بـ `403 Forbidden` والضيوف بـ `401 Unauthorized`.
3. **OWASP API Security Top 10 (2023) - API2:2023 Broken Authentication**:
   - منع تعداد المستخدمين (User Enumeration) برسائل خطأ موحدة في تسجيل الدخول واستعادة كلمة المرور، وتطهير التوكنات القديمة وإبطال توكن إعادة التعيين فور استخدامه لمرة واحدة (Single-use) مع انتهاء صلاحيته بعد 60 دقيقة.
4. **Pest PHP Documentation - Architectural Testing & Datasets**:
   - استخدام `#[DataProvider]` واختبار معماري تلقائي يفحص `Route::getRoutes()` لضمان تغطية 100% من المسارات، وإفشال الاختبار فوراً في حال إضافة مسار جديد بدون إدراجه في مصفوفة الصلاحيات.
5. **Laravel 11 Authorization & Sanctum Official Documentation**:
   - وضع السياسات حصراً في طبقة HTTP (Controllers و FormRequests)، واستخدام `Gate::before` للأدمن، مع حماية جلسات BFF عبر `HttpOnly`, `Secure`, `SameSite=lax`.

---

## 📝 تقرير تفاصيل المهام المنجزة وأدلة الإثبات (Verification Evidence)

### [P1-T1] 🔑 تحصين واختبار المصادقة (3.1 Authentication)
* **الإجراء المتخذ:**
  - إنشاء ملف فحص شامل `tests/Feature/Auth/PasswordResetTest.php` بـ 7 اختبارات تفصيلية:
    1. التحقق من منع تعداد المستخدمين عند طلب استعادة كلمة المرور (`forgot-password` يُرجع 200 OK دون كشف وجود الإيميل).
    2. إرسال الإشعار والتوليد الآمن للرمز المميز للمستخدم المسجل.
    3. إعادة تعيين كلمة المرور بنجاح، وتحديثها مشفرة في قاعدة البيانات، وإلغاء كافة التوكنات القديمة فورياً لمنع سرقة الجلسة.
    4. التحقق من أن توكن الاستعادة أحادي الاستخدام (Single-use) ويرفض الاستخدام الثاني بـ 422.
    5. التحقق من انتهاء صلاحية التوكن بعد 60 دقيقة عبر `Carbon::setTestNow` ويرفض بـ 422.
    6. رفض التوكن التالف أو المعدل بـ 422.
    7. رفض التوكنات العشوائية والتالفة (Garbage & Malformed Bearer Tokens) على المسارات المحمية بـ 401.
  - التحقق من منع التعيين الجماعي لحقل `role` أثناء التسجيل (`RegisterTest.php` و `RoleAccessTest.php`).
  - فحص كوكيز BFF في `frontend-course/src/features/auth/server/session.ts` والتأكد من ضبط `httpOnly: true`, `secure: true (production)`, `sameSite: "lax"`.
  - فحص متغيرات البيئة في الفرونت إند والتأكد من خلو `NEXT_PUBLIC_*` من أي مفاتيح سرية.
* **دليل التحقق:**
  - `pest tests/Feature/Auth/PasswordResetTest.php`: **7 passed, 21 assertions (100% Green)**.
* **التثبيت الذري (Git Commit):** `02b4150`

---

### [P1-T2] 🛡️ مصفوفة الصلاحيات الشاملة (3.2 Role × Endpoint Matrix)
* **الإجراء المتخذ:**
  - إنشاء `tests/Feature/Auth/RoleEndpointMatrixTest.php` يحتوي على:
    1. **مصفوفة كاملة لـ 78 مساراً**: تغطي كل مسار وميثود مسجل تحت `api/*` مع تحديد الحالة المتوقعة للأدوار الثلاثة: `(guest, student, admin)`.
    2. **اختبار معماري صارم (Architectural Route Coverage Test)**: يستخرج كافة المسارات من `Route::getRoutes()` ويطابقها بالمصفوفة؛ أي مسار جديد يُضاف دون تسجيله في المصفوفة يُفشل الاختبار فوراً.
    3. **فحص BFLA لكل مسار**:
       - الضيف (Guest): يتلقى 401 على كل المسارات المحمية والإدارية، ويسمح له فقط بالمسارات العامة.
       - الطالب (Student): يتلقى قطيعاً `403 Forbidden` على كافة مسارات الإدارة الـ 49، ويُصرّح له في مسارات الطلاب.
       - المدير (Admin): يمتلك صلاحية الوصول الكاملة ولا يُحظر أبداً بـ 401 أو 403.
* **دليل التحقق:**
  - `pest tests/Feature/Auth/RoleEndpointMatrixTest.php`: **79 passed, 421 assertions (100% Green)**.
* **التثبيت الذري (Git Commit):** `1c2b35c`

---

### [P1-T3] & [P1-T4] 🔒 الحماية من BOLA/IDOR وتأسيس سياسات النماذج المملوكة
* **الإجراء المتخذ:**
  - إنشاء 4 سياسات تفويض مستقلة للنماذج المملوكة للمستخدمين:
    1. `app/Policies/EnrollmentPolicy.php` (تمنع وصول أي طالب لاشتراك طالب آخر، وتسمح للأدمن بالإدارة).
    2. `app/Policies/TaskAttemptPolicy.php` (تمنع استعراض محاولات المهام لغير صاحبها).
    3. `app/Policies/UserLessonProgressPolicy.php` (تمنع عرض أو تعديل تقدم الدروس لطالب آخر).
    4. `app/Policies/ZoomAttendancePolicy.php` (تمنع عرض سجل حضور زووم لغير صاحب الحساب).
  - تسجيل كافة السياسات رسمياً في `AppServiceProvider::boot()`.
  - إنشاء ملف فحص شامل `tests/Feature/Auth/BolaProtectionTest.php` يختبر:
    1. منع الطالب A من تعديل الملف الشخصي للطالب B (403 Forbidden).
    2. عزل مسار `my-courses` لمنع تسريب اشتراكات طالب لآخر.
    3. حظر الطالب غير المسجل من مشاهدة دروس الكورس المدفوع (403 Forbidden).
    4. حظر الطالب غير المسجل من استعراض أو تسليم مهام الكورس المدفوع (403 Forbidden).
    5. حظر الطالب غير المسجل من حضور لقاء زووم للكورس المدفوع أو قراءة سجل الحضور (403 Forbidden).
    6. فحص الاستجابة الخام (Raw JSON) للكورس والتأكد التام من عدم تسريب مسارات الفيديو التخزينية أو أسماء القنوات السرية.
    7. فحص الاستجابة الخام لبيانات المستخدم والتأكد من خلوها من حقول `password` أو `remember_token` أو غيرها (BOPLA).
    8. التحقق من فرض سقف الأمان للترقيم (`limit <= 50`) في `CourseController@index`.
    9. التحقق المباشر من سياسات النماذج المملوكة عبر `Gate::forUser`.
* **دليل التحقق:**
  - `pest tests/Feature/Auth/BolaProtectionTest.php`: **9 passed, 33 assertions (100% Green)**.
* **التثبيت الذري (Git Commit):** `916a7bf`

---

## 🎯 تقرير خروج المرحلة الأولى (Phase 1 Exit Report)

| المعيار | المطلوب في خطة الفحص | النتيجة الفعلية المحققة |
| :--- | :--- | :--- |
| **Tests Added** | تغطية 3.1 و 3.2 و 3.3 و 3.4 | **+95 اختباراً جديداً متخصصاً بالأمان والصلاحيات** |
| **Total Test Suite** | جناح اختبارات كامل وGreen | **260 اختباراً اجتازت بنجاح (1,412 assertions)** |
| **Bugs Found** | فحص الثغرات والفجوات الأمنية | 1. غياب اختبارات وتأكيد حماية دورة استعادة كلمة المرور (Password Reset Single-use & Expiry).<br>2. غياب سياسات رسمية مخصصة للنماذج المملوكة للمستخدمين (`Enrollment`, `TaskAttempt`, `UserLessonProgress`, `ZoomAttendance`).<br>3. عدم وجود اختبار معماري آلي يرصد أي مسار API جديد غير محمي. |
| **Bugs Fixed** | سد كافة الثغرات باختبار فاشل أولاً ثم الحل | تم سد وحل كافة النقاط بنسبة 100% مع اختبارات تثبت الحصانة. |
| **Static Analysis** | Larastan Level 5 | **0 errors (100% Green)** |
| **Code Style** | Laravel Pint | **0 errors (100% Green)** |
| **Open Risks** | المخاطر المتبقية | **صفر مخاطر أمنية مفتوحة في طبقة المصادقة والصلاحيات (Zero Known BOLA/BFLA Gaps).** |
| **Exit Status** | اعتماد معيار الخروج | 🟢 **المرحلة الأولى مكتملة ومعتمدة بنجاح للانتقال إلى المرحلة التالية.** |
