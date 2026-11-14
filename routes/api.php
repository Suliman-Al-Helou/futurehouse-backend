<?php

use App\Http\Controllers\Api\Admin\AdminCourseController;
use App\Http\Controllers\Api\Admin\AdminVideoAssetController;
use App\Http\Controllers\Api\Admin\AdminZoomMeetingController;
use App\Http\Controllers\Api\Admin\EnrollmentController as AdminEnrollmentController;
use App\Http\Controllers\Api\Admin\FaqController as AdminFaqController;
use App\Http\Controllers\Api\Admin\InstructorController as AdminInstructorController;
use App\Http\Controllers\Api\Admin\LessonController as AdminLessonController;
use App\Http\Controllers\Api\Admin\SectionController as AdminSectionController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ZoomMeetingController;
use Illuminate\Support\Facades\Route;

// ─── Health ──────────────────────────────────────────────
Route::get('/health', fn () => response()->json(['status' => 'ok']));

// ─── Auth (Public) ────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])
        ->middleware('throttle:forgot-password');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
        ->middleware('throttle:reset-password');
});
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
});
// ─── Courses (Public) ─────────────────────────────────────
Route::middleware('throttle:courses-public')->group(function () {
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{course}', [CourseController::class, 'show']);
});
Route::get('/instructors', [AdminInstructorController::class, 'index']);
Route::get('/instructors/{id}', [AdminInstructorController::class, 'show']);
Route::get('/faqs', [AdminFaqController::class, 'index']);
// ─── Protected ────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::middleware('throttle:course-enroll')->group(function () {
        Route::post('/courses/{course}/enroll', [EnrollmentController::class, 'enroll']);
        Route::delete('/courses/{course}/enroll', [EnrollmentController::class, 'unenroll']);
    });
    Route::get('/courses/{course}/enrollment', [EnrollmentController::class, 'checkEnrollment']);
    Route::get('/my-courses', [EnrollmentController::class, 'myCourses']);

    Route::middleware('throttle:lesson-progress')->group(function () {
        Route::get('/lessons/in-progress', [ProgressController::class, 'inProgress']);
        Route::get('/lessons/{id}/progress', [ProgressController::class, 'show']);
        Route::post('/lessons/{id}/progress', [ProgressController::class, 'update']);
        Route::get('/courses/{course}/progress-summary', [ProgressController::class, 'courseSummary']);
    });

    Route::get('/lessons/{lesson}/task', [TaskController::class, 'show']);
    Route::post('/lessons/{lesson}/task', [TaskController::class, 'submit'])->middleware('throttle:task-submit');
    Route::get('/lessons/{lesson}', [LessonController::class, 'show']);
    Route::get('/zoom-meetings', [ZoomMeetingController::class, 'index']);
    Route::get('/zoom-meetings/attendance-summary', [ZoomMeetingController::class, 'attendanceSummary']);
    Route::get('/zoom-meetings/attendance', [ZoomMeetingController::class, 'attendanceRecord']);
    Route::post('/zoom-meetings/{zoomMeeting}/attend', [ZoomMeetingController::class, 'attend']);
});

// ─── Admin ────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'can:manageAdmin,App\Models\User'])->prefix('admin')->group(function () {
    Route::get('/stats', [AdminController::class, 'stats']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::get('/students', [AdminController::class, 'students']);
    Route::delete('/users/{user}', [AdminController::class, 'destroyUser']);
    Route::patch('/users/{user}', [AdminController::class, 'updateUserRole']);
    Route::get('/enrollments', [AdminEnrollmentController::class, 'index']);
    Route::post('/enrollments', [AdminEnrollmentController::class, 'store']);
    Route::patch('/enrollments/{enrollment}', [AdminEnrollmentController::class, 'update']);

    // Courses & Sections (Admin)
    Route::middleware('throttle:courses-admin')->group(function () {
        Route::get('/courses', [AdminCourseController::class, 'index']);
        Route::post('/courses', [AdminCourseController::class, 'store']);
        Route::get('/courses/{course}', [AdminCourseController::class, 'show']);
        Route::put('/courses/{course}', [AdminCourseController::class, 'update']);
        Route::delete('/courses/{course}', [AdminCourseController::class, 'destroy']);
        Route::get('/courses/{course}/sections', [AdminSectionController::class, 'index']);
        Route::post('/courses/{course}/sections', [AdminSectionController::class, 'store']);

        // Sections
        Route::delete('/sections/{section}', [AdminSectionController::class, 'destroy']);
        Route::post('/sections/{section}/lessons', [AdminLessonController::class, 'store']);
    });

    // Lessons
    Route::put('/lessons/{lesson}', [AdminLessonController::class, 'update']);
    Route::delete('/lessons/{lesson}', [AdminLessonController::class, 'destroy']);

    // Tasks
    Route::get('/lessons/{lesson}/task', [AdminLessonController::class, 'getTask']);
    Route::post('/lessons/{lesson}/task', [AdminLessonController::class, 'storeTask']);
    Route::put('/lessons/{lesson}/task', [AdminLessonController::class, 'updateTask']);

    Route::get('/instructors', [AdminInstructorController::class, 'index']);
    Route::post('/instructors', [AdminInstructorController::class, 'store']);
    Route::get('/instructors/{id}', [AdminInstructorController::class, 'show']);
    Route::put('/instructors/{instructor}', [AdminInstructorController::class, 'update']);
    Route::delete('/instructors/{instructor}', [AdminInstructorController::class, 'destroy']);
    // FAQs
    Route::get('/faqs', [AdminFaqController::class, 'index']);
    Route::post('/faqs', [AdminFaqController::class, 'store']);
    Route::put('/faqs/{faq}', [AdminFaqController::class, 'update']);
    Route::delete('/faqs/{faq}', [AdminFaqController::class, 'destroy']);

    Route::get('/zoom-meetings', [AdminZoomMeetingController::class, 'index']);
    Route::post('/zoom-meetings', [AdminZoomMeetingController::class, 'store']);
    Route::put('/zoom-meetings/{zoomMeeting}', [AdminZoomMeetingController::class, 'update']);
    Route::delete('/zoom-meetings/{zoomMeeting}', [AdminZoomMeetingController::class, 'destroy']);
    Route::get('/zoom-meetings/{zoomMeeting}/attendances', [AdminZoomMeetingController::class, 'attendances']);
    Route::get('/zoom-meetings/{zoomMeeting}/roster', [AdminZoomMeetingController::class, 'roster']);
    Route::put('/zoom-meetings/{zoomMeeting}/attendances/{user}', [AdminZoomMeetingController::class, 'setAttendance']);

    // Video Assets
    Route::get('/video-assets', [AdminVideoAssetController::class, 'index']);
    Route::post('/video-assets', [AdminVideoAssetController::class, 'store']);
    Route::post('/video-assets/upload-chunk', [AdminVideoAssetController::class, 'uploadChunk']);
    Route::get('/video-assets/{videoAsset}', [AdminVideoAssetController::class, 'show']);
    Route::put('/video-assets/{videoAsset}', [AdminVideoAssetController::class, 'update']);
    Route::delete('/video-assets/{videoAsset}', [AdminVideoAssetController::class, 'destroy']);

    // YouTube Integration
    Route::get('/youtube/status', [AdminVideoAssetController::class, 'youtubeStatus']);
    Route::get('/youtube/auth-url', [AdminVideoAssetController::class, 'youtubeAuthUrl']);
    Route::post('/youtube/callback', [AdminVideoAssetController::class, 'youtubeCallback']);
    Route::post('/youtube/disconnect', [AdminVideoAssetController::class, 'youtubeDisconnect']);
});
