<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\SecureHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Sentry\Laravel\Integration;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->use([
            HandleCors::class,
            SecureHeaders::class,
        ]);
        $middleware->alias([
            'auth' => Authenticate::class,
            'admin' => AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

          $exceptions->shouldRenderJsonWhen(
        fn (Request $r, Throwable $e) => $r->is('api/*') || $r->expectsJson()
    );

    // findOrFail / route-model-binding يتحولان تلقائياً إلى NotFoundHttpException
    $exceptions->render(function (NotFoundHttpException $e, Request $r) {
        if ($r->is('api/*')) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'العنصر غير موجود'], 404);
        }
    });
    })->create();
