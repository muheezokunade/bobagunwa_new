<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user?->member_id) {
            return $user?->isStaff() ? redirect()->route('admin.dashboard') : abort(403, 'The member portal is for approved members.');
        }

        return $next($request);
    }
}
