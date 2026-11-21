# Logic map — POST /api/admin/instructors

Only facts you opened. Format: `fact — file.php:line`. Unknown: `NOT FOUND: <what you searched>`. Nothing there: `none`.

## 1. Route
- verbs / URI / action: `POST /api/admin/instructors` -> `App\Http\Controllers\Api\Admin\InstructorController@store` — `routes/api.php:114`
- middleware list: `api`, `auth:sanctum` (`App\Http\Middleware\Authenticate`), `can:manageAdmin,App\Models\User` (`Illuminate\Auth\Middleware\Authorize`) — `routes/api.php:79`
- binding / where(): none

## 2. Middleware (one line each: what it can reject, status code)
- `auth:sanctum`: rejects unauthenticated requests with HTTP 401 `{"message": "غير مصرح لك بالوصول، يرجى تسجيل الدخول أولاً"}` — `bootstrap/app.php:44-52`
- `can:manageAdmin,App\Models\User`: calls `UserPolicy::manageAdmin` and rejects non-admin users with HTTP 403 `{"message": "ليس لديك الصلاحية للوصول إلى هذا المورد"}` — `app/Policies/UserPolicy.php:14-17`, `bootstrap/app.php:55-61`

## 3. Request class (authorize / rules / prepareForValidation / hooks)
- Class: `App\Http\Requests\InstructorRequest` — `app/Http/Requests/InstructorRequest.php:10`
- `authorize()`: returns `true` — `app/Http/Requests/InstructorRequest.php:17`
- `rules()`:
  - `user_id`: `['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at'), Rule::unique('instructors', 'user_id'), Closure rejecting admin users]` — `app/Http/Requests/InstructorRequest.php:28-42`
  - `name`: `'required|string|max:255'` — `app/Http/Requests/InstructorRequest.php:43`
  - `title`: `'nullable|string|max:255'` — `app/Http/Requests/InstructorRequest.php:44`
  - `bio`: `'nullable|string'` — `app/Http/Requests/InstructorRequest.php:45`
  - `avatar_url`: `'nullable|string|max:2048'` — `app/Http/Requests/InstructorRequest.php:46`
  - `cover_url`: `'nullable|string'` — `app/Http/Requests/InstructorRequest.php:47`
  - `specializations`: `'nullable|string'` — `app/Http/Requests/InstructorRequest.php:48`
  - `years_experience`: `'nullable|integer|min:0'` — `app/Http/Requests/InstructorRequest.php:49`
  - `rating`: `'nullable|numeric|min:0|max:5'` — `app/Http/Requests/InstructorRequest.php:50`
  - `total_reviews`: `'nullable|integer|min:0'` — `app/Http/Requests/InstructorRequest.php:51`
  - `students_count`: `'nullable|integer|min:0'` — `app/Http/Requests/InstructorRequest.php:52`
  - `achievements`: `'nullable|array'` — `app/Http/Requests/InstructorRequest.php:53`
  - `achievements.*`: `'string'` — `app/Http/Requests/InstructorRequest.php:54`
  - `twitter`: `'nullable|string'` — `app/Http/Requests/InstructorRequest.php:55`
  - `linkedin`: `'nullable|string'` — `app/Http/Requests/InstructorRequest.php:56`
  - `youtube`: `'nullable|string'` — `app/Http/Requests/InstructorRequest.php:57`
  - `courses`: `'nullable|array'` — `app/Http/Requests/InstructorRequest.php:58`
  - `courses.*`: `'integer|exists:courses,id'` — `app/Http/Requests/InstructorRequest.php:59`
- `prepareForValidation()`: none
- `after()`: none

## 4. Controller branches
- Calls `$this->instructorService->create($request->validated())` — `app/Http/Controllers/Api/Admin/InstructorController.php:55`
- Returns `response()->json(new InstructorResource($instructor), 201)` — `app/Http/Controllers/Api/Admin/InstructorController.php:57`
- Branches inside method: none (straight pass-through)

## 5. Service / Action branches (exceptions thrown or swallowed, external calls)
- Service: `App\Services\InstructorService::create(array $data)` — `app/Services/InstructorService.php:19-34`
- Wrapped in `DB::transaction()` — `app/Services/InstructorService.php:21`
- Creates instructor: `Instructor::create(Arr::except($data, 'courses'))` — `app/Services/InstructorService.php:22`
- Branch: calls `$this->syncCourses($instructor, $data['courses'] ?? null)` — `app/Services/InstructorService.php:23`:
  - If `$courseIds === null`, returns early without touching courses — `app/Services/InstructorService.php:76-78`
  - If `$courseIds !== null`, sets `instructor_id = null` for omitted courses and links selected courses — `app/Services/InstructorService.php:80-86`
