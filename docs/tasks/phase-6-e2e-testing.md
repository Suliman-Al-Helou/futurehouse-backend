# 🎭 Phase 6: End-to-End Testing (Playwright) — Plan & Implementation

> **تاريخ الإنجاز:** 2026-11-16  
> **الحالة:** مكتمل ومعتمد بنجاح 100% (Certified Green) ✅  
> **بيئة العمل المعتمدة:**  
> - **Backend API:** Laravel 11 (`http://127.0.0.1:8000`) معزول تماماً على قاعدة PostgreSQL 16 المحلية (`courses_testing` المنفذ 5433).
> - **Frontend Web:** Next.js 16.2 App Router / React 19 (`http://localhost:3000`).
> - **محرك الاختبارات:** `@playwright/test` (Headless Chromium مع Traces و Screenshots و Videos عند الإخفاق).
> - **قاعدة التحصين (Prod-Data Guard):** حظر كامل لقاعدة الإنتاج السحابية (Neon)، والاعتماد بنسبة 100% على `courses_testing` و `E2EPlatformSeeder`.

---

## 🎯 مصفوفة التدفقات الخمسة المعتمدة (The 5 Critical E2E Flows)

| # | اسم التدفق (Flow) | مسار الاختبار (Test Spec) | الهدف ومعيار النجاح الفني | وقت التنفيذ | الحالة |
|:---:|:---|:---|:---|:---:|:---:|
| **1** | **Authentication Lifecycle** | `e2e/flow1-auth-lifecycle.spec.ts` | تسجيل حساب جديد فريد ← دخول ← التحقق من كوكي الجلسة المشفر `fh_session` (HttpOnly/SameSite) ← تسجيل الخروج والتطهير الكامل للجلسة. | 4.4s | ✅ نجح (Green) |
| **2** | **Student Core Value** | `e2e/flow2-student-core-value.spec.ts` | تصفح الكتالوج ← فتح تفاصيل كورس تجريبي ← الضغط على بدء التعلم/التسجيل ← فتح صفحة الدرس والتحقق من مشغل الفيديو `.aspect-video`. | 4.6s | ✅ نجح (Green) |
| **3** | **Live Attendance Interaction** | `e2e/flow3-live-attendance.spec.ts` | الانتقال للقاءات المباشرة ← التحويل لعرض اليوم (Day View) ← التحقق من زر الدخول ← تسجيل الحضور والحصول على رابط Zoom مع كود 200 OK. | 3.5s | ✅ نجح (Green) |
| **4** | **Admin Core Flow** | `e2e/flow4-admin-core-flow.spec.ts` | دخول مدير النظام ← إنشاء كورس جديد مع تعيين المدرب والوصف ← فتح شجرة المحتوى وإضافة قسم ← إضافة درس وربط الفيديو بالدرس وعرضه بالواجهة. | 9.8s | ✅ نجح (Green) |
| **5** | **Security Negative Flow (BOLA/Authz)** | `e2e/flow5-security-negative.spec.ts` | محاولة طالب غير مسجل الوصول المباشر لدرس في كورس مدفوع محمي ← التحقق من اعتراض الـ API بـ 403 Forbidden وعرض شاشة الحظر ومنع تشغيل الفيديو ومنع الـ iframe. | 4.0s | ✅ نجح (Green) |

---

## 🏗️ البنية التحتية للاختبارات (Test Harness Setup)

1. **قاعدة البيانات والعزل (`E2EPlatformSeeder.php`):**
   - بيئة معزولة على المنفذ `5433` بقاعدة `courses_testing` دون المساس بقاعدة Neon الإنتاجية نهائياً.
   - تهيئة حسابات اختبار:
     - مدير النظام: `admin@e2e.test`
     - طالب معتمد: `student@e2e.test`
     - متسلل / طالب غير مسجل: `intruder@e2e.test`
   - توفير كورسات تجريبية: كورس مجاني للتسجيل التلقائي، وكورس مدفوع محمي لاختبارات الأمان، ولقاء Zoom نشط في اللحظة الراهنة.

2. **إعدادات Playwright (`playwright.config.ts`):**
   - تم ضبط `workers: 1` لضمان تسلسل العمليات وتفادي تضارب الجلسات وحالات قاعدة البيانات.
   - تفعيل `headless: true` مع التقاط `trace: 'retain-on-failure'` و `screenshot: 'only-on-failure'`.
   - إضافة سكريبت التشغيل في `package.json`: `"test:e2e": "playwright test"`.

3. **المساعدات المشتركة (`e2e/helpers/auth.ts`):**
   - توفير دوال `loginViaUI`, `registerViaUI`, `logoutViaUI`, `expectSessionCookieExists`, `expectSessionCookieCleared`.
   - معالجة تعارض تراكب وضع التطوير في Next.js (`<nextjs-portal>`) وشاشات RTL بتوجيه أحداث النقر المباشر (`dispatchEvent`).

---

## 📊 نتيجة التشغيل الموحد لكامل الحزمة (Full Suite Execution)

```bash
$ cd frontend-course && npx playwright test

Running 5 tests using 1 worker

     1 … should register a new account, login, verify session cookie, and logout
  ✓  1 … register a new account, login, verify session cookie, and logout (4.4s)
     2 … student should be able to view course, access lesson and see video player
  ✓  2 … should be able to view course, access lesson and see video player (4.6s)
     3 … student can view active live session and record attendance
  ✓  3 … student can view active live session and record attendance (3.5s)
     4 … admin should login, create a new course, add section, and attach video to lesson
  ✓  4 … admin should login, create a new course, add section, and attach video to lesson (9.8s)
     5 … unenrolled user/intruder cannot access protected paid lesson directly
  ✓  5 … unenrolled user/intruder cannot access protected paid lesson directly (4.0s)

  5 passed (27.1s)
```

---

## 🔒 معايير الخروج (Exit Criteria Verification)
- [x] التدفقات الخمسة تعمل وتمر بنجاح كامل: `5 E2E flows green`.
- [x] تغطية الأمان السلبي (Negative Flow) والتأكد من منع الوصول غير المصرح (403 Forbidden).
- [x] حماية تامة لبيانات الإنتاج السحابية (Neon) وتطبيق `Prod-Data Guard`.
- [x] تسجيل توثيقي كامل ومفصل لتقرير الإنجاز والدروس المستفادة.
