<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Policies\CoursePolicy;
use App\Policies\LessonPolicy;
use App\Policies\UserPolicy;
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
        RateLimiter::for('reset-password', function (Request $r) {
            $email = is_string($r->input('email')) ? Str::lower($r->input('email')).'.'.$r->ip() : '';

            return Limit::perMinute(5)->by($email.$r->ip());
        });

        // 4. حماية مسار طلب رابط الاستعادة لمنع إغراق السيرفر
        RateLimiter::for('forgot-password', function (Request $r) {
            $email = is_string($r->input('email')) ? Str::lower($r->input('email')).'.'.$r->ip() : '';

            return Limit::perMinute(3)->by($email.$r->ip());
        });
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
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(Lesson::class, LessonPolicy::class);
    }
}
