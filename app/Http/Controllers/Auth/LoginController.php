<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use App\Support\Normalize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'password' => ['required', 'string', 'max:200']]);
        $email = Normalize::email($data['email']);
        $user = User::with('member')->where('email', $email)->first();

        // Constant-shape failure: no disclosure of whether the email exists.
        $valid = $user && $user->password && Hash::check($data['password'], $user->password);
        if (! $user || ! $user->password) {
            Hash::check($data['password'], '$2y$12$xyQt4HY0kGv4H4Wo/hn0m.xRQl29uKq53cbrw6bx6mt/U5JnGQZGu');
        }
        if (! $valid) {
            Audit::security('login.failed', $user?->id, ['reason' => $user ? ($user->password ? 'password' : 'not_activated') : 'unknown_email']);

            return back()->withInput(['email' => $data['email']])->withErrors(['email' => 'The email address or password is not correct, or the account has not been activated.']);
        }
        if (! $user->isActive()) {
            Audit::security('login.denied_inactive', $user->id, ['status' => $user->status, 'member_status' => $user->member?->status]);

            return back()->withInput(['email' => $data['email']])->withErrors(['email' => 'This account is not active. Contact the secretariat for help.']);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        Audit::security('login.succeeded', $user->id);

        if ($user->member_id && ! $user->isStaff()) {
            return redirect()->intended(route('portal.dashboard'));
        }

        return redirect()->intended($user->isStaff() ? route('admin.dashboard') : route('portal.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Audit::security('logout', $request->user()?->id);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have signed out.');
    }
}
