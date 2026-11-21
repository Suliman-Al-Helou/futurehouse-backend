# Decision table — POST /api/admin/instructors

| ID | Layer | Condition | Code outcome (from reading) | Source |
|----|-------|-----------|-----------------------------|--------|
| D-01 | middleware | unauthenticated user | 401 Unauthorized `{"message": "غير مصرح لك بالوصول، يرجى تسجيل الدخول أولاً"}` | bootstrap/app.php:44-52 |
| D-02 | middleware | authenticated user is student (not admin) | 403 Forbidden `{"message": "ليس لديك الصلاحية للوصول إلى هذا المورد"}` | app/Policies/UserPolicy.php:14-17 |
| D-03 | middleware | authenticated user is admin | passes authorization gate to controller | app/Policies/UserPolicy.php:16 |
| D-04 | validation | all fields valid, with valid student `user_id` | 201 Created, instructor created and linked | app/Http/Controllers/Api/Admin/InstructorController.php:57 |
| D-05 | validation | all fields valid, `user_id` is null (display-only) | 201 Created, display-only instructor created | app/Http/Controllers/Api/Admin/InstructorController.php:57 |
| D-06 | validation | `name` is missing or empty string | 422 Unprocessable Entity, error on `name` | app/Http/Requests/InstructorRequest.php:43 |
| D-07 | validation | `user_id` is non-integer string (e.g. `"abc"`) | 422 Unprocessable Entity, error on `user_id` | app/Http/Requests/InstructorRequest.php:30 |
| D-08 | validation | `user_id` does not exist in `users` table | 422 Unprocessable Entity, error on `user_id` | app/Http/Requests/InstructorRequest.php:31 |
| D-09 | validation | `user_id` belongs to a soft-deleted user | 422 Unprocessable Entity, error on `user_id` | app/Http/Requests/InstructorRequest.php:31 |
| D-10 | validation | `user_id` belongs to a user already linked to another instructor | 422 Unprocessable Entity, error on `user_id` | app/Http/Requests/InstructorRequest.php:32 |
| D-11 | validation | `user_id` belongs to an admin user | 422 Unprocessable Entity, "لا يمكن ربط حساب مسؤول بدور مدرب." | app/Http/Requests/InstructorRequest.php:38-41 |
| D-12 | validation | `courses` contains non-existent course id | 422 Unprocessable Entity, error on `courses.0` | app/Http/Requests/InstructorRequest.php:59 |
| D-13 | service | linking valid student user assigns role | user gets Spatie role `instructor` | app/Services/InstructorService.php:24-26, 103-105 |
| D-14 | service | display-only profile (`user_id = null`) created | role assignment skipped, no user modified | app/Services/InstructorService.php:24 |
| D-15 | service | role `instructor` missing in Spatie roles table | throws `\RuntimeException` | app/Services/InstructorService.php:97-101 |
| D-16 | service | courses passed in payload `courses: [id]` | updates `courses.instructor_id` to new instructor | app/Services/InstructorService.php:84-86 |
| D-17 | side effect | instructor created | flushes `instructors:all` and `instructors:public` cache | app/Services/InstructorService.php:31 |
| D-18 | response | admin requester views response | contains `user_id`, `is_display_only`, and nested `user` (`id`, `name`, `email`) | app/Http/Resources/InstructorResource.php:18-24 |
| D-19 | response | public visitor queries `/api/instructors` | excludes `user_id` and `user` object | app/Http/Resources/InstructorResource.php:18-24 |
