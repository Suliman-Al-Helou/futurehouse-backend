<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

// ─── Health ──────────────────────────────────────────────
Route::get('/health', fn () => response()->json(['status' => 'ok']));

// ─── Auth (Public) ────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
});

// ─── Courses (Public) ─────────────────────────────────────
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{course}', [CourseController::class, 'show']);

// ─── Protected ────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::post('/courses/{course}/enroll', [EnrollmentController::class, 'enroll']);
    Route::get('/courses/{course}/enrollment', [EnrollmentController::class, 'checkEnrollment']);
    Route::get('/my-courses', [EnrollmentController::class, 'myCourses']);

    Route::get('/lessons/{id}/progress', [ProgressController::class, 'show']);
    Route::post('/lessons/{id}/progress', [ProgressController::class, 'update']);
    Route::get('/courses/{course}/progress-summary', [ProgressController::class, 'courseSummary']);
    Route::delete('/courses/{course}/enroll', [EnrollmentController::class, 'unenroll']);
    Route::get('/lessons/{id}/task', [TaskController::class, 'show']);
    Route::post('/lessons/{id}/task', [TaskController::class, 'submit']);
});

// ─── Admin ────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/stats', [AdminController::class, 'stats']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::get('/students', [AdminController::class, 'students']);
    Route::get('/enrollments', [AdminController::class, 'enrollments']);
    Route::patch('/enrollments/{enrollment}', [AdminController::class, 'updateEnrollment']);

    // Courses
    Route::get('/courses', [AdminController::class, 'courses']);
    Route::post('/courses', [AdminController::class, 'storeCourse']);
    Route::put('/courses/{course}', [AdminController::class, 'updateCourse']);
    Route::delete('/courses/{course}', [AdminController::class, 'deleteCourse']);
    Route::get('/courses/{course}/sections', [AdminController::class, 'getSections']);
    Route::post('/courses/{course}/sections', [AdminController::class, 'storeSection']);

    // Sections
    Route::delete('/sections/{section}', [AdminController::class, 'deleteSection']);
    Route::post('/sections/{section}/lessons', [AdminController::class, 'storeLesson']);

    // Lessons
    Route::put('/lessons/{lesson}', [AdminController::class, 'updateLesson']);
    Route::delete('/lessons/{lesson}', [AdminController::class, 'deleteLesson']);

    // Tasks
    Route::get('/lessons/{lesson}/task', [AdminController::class, 'getTask']);
    Route::post('/lessons/{lesson}/task', [AdminController::class, 'storeTask']);
    Route::put('/lessons/{lesson}/task', [AdminController::class, 'updateTask']);

    Route::get('/instructors', [AdminController::class, 'instructors']);
    Route::post('/instructors', [AdminController::class, 'storeInstructor']);
    Route::put('/instructors/{instructor}', [AdminController::class, 'updateInstructor']);
    Route::delete('/instructors/{instructor}', [AdminController::class, 'deleteInstructor']);
});
