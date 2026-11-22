<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountService;
use App\Services\TwoFactorService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Giwa's recovery path (22 Nov 2026 demo): if the System Administrator
 * loses access, the Giwa can issue a one-time password link or reset
 * two-factor sign-in for that administrator. Only accounts that hold
 * access.manage can be targeted, never one's own, and every step is audited.
 */
class AdministratorRecoveryController extends Controller
{
    public function __construct(private AccountService $accounts) {}

    public function index(): View
    {
        $administrators = User::with('roles')->whereHas('roles.permissions', fn ($q) => $q->where('key', 'access.manage'))->orderBy('name')->get();

        return view('admin.administrator-recovery', ['administrators' => $administrators]);
    }

    public function link(Request $request, User $user): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255'], 'confirm_identity' => ['accepted']], ['confirm_identity.accepted' => 'Confirm that you have verified this administrator’s identity in person or by phone.']);
        $this->assertTarget($request, $user);
        $purpose = $user->status === 'pending_activation' ? 'activation' : 'password_reset';
        $link = $this->accounts->issue($user, $purpose, 'admin', $request->user()->id);
        $message = $this->accounts->prepareLinkMessage($user, $purpose, $link['url'], $link['record']);
        Audit::security('access.administrator_recovery_link', $user->id, ['by' => $request->user()->id]);
        Audit::record('access.administrator_recovery', "Administrator access recovery link issued for {$user->name}", $user, ['reason' => $request->input('reason')]);

        return redirect()->route('admin.administrator-recovery.index')->with('one_time_link', [
            'url' => $link['url'], 'label' => 'Recovery link for '.$user->name, 'message_id' => $message->id,
            'expires' => $purpose === 'activation' ? 'in '.config('boba.accounts.activation_token_days').' days' : 'in '.config('boba.accounts.reset_token_minutes').' minutes',
        ]);
    }

    public function twoFactorReset(Request $request, User $user, TwoFactorService $twoFactor): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255'], 'confirm_identity' => ['accepted']], ['confirm_identity.accepted' => 'Confirm that you have verified this administrator’s identity in person or by phone.']);
        $this->assertTarget($request, $user);
        $twoFactor->adminReset($user, $request->user(), $data['reason']);

        return redirect()->route('admin.administrator-recovery.index')->with('status', "Two-factor sign-in reset for {$user->name}. They set it up again at their next sign-in.");
    }

    private function assertTarget(Request $request, User $user): void
    {
        if ($user->id === $request->user()->id) {
            throw new DomainRuleException('You cannot recover your own account here.');
        }
        if (! $user->roles()->whereHas('permissions', fn ($q) => $q->where('key', 'access.manage'))->exists()) {
            throw new DomainRuleException('Only a System Administrator’s access can be recovered here. Other officers are helped by the System Administrator.');
        }
    }
}
