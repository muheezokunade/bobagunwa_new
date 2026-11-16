<?php

namespace App\Http\Middleware;

use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side permission gate: `permission:finance.read` or
 * `permission:members.read|finance.read` (any of).
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();
        $required = explode('|', $permissions);
        if (! $user || ! $user->hasAnyPermission(...$required)) {
            Audit::security('access.denied', $user?->id, ['path' => $request->path(), 'required' => $required]);
            abort(403, 'You do not have permission to open this page.');
        }

        return $next($request);
    }
}
