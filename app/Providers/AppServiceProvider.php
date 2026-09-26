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
        RateLimiter::for('auth', function (Request $request) {
            $email = Str::lower($request->input('email'));

            // 6 محاولات بالدقيقة، حسب الـ IP — يكفي لمستخدم حقيقي، وبيوقف brute-force
            return Limit::perHour(3)->by($email.$request->ip());
        });

        RateLimiter::for('reset-password', function (Request $request) {
            $email = Str::lower($request->input('email'));

            return Limit::perMinute(5)->by($email.$request->ip());
        });
        RateLimiter::for('forgot-password', function (Request $request) {
            $email = Str::lower($request->input('email'));

            return Limit::perMinute(3)->by($email.$request->ip());
        });
        RateLimiter::for('login', fn ($r) => Limit::perHour(3)->by(Str::lower($r->email).$r->ip()));
        RateLimiter::for('register', fn ($r) => Limit::perHour(3)->by(Str::lower($r->email).$r->ip()));
    }
}
