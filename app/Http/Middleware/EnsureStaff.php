<?php

namespace App\Http\Middleware;

use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isStaff()) {
            Audit::security('access.denied', $request->user()?->id, ['path' => $request->path(), 'required' => ['staff']]);
            abort(403, 'The administration area is for authorised officers only.');
        }

        return $next($request);
    }
}