- Branch: `if (! empty($instructor->user_id))` calls `$this->ensureInstructorRole((int) $instructor->user_id)` — `app/Services/InstructorService.php:24-26`:
  - If user not found, returns early — `app/Services/InstructorService.php:93-95`
  - If role `instructor` missing in DB, throws `\RuntimeException` — `app/Services/InstructorService.php:97-101`
  - If user does not have role `instructor`, calls `$user->assignRole($role)` — `app/Services/InstructorService.php:103-105`
- Calls `ContentCache::forgetInstructors()` — `app/Services/InstructorService.php:31`
- Returns `$instructor->load('user')->loadCount('courses')` — `app/Services/InstructorService.php:33`

## 6. Models (fillable/guarded, casts, mutators, scopes, soft deletes, observers, transactions)
- `Instructor` model — `app/Models/Instructor.php`:
  - Fillable: `user_id`, `name`, `title`, `bio`, `avatar_url`, `cover_url`, `specializations`, `years_experience`, `rating`, `total_reviews`, `students_count`, `achievements`, `twitter`, `linkedin`, `youtube` — `app/Models/Instructor.php:20-36`
  - Casts: `years_experience` (integer), `rating` (float), `total_reviews` (integer), `students_count` (integer), `achievements` (array) — `app/Models/Instructor.php:38-44`
  - Mutators / Accessors: none
  - Scopes: `scopeWithCourseStats` — `app/Models/Instructor.php:47-52`
  - Soft deletes: none
  - Observers / events: uses `ClearsCacheOnChange` trait which registers `created`, `updated`, `deleted` model events to flush cache — `app/Models/Instructor.php:13-18`, `app/Models/Concerns/ClearsCacheOnChange.php:13-22`
  - Relations: `user()` (`belongsTo(User::class)`), `courses()` (`hasMany(Course::class, 'instructor_id')`) — `app/Models/Instructor.php:55-70`
- `User` model — `app/Models/User.php`:
  - Soft deletes: uses `SoftDeletes` — `app/Models/User.php:20`
  - Deleting hook: on soft delete, unlinks `Instructor::where('user_id', $user->id)->update(['user_id' => null])` — `app/Models/User.php:34-39`
  - Relations: `instructor()` (`hasOne(Instructor::class)`) — `app/Models/User.php:97-100`

## 7. Database (unique, FK + onDelete, nullable, defaults)
- Table `instructors`:
  - `user_id`: nullable bigint, unique constraint `instructors_user_id_unique`, foreign key constrained to `users.id` with `nullOnDelete()` — `database/migrations/2026_11_18_153000_add_user_id_to_instructors_table.php:14-20`
  - `name`: string, not nullable — `database/migrations/2026_05_09_134405_create_instructors_table.php`
- Table `roles` / `model_has_roles`:
  - Spatie permissions schema for role assignments

## 8. Side effects (events, listeners queued?, jobs, mail, notifications, cache, storage)
- Cache flush: `ContentCache::forgetInstructors()` flushes `instructors:all` and `instructors:public`, calls `FrontendCache::revalidate('instructors')` — `app/Support/ContentCache.php:38-43`
- Events: none
- Queued jobs: none
- Notifications / Mail: none
- File storage: none

## 9. Authorization (policies / gates actually reached)
- Route gate: `can:manageAdmin,App\Models\User` invokes `UserPolicy::manageAdmin` — `app/Policies/UserPolicy.php:14-17`

## 10. Response (shape and status per branch)
- Success (valid payload, admin user): HTTP 201 Created with JSON from `InstructorResource` including `id`, `user_id`, `is_display_only`, `user` (`id`, `name`, `email`), `name`, ..., `courses_count` — `app/Http/Controllers/Api/Admin/InstructorController.php:57`, `app/Http/Resources/InstructorResource.php:12-41`
- Validation error (missing name, invalid user_id, admin user_id, already-linked user_id): HTTP 422 with validation errors JSON
- Unauthenticated request: HTTP 401 with `{"message": "غير مصرح لك بالوصول، يرجى تسجيل الدخول أولاً"}`
- Forbidden request (non-admin user): HTTP 403 with `{"message": "ليس لديك الصلاحية للوصول إلى هذا المورد"}`
