<?php

use App\Exceptions\DomainRuleException;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureMember;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(SecurityHeaders::class);
        $middleware->web(append: [EnsureActiveAccount::class]);
        $middleware->alias([
            'permission' => RequirePermission::class,
            'staff' => EnsureStaff::class,
            'member' => EnsureMember::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->member_id ? route('portal.dashboard') : route('admin.dashboard'));
        // Namecheap shared hosting sits behind a proxy layer for TLS.
        $middleware->trustProxies(at: env('BOBA_TRUSTED_PROXIES', '127.0.0.1'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Business-rule refusals return the user to the form with a clear message.
        $exceptions->render(function (DomainRuleException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput($request->except(['password', 'password_confirmation', 'current_password', 'token']))->withErrors(['domain' => $e->getMessage()]);
        });
        $exceptions->dontReport([DomainRuleException::class]);
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'token']);
    })->create();

// Allow the public webroot to live outside the application directory
// (e.g. a cPanel subdomain document root). See docs/09-deployment-runbook.md.
if ($publicPath = env('BOBA_PUBLIC_PATH')) {
    $app->usePublicPath($publicPath);
}

return $app;
