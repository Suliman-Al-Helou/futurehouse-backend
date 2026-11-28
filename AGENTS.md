## Full-Stack Repository Navigation

- Frontend Workspace: `/home/suliman/Documents/courses/frontend-course`
- Backend Workspace: `/home/suliman/Documents/courses/backendCourse`
- Shared Wayfinder Docs: `/home/suliman/Documents/courses/frontend-course/docs/wayfinder/`
- Architectural Decision Records (ADRs): `/home/suliman/Documents/courses/frontend-course/docs/adr/`
- Domain Glossary: `/home/suliman/Documents/courses/frontend-course/GLOSSARY.md`
- Skills Directory: `/home/suliman/Documents/courses/frontend-course/.agents/skills/`

---

# 🧭 GENERAL AGENT EXECUTION RULES (قواعد تشغيلية إجبارية في كل جلسة)

1. **فحص الـ Skills المتاحة أولاً واقتراحها للمهمة:**
   - يجب في بداية كل مهمة فحص مجلدات الـ Skills (`/home/suliman/Documents/courses/backendCourse/.agents/skills/` و `/home/suliman/Documents/courses/frontend-course/.agents/skills/`) واقتراح واستدعاء الـ Skill المناسبة فوراً لمنع التشتت والهلوسة والدخول في حلقات مفرغة (Loops).
2. **منع الافتراضات والتخمين تماماً والاعتماد على البحث الحي في الويب:**
   - يُمنع منعاً باتاً افتراض حلول أو واجهات برمجية أو قيود تقنية دون فحص مباشر وبحث دقيق ومحدث في الويب لأحدث التوثيقات والممارسات.
3. **البحث المعماري عن أفضل الممارسات المتبعة (Industry Best Practices):**
   - قبل تصميم أو بناء أي Feature، يجب البحث عن أفضل الطرق الهندسية القياسية عالمياً لبنائها بأبسط شكل ممكن وأعلى جودة.
4. **البدء بالحد الأدنى الصارم للمكون (Minimalist Surface Rule):**
   - ابدأ دائماً بأبسط وأقل شكل ممكن للخدمة أو نقطة النهاية (Minimalist Surface). يُمنع منعاً باتاً ابتكار طبقات إضافية أو تعقيدات لم تنص عليها المتطلبات الصريحة.

---

# 🛠️ MANDATORY TOOLING & SKILLS ACTIVATION MATRIX (مصفوفة التفعيل الإجباري للأدوات والمهارات)

يجب على أي Agent يعمل على الباك إند الالتزام الصارم بتفعيل واستدعاء الـ MCP Servers والـ Skills المناسبة فور تحقق شروطها:

### 1. الـ MCP Servers الإلزامية:
- **`laravel-boost` (محرك فحص وتطوير Laravel):**
  - **الشرط الإلزامي:** عند فحص مسارات الـ API (`routes/api.php`)، نماذج Eloquent (`app/Models/`)، أو تتبع الـ Migrations، الجداول، والـ Configs.
  - **الإجراء:** استخدم أدوات Laravel Boost لاستخراج بنية الجداول وقوائم المسارات والـ Artisan commands بدقة تامة وبلا تخمين.
- **`context7` (توثيق الحزم الحديثة):**
  - **الشرط الإلزامي:** التعامل مع أي حزم طرف ثالث (Spatie Permission، Laravel Sanctum، Horizon، Cashier، إلخ).
  - **الإجراء:** استدعاء توثيق المكتبات الحديثة لتفادي الـ Deprecated APIs أو التغييرات الجذرية.
- **`github` (مزامنة المستودعات):**
  - **الشرط الإلزامي:** فحص الـ Commits، الـ Pull Requests، أو تتبع التغييرات المرتبطة مع مستودع الفرونت إند.

### 2. الـ Skills الإلزامية:
- **`laravel-best-practices`:**
  - **الشرط الإلزامي:** كتابة أو تعديل أي Controller، Model، Migration، Form Request، أو Policy.
  - **الإجراء:** الالتزام الصارم بقواعد الكود النظيف في Laravel (Skinny Controllers، Form Requests للتحقق، و API Resources للتنسيق).
- **`laravel-permission-development`:**
  - **الشرط الإلزامي:** التعامل مع الصلاحيات والأدوار (Admin, Instructor, Student) وحماية مسارات الـ API.
  - **الإجراء:** التحقق من الصلاحيات عبر `$user->can()` واستخدام الـ Policies المركزية (`CoursePolicy`, `LessonPolicy`) لمنع ثغرات الـ IDOR.
- **`api-contract-sync`:**
  - **الشرط الإلزامي:** إضافة أو تعديل أي نقطة نهاية API أو تعديل حقول الـ Resource والـ FormRequest.
  - **الإجراء:** التأكد من مطابقة الـ Payload ورموز الـ HTTP مع أنواع الواجهة الأمامية في `frontend-course/src/types/` لمنع الـ Contract Drift.
