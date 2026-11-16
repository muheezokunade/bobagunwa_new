<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** When enforcement is switched on, privileged officers must enrol before using administration. */
class EnsureTwoFactorEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && TwoFactorService::enforced() && ! $user->two_factor_confirmed_at && TwoFactorService::isPrivileged($user)
            && ! $request->routeIs('admin.account', 'admin.account.*')) {
            return redirect()->route('admin.account')->withErrors(['domain' => 'Your role requires two-factor sign-in. Set it up below to continue.']);
        }

        return $next($request);
    }
}
