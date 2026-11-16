<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Request correlation id plus security headers on every response.
 * The CSP allows only same-origin resources and no inline script or style.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->instance('request_id', (string) Str::uuid());
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Request-Id', app('request_id'));
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
        }
        // Pages reached from one-time links never send a Referer.
        if ($request->is('account/*', 'account')) {
            $headers->set('Referrer-Policy', 'no-referrer');
        }
        if (config('boba.force_https')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        // Private pages must never be cached by shared caches or the browser.
        if ($request->user() || $request->is('portal*', 'admin*', 'account*', 'login', 'membership/status*', 'membership/receipt')) {
            $headers->set('Cache-Control', 'no-store, private');
            $headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
