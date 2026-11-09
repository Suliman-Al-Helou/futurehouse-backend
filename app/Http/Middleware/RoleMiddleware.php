<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'غير مصرح لك بالوصول، يرجى تسجيل الدخول أولاً'], 401);
        }

        if (! $user->hasAnyRole($roles)) {
            return response()->json(['message' => 'ليس لديك الصلاحية للوصول إلى هذا المورد'], 403);
        }

        return $next($request);
    }
}
