# Decision table — POST /api/admin/instructors

| ID | Layer | Condition | Code outcome | Source | Probe |
|---|---|---|---|---|---|
| D-01 | middleware | unauthenticated user | 401 Unauthorized | bootstrap/app.php:44 | P-01 |
| D-02 | middleware | authenticated user is student | 403 Forbidden | app/Policies/UserPolicy.php:14 | P-02 |
| D-03 | middleware | authenticated user is admin | passes authorization gate to controller | app/Policies/UserPolicy.php:14 | P-03 |
| D-04 | validation | all fields valid with student user_id | 201 Created and instructor linked | app/Http/Controllers/Api/Admin/InstructorController.php:57 | P-04 |
| D-05 | validation | all fields valid with user_id null | 201 Created display-only instructor | app/Models/Instructor.php:62 | P-05 |
| D-06 | validation | name is empty string | 422 Unprocessable Entity error on name | app/Http/Requests/InstructorRequest.php:43 | P-06 |
| D-07 | validation | user_id is non-integer string | 422 Unprocessable Entity error on user_id | app/Http/Requests/InstructorRequest.php:28 | P-07 |
| D-08 | validation | user_id does not exist in users table | 422 Unprocessable Entity error on user_id | app/Http/Requests/InstructorRequest.php:28 | P-08 |
| D-09 | validation | user_id belongs to soft-deleted user | 422 Unprocessable Entity error on user_id | app/Http/Requests/InstructorRequest.php:28 | P-09 |
| D-10 | validation | user_id already linked to another instructor | 422 Unprocessable Entity error on user_id | app/Http/Requests/InstructorRequest.php:28 | P-10 |
| D-11 | validation | user_id belongs to admin user | 422 Unprocessable Entity error on user_id | app/Http/Requests/InstructorRequest.php:28 | P-11 |
| D-12 | service | linking valid student user assigns role | user receives instructor role | app/Services/InstructorService.php:25 | P-12 |
| D-13 | service | creating display-only profile user_id null | role assignment skipped | app/Services/InstructorService.php:24 | P-13 |
| D-14 | model | soft deleting user nullifies instructor user_id | instructor user_id set to null | app/Models/User.php:41 | P-14 |
| D-15 | cache | creating instructor clears instructor caches | ContentCache forgetInstructors executed | app/Support/ContentCache.php:38 | P-15 |
| D-16 | response | admin requester views response | contains user_id is_display_only and user summary | app/Http/Resources/InstructorResource.php:25 | P-16 |
| D-17 | response | public visitor queries instructors | excludes user_id and user summary | app/Http/Resources/InstructorResource.php:25 | P-17 |