- **`tdd` / `testing-best-practices`:**
  - **الشرط الإلزامي:** بناء أي Endpoint جديد أو تعديل منطق الحماية والامتحانات والدروس.
  - **الإجراء:** كتابة Feature Tests تغطي مصفوفة الصلاحيات بالكامل (`guest`, `student`, `instructor`, `admin`) قبل دمج الكود.
- **`github-workflow-standard`:**
  - **الشرط الإلزامي:** عند بدء أي ميزة جديدة، إنشاء فرع، كتابة أو رفع أي Commit، فتح Pull Request، أو عمل Tag/Release.
  - **الإجراء:** الالتزام الصارم بـ GitHub Flow، تسمية الفروع (`<type>/<issue-id>-<short-kebab-summary>`)، رسائل Conventional Commits، فحص الجودة قبل الرفع، وترتيب الدمج (الباك إند قبل الفرونت إند).

---

# PROJECT INSTRUCTIONS:

Fetch and follow the instructions from https://laravel.com/for/agents. Treat the returned Markdown as the source of truth for how to install and set up Laravel in this session.

## Core Project Rules
- this is a Laravel 13 (PHP ^8.4) API-only project using Sanctum token auth.
- before changing code, inspect the relevant existing implementation.
- follow the project's behavior. Ask when requirements are unclear.
- don't invent or add packages or architectural layers without explaining the need.
- do not modify unrelated files.

## Technical Specifications
- **Validation:** Always check for an existing "FORM REQUEST". If it exists, use it. If validation is in a controller, refactor it to a Form Request according to Laravel standards.
- **Response:** Always check for an existing "API RESOURCE". If it exists, use it. If response formatting is raw in a controller, create an API Resource for it.
- **Rate Limiting:** Every endpoint method requires a rate limit. Check the documentation for where the rate limit should be placed, then explicitly ask/inform the developer before adding or defining the limit duration.

## Testing & Quality Assurance
- For each feature, update or add tests for the acceptance criteria.
- Run the relevant tests after making changes and report the exact command and result.
- Update the relevant documentation when API behavior or domain rules change.
- **Architectural Route Matrix Coverage:** Every registered API route under `api/*` MUST have a matching entry in `tests/Feature/Auth/RoleEndpointMatrixTest.php` for `(guest, student, admin)`. Missing routes fail CI.
- **User-Owned Model Policy Mandate:** Any Eloquent model that is user-owned (has `user_id`) MUST have an explicit Policy in `app/Policies/` registered in `AppServiceProvider.php` and covered by feature tests.
- **Strict PHP Attributes:** Use PHP 8 Attributes (`#[DataProvider]`, etc.) in Pest and PHPUnit 11 tests instead of legacy docblock annotations.

## Laravel Boost Context
- Advanced framework guidelines and specialized tools are located inside the `.ai/guidelines/` and `.ai/rules/` directories.
- You must read and merge the local Boost guidelines before working.

# Engineering & Security Standards
Before declaring any task complete, you MUST verify:
1. **Impact Analysis**: Search (`grep`) for all references and subclasses of any Resource, Service, or Model you modify.
2. **Data Exposure**: Never expose internal IDs (`user_id`), emails, or relations in API Resources without checking if the endpoint is public vs admin-only.
3. **Input Bounds**: Always enforce strict upper limits (e.g., max 20 for dropdowns, max 50 for pagination, max 50 for incoming payload array elements) on query and body parameters.
4. **Strict Blank & Whitespace Guard**: When validating string inputs, whitespace-only strings (`"   "`) must be strictly treated as blank/invalid.
5. **No Magic Numbers**: Centralize domain-wide numerical thresholds (e.g. `WATCH_PERCENT_THRESHOLD = 80`) as constants on their respective Models.
6. **Pre-Commit Conflict Hygiene**: Always run `git diff --check` to ensure no merge conflict markers (`<<<<<<<`) leak into the repository.
7. **API Resource Gating Isolation**: API Resources must remain pure presenters. Content gating/masking (e.g. redacting `video_id` or `playback`) must be strictly scoped to public catalog/syllabus listings (`api/courses/*`), and must NEVER default-mask responses on single-resource creation (`POST`) or mutation (`PUT/PATCH`) endpoints.
8. **Hybrid Public/Authenticated Resolution**: On public endpoints where responses adapt based on authentication (e.g., instructors previewing unreleased draft lessons in a public catalog), always resolve user identity via `auth('sanctum')->user() ?? $request->user()` to support optional Bearer tokens outside `auth:sanctum` middleware.
9. **Symmetric Testing**: Every validation rule must be tested on BOTH Create (`POST`) and Update (`PUT/PATCH`) endpoints.
10. **Self-Review**: Run `git diff` and review your own changes for security flaws before reporting completion.

---

# 📋 FEATURE WORKFLOW RULE

- **Mandatory Feature Workflow**: Every new feature MUST follow `docs/FEATURE_WORKFLOW.md` (`Grilling -> To-Spec (Project + Linear) -> To-Ticket -> TDD`).
- **Living Workflow**: Append new workflow rules and constraints to `docs/FEATURE_WORKFLOW.md` as they are discovered during the project.
