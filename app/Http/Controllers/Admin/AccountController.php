<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AccountService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Password change and session list for any officer, including those without a member record. */
class AccountController extends Controller
{
    public function show(Request $request): View
    {
        return view('admin.account', [
            'sessions' => DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get(['id', 'user_agent', 'last_activity']),
            'current' => $request->session()->getId(),
        ]);
    }

    public function twoFactorStart(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $setup = $twoFactor->begin($request->user());

        return redirect()->route('admin.account')->with('two_factor_setup', $setup);
    }

    public function twoFactorConfirm(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $codes = $twoFactor->confirm($request->user(), $data['code']);

        return redirect()->route('admin.account')->with('status', 'Two-factor sign-in is now on.')->with('recovery_codes', $codes);
    }

    public function twoFactorRecovery(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $codes = $twoFactor->regenerateRecoveryCodes($request->user(), $data['code']);

        return redirect()->route('admin.account')->with('status', 'New recovery codes created; the old ones no longer work.')->with('recovery_codes', $codes);
    }

    public function twoFactorDisable(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string'], 'code' => ['required', 'string', 'max:20']]);
        $twoFactor->disable($request->user(), $data['password'], $data['code']);

        return redirect()->route('admin.account')->with('status', 'Two-factor sign-in has been switched off.');
    }

    public function password(Request $request, AccountService $accounts): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'string'], 'password' => ['required', 'string', 'confirmed', 'max:128']]);
        $accounts->changePassword($request->user(), $data['current_password'], $data['password'], $request->session()->getId());
        // true = delete the old session record too, so a copied old cookie stops working.
        $request->session()->regenerate(true);

        return redirect()->route('admin.account')->with('status', 'Your password has been changed and your other sessions have been signed out.');
    }
}
