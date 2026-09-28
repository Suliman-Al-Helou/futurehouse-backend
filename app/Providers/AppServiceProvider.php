<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        RateLimiter::for('register', fn (Request $r) => 
            Limit::perHour(10)->by($r->ip())
        );

        // 3. حماية عملية تغيير الباسورد الفعلي
        RateLimiter::for('reset-password', function (Request $request) {
            $email = is_string($request->input('email')) ? Str::lower($request->input('email')) : '';
            return Limit::perMinute(5)->by($email.$request->ip());
        });

        // 4. حماية مسار طلب رابط الاستعادة لمنع إغراق السيرفر
        RateLimiter::for('forgot-password', function (Request $request) {
$email = is_string($request->input('email')) ? Str::lower($request->input('email')) : '';
            return Limit::perMinute(3)->by($email.$request->ip());
        });
    }
}
