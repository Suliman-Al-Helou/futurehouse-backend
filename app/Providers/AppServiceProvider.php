<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\TaskAttempt;
use App\Models\User;
use App\Models\UserLessonProgress;
use App\Models\ZoomAttendance;
use App\Policies\CoursePolicy;
use App\Policies\EnrollmentPolicy;
use App\Policies\InstructorPolicy;
use App\Policies\LessonPolicy;
use App\Policies\TaskAttemptPolicy;
use App\Policies\UserLessonProgressPolicy;
use App\Policies\UserPolicy;
use App\Policies\ZoomAttendancePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // 1. تفعيل الحماية الثنائية الذكية والمعدلة لصفحة تسجيل الدخول (بديلة لـ auth القديمة)
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(Str::lower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(20)->by($r->ip()),
        ]);

        // 2. تفعيل حماية صفحة تسجيل حساب جديد ضد الحسابات الوهمية
        RateLimiter::for('register', fn (Request $r) => Limit::perHour(10)->by($r->ip())
        );

        // 3. حماية عملية تغيير الباسورد الفعلي
        RateLimiter::for('reset-password', fn (Request $r) => Limit::perMinute(5)->by(Str::lower((string) $r->input('email')).'|'.$r->ip()));

        // 4. حماية مسار طلب رابط الاستعادة لمنع إغراق السيرفر
        RateLimiter::for('forgot-password', fn (Request $r) => Limit::perMinute(3)->by(Str::lower((string) $r->input('email')).'|'.$r->ip()));

        // 5. حماية التصفح العام للكورسات (60 بالدقيقة لكل IP)
        // In load-test mode, raise to 10000/min so we measure DB/app perf, not the limiter
        // (Rate-limiting correctness was already proven in Phase 3)
        $publicCourseLimit = config('app.load_test_mode') ? 10000 : 60;
        RateLimiter::for('courses-public', fn (Request $r) => Limit::perMinute($publicCourseLimit)->by($r->ip()));

        // 6. حماية لوحة تحكم الكورسات والأقسام للأدمن (60 بالدقيقة لكل مستخدم)
        RateLimiter::for('courses-admin', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));

        // 7. حماية عمليات التسجيل وإلغاء التسجيل في الكورسات (15 بالدقيقة لكل مستخدم)
        RateLimiter::for('course-enroll', fn (Request $r) => Limit::perMinute(15)->by($r->user()?->id ?: $r->ip()));

        // 8. حماية تتبع تقدم الدروس وملخص الكورس (60 بالدقيقة لكل مستخدم)
        RateLimiter::for('lesson-progress', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));

        // 9. حماية تسليم مهام الدروس (20 بالدقيقة لكل مستخدم)
        RateLimiter::for('task-submit', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->id ?: $r->ip()));

        // 10. حماية تسجيل الحضور في اللقاءات المباشرة (10 بالدقيقة لكل مستخدم)
        $attendanceLimit = config('app.load_test_mode') ? 10000 : 10;
        RateLimiter::for('attendance-submit', fn (Request $r) => Limit::perMinute($attendanceLimit)->by($r->user()?->id ?: $r->ip()));
        // 11. حماية بوابة المدرب ومسارات البروفايل (60 بالدقيقة لكل مستخدم)
        RateLimiter::for('instructor-portal', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));

        DB::listen(function ($query) {
            if ($query->time > 50) {
                Log::info("SLOW QUERY ({$query->time}ms): {$query->sql}");
            }
        });

        Model::preventLazyLoading(! app()->isProduction());

        // Super-admin pre-authorization bypass
        Gate::before(function ($user, string $ability) {
            if ($user instanceof User && $user->hasRole('admin')) {
                return true;
            }

            return null;
        });

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Instructor::class, InstructorPolicy::class);
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(Lesson::class, LessonPolicy::class);
        Gate::policy(Enrollment::class, EnrollmentPolicy::class);
        Gate::policy(TaskAttempt::class, TaskAttemptPolicy::class);
        Gate::policy(UserLessonProgress::class, UserLessonProgressPolicy::class);
        Gate::policy(ZoomAttendance::class, ZoomAttendancePolicy::class);
    }
}
