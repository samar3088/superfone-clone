<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NoBrowserCache;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            NoBrowserCache::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /*
         | A stale CSRF token — a tab left open past the session lifetime —
         | otherwise renders Laravel's bare "419 PAGE EXPIRED" screen. Send the
         | visitor back to the page they were on with an explanation instead.
         */
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() !== 419 || $request->expectsJson()) {
                return $response;
            }

            /*
             | Deliberately do NOT regenerate the token here. Laravel attaches
             | the XSRF-TOKEN cookie inside the CSRF middleware, which has
             | already thrown by this point — rotating the session token would
             | leave the browser holding the old cookie and mismatching forever.
             | The redirect re-renders the page, and that response carries a
             | matching cookie.
             */
            return back()
                ->withInput($request->except('password', 'code', '_token'))
                ->with('error', 'Your session timed out for security. Please try that again.');
        });
    })->create();
