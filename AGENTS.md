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
   - يجب في بداية كل مهمة فحص مجلد الـ Skills (`/home/suliman/Documents/courses/frontend-course/.agents/skills/`) واقتراح واستدعاء الـ Skill المناسبة فوراً لمنع التشتت والهلوسة والدخول في حلقات مفرغة (Loops).
2. **منع الافتراضات والتخمين تماماً والاعتماد على البحث الحي في الويب:**
   - يُمنع منعاً باتاً افتراض حلول أو واجهات برمجية أو قيود تقنية دون فحص مباشر وبحث دقيق ومحدث في الويب لأحدث التوثيقات والممارسات.
3. **البحث المعماري عن أفضل الممارسات المتبعة (Industry Best Practices):**
   - قبل تصميم أو بناء أي Feature، يجب البحث عن أفضل الطرق الهندسية القياسية عالمياً لبنائها بأبسط شكل ممكن وأعلى جودة.
4. **البدء بالحد الأدنى الصارم للمكون (Minimalist Surface Rule):**
   - ابدأ دائماً بأبسط وأقل شكل ممكن للخدمة أو نقطة النهاية (Minimalist Surface). يُمنع منعاً باتاً ابتكار طبقات إضافية أو تعقيدات لم تنص عليها المتطلبات الصريحة.

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
7. **Symmetric Testing**: Every validation rule must be tested on BOTH Create (`POST`) and Update (`PUT/PATCH`) endpoints.
8. **Self-Review**: Run `git diff` and review your own changes for security flaws before reporting completion.
