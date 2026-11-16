<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AccountService;
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

    public function password(Request $request, AccountService $accounts): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'string'], 'password' => ['required', 'string', 'confirmed', 'max:128']]);
        $accounts->changePassword($request->user(), $data['current_password'], $data['password'], $request->session()->getId());
        $request->session()->regenerate();

        return redirect()->route('admin.account')->with('status', 'Your password has been changed and your other sessions have been signed out.');
    }
}
