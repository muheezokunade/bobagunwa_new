<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\RecoveryRequest;
use App\Models\User;
use App\Services\AccountService;
use App\Support\Audit;
use App\Support\Normalize;
use App\Support\Settings;
use App\Support\Tokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(private AccountService $accounts) {}

    /**
     * The emailed/delivered link carries the token once. It is moved into the
     * encrypted server-side session and the browser is redirected to a clean
     * URL, so the token is not kept in history, page source or Referer.
     */
    public function activateLink(Request $request, string $token): RedirectResponse
    {
        $request->session()->put('account.activation_token', $token);

        return redirect()->route('account.activate.form');
    }

    public function activateForm(Request $request): View
    {
        $token = (string) $request->session()->get('account.activation_token');
        $record = $token !== '' ? $this->accounts->findUsableToken($token, 'activation') : null;

        return view('auth.activate', ['valid' => (bool) $record, 'user' => $record?->user, 'signedInAs' => Auth::user()]);
    }

    public function activate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'confirmed', 'max:128'],
        ]);
        if (Auth::check()) {
            // Activating would silently replace the signed-in account. The link is not used up.
            return redirect()->route('account.activate.form')->withErrors(['domain' => 'You are signed in to another account. Sign out first, then open the activation link again.']);
        }
        $user = $this->accounts->activate((string) ($data['token'] ?? $request->session()->get('account.activation_token')), $data['password']);
        $request->session()->forget('account.activation_token');
        Auth::login($user);
        // true = delete the old session record too, so a copied old cookie stops working.
        $request->session()->regenerate(true);

        return redirect()->to($user->member_id ? route('portal.dashboard') : route('admin.dashboard'))->with('status', 'Your account is active. Welcome.');
    }

    public function recoverForm(): View
    {
        return view('auth.recover');
    }

    /**
     * Enumeration-safe recovery. With self-service email enabled, a reset link
     * is emailed to a known address; otherwise the request joins the officer
     * queue for identity verification and private delivery.
     */
    public function recover(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $email = Normalize::email($data['email']);
        $user = User::with('member')->where('email', $email)->first();

        $recent = RecoveryRequest::where('identifier_hash', Tokens::hash($email))->where('created_at', '>', now()->subHour())->exists();
        if (! $recent) {
            $request_ = RecoveryRequest::create([
                'identifier_hash' => Tokens::hash($email),
                'user_id' => $user?->id,
                'status' => $user ? 'pending' : 'dismissed',
                'requested_ip_hash' => Audit::ipHash(),
                'note' => $user ? null : 'No matching account',
            ]);
            Audit::security('recovery.requested', $user?->id, ['matched' => (bool) $user]);
            if ($user && $user->isActive() && Settings::bool('accounts.self_service_reset')) {
                $link = $this->accounts->issue($user, 'password_reset', 'self_service', null);
                $message = $this->accounts->prepareLinkMessage($user, 'password_reset', $link['url'], $link['record']);
                if ($message->status === 'accepted_by_mail_server') {
                    $request_->update(['status' => 'issued', 'handled_at' => now(), 'note' => 'Emailed automatically']);
                }
            }
        }

        return redirect()->route('password.request')->with('status', 'If an account matches that email address, the secretariat will help you regain access. For your security, an officer may contact you to confirm your identity before sending a private reset link.');
    }

    public function resetLink(Request $request, string $token): RedirectResponse
    {
        $request->session()->put('account.reset_token', $token);

        return redirect()->route('password.reset.form');
    }

    public function resetForm(Request $request): View
    {
        $token = (string) $request->session()->get('account.reset_token');

        return view('auth.reset', ['valid' => $token !== '' && $this->accounts->findUsableToken($token, 'password_reset')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'confirmed', 'max:128'],
        ]);
        $this->accounts->resetPassword((string) ($data['token'] ?? $request->session()->get('account.reset_token')), $data['password']);
        $request->session()->forget('account.reset_token');

        return redirect()->route('login')->with('status', 'Your password has been changed. Sign in with your new password. Other sessions have been signed out.');
    }
}
