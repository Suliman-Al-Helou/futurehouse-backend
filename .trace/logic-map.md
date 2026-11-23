# Logic map — POST /api/admin/instructors

## 1. Route
- verbs / URI / action: POST /api/admin/instructors -> App\Http\Controllers\Api\Admin\InstructorController@store — routes/api.php:143
- middleware list: api, auth:sanctum, can:manageAdmin,App\Models\User — routes/api.php:108
- binding / where(): none

## 2. Middleware
- auth:sanctum rejects unauthenticated requests with 401 — bootstrap/app.php:44
- can:manageAdmin,App\Models\User rejects non-admin users with 403 — app/Policies/UserPolicy.php:14

## 3. Request class
- authorize returns true — app/Http/Requests/InstructorRequest.php:16
- rules user_id validates nullable integer exists in users and unique on instructors — app/Http/Requests/InstructorRequest.php:28
- rules name is required string max 255 — app/Http/Requests/InstructorRequest.php:43
- prepareForValidation: none
- hooks: none

## 4. Controller branches
- store passes validated data to InstructorService::create — app/Http/Controllers/Api/Admin/InstructorController.php:55
- store returns InstructorResource with 201 Created — app/Http/Controllers/Api/Admin/InstructorController.php:57

## 5. Service / Action branches
- create wraps instructor creation in DB transaction — app/Services/InstructorService.php:21
- create persists instructor model with validated attributes — app/Services/InstructorService.php:22
- create syncs optional assigned courses — app/Services/InstructorService.php:23
- create ensures instructor role assigned when user_id is provided — app/Services/InstructorService.php:25
- create flushes instructors content cache — app/Services/InstructorService.php:31

## 6. Models
- Instructor fillable attributes include user_id — app/Models/Instructor.php:22
- Instructor belongsTo User relation — app/Models/Instructor.php:56
- Instructor isDisplayOnly helper returns true when user_id is null — app/Models/Instructor.php:62
- User soft deleting hook unlinks associated instructor profile user_id — app/Models/User.php:41
- User hasOne Instructor relation — app/Models/User.php:127

## 7. Database
- instructors table has foreign key user_id constrained to users table null on delete — database/migrations/2026_11_18_153000_add_user_id_to_instructors_table.php:14
- instructors table name column is non-nullable string — database/migrations/2026_05_09_134405_create_instructors_table.php:16

## 8. Side effects
- ContentCache forgetInstructors clears cache tags — app/Support/ContentCache.php:38
- events: none
- jobs: none
- mail: none
- notifications: none

## 9. Authorization
- UserPolicy manageAdmin requires user to be admin — app/Policies/UserPolicy.php:14

## 10. Response
- InstructorResource exposes user_id and is_display_only conditionally for admin — app/Http/Resources/InstructorResource.php:25
- store returns 201 Created on success — app/Http/Controllers/Api/Admin/InstructorController.php:57
