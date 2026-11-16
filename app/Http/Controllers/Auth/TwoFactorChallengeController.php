<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public const MAX_ATTEMPTS = 5;

    public function show(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get('two_factor.login');
        if (! $pending || $pending['expires'] < time()) {
            $request->session()->forget('two_factor.login');

            return redirect()->route('login')->withErrors(['email' => 'Please sign in again.']);
        }

        return view('auth.two-factor');
    }

    public function verify(Request $request, TwoFactorService $twoFactor, LoginController $login): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $pending = $request->session()->get('two_factor.login');
        if (! $pending || $pending['expires'] < time()) {
            $request->session()->forget('two_factor.login');

            return redirect()->route('login')->withErrors(['email' => 'The sign-in took too long. Please sign in again.']);
        }
        $user = User::with('member')->find($pending['user_id']);
        if (! $user || ! $user->isActive()) {
            $request->session()->forget('two_factor.login');

            return redirect()->route('login')->withErrors(['email' => 'This account is not active. Contact the secretariat.']);
        }
        if (! $twoFactor->verify($user, (string) $request->input('code'))) {
            $pending['attempts']++;
            Audit::security('login.two_factor_failed', $user->id, ['attempt' => $pending['attempts']]);
            if ($pending['attempts'] >= self::MAX_ATTEMPTS) {
                $request->session()->forget('two_factor.login');

                return redirect()->route('login')->withErrors(['email' => 'Too many incorrect codes. Please sign in again.']);
            }
            $request->session()->put('two_factor.login', $pending);

            return back()->withErrors(['code' => 'That code is not correct.']);
        }
        $request->session()->forget('two_factor.login');

        return $login->complete($request, $user, true);
    }
}
