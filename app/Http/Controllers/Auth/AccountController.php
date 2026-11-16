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

    public function activateForm(string $token): View
    {
        $record = $this->accounts->findUsableToken($token, 'activation');

        return view('auth.activate', ['token' => $token, 'valid' => (bool) $record, 'user' => $record?->user]);
    }

    public function activate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'confirmed', 'max:128'],
        ]);
        $user = $this->accounts->activate($data['token'], $data['password']);
        Auth::login($user);
        $request->session()->regenerate();

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

    public function resetForm(string $token): View
    {
        $record = $this->accounts->findUsableToken($token, 'password_reset');

        return view('auth.reset', ['token' => $token, 'valid' => (bool) $record]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'confirmed', 'max:128'],
        ]);
        $this->accounts->resetPassword($data['token'], $data['password']);

        return redirect()->route('login')->with('status', 'Your password has been changed. Sign in with your new password. Other sessions have been signed out.');
    }
}
