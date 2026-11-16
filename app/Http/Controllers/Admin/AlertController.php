<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrityCheckRun;
use App\Models\SystemAlert;
use App\Services\IntegrityMonitor;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function __construct(private IntegrityMonitor $monitor) {}

    public function index(): View
    {
        return view('admin.alerts.index', [
            'alerts' => SystemAlert::orderByRaw("FIELD(status, 'open', 'acknowledged', 'resolved')")->latest('id')->paginate(25),
            'runs' => IntegrityCheckRun::with('requester:id,name')->latest('id')->limit(15)->get(),
            'emailReady' => $this->monitor->emailChannelReady(),
            'emailEnabled' => Settings::bool('alerts.email_enabled'),
            'emailVerified' => Settings::get('alerts.email_verified'),
            'mailer' => config('mail.default'),
            'recipients' => $this->monitor->recipients(),
        ]);
    }

    public function show(SystemAlert $alert): View
    {
        return view('admin.alerts.show', ['alert' => $alert->load('notifications.user:id,name', 'receipts.user:id,name', 'acknowledger:id,name', 'resolver:id,name')]);
    }

    public function check(Request $request): RedirectResponse
    {
        $run = $this->monitor->run('manual', $request->user());

        return redirect()->route('admin.alerts.index')->with('status', $run->status === 'passed' ? 'Integrity check passed.' : 'Integrity check '.$run->status.'. See the alert below.');
    }

    public function acknowledge(Request $request, SystemAlert $alert): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $this->monitor->acknowledge($alert, $request->user(), $data['note']);

        return redirect()->route('admin.alerts.show', $alert)->with('status', 'Acknowledged. The alert stays visible until a later check passes and it is resolved.');
    }

    public function resolve(Request $request, SystemAlert $alert): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $this->monitor->resolve($alert, $request->user(), $data['note']);

        return redirect()->route('admin.alerts.show', $alert)->with('status', 'Alert resolved.');
    }

    public function emailTest(Request $request): RedirectResponse
    {
        $this->monitor->sendChannelTest($request->user());

        return redirect()->route('admin.alerts.index')->with('status', 'A verification code was handed to the mail server for '.$request->user()->email.'. Enter it below once it arrives.');
    }

    public function emailVerify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $this->monitor->confirmChannelTest($request->user(), $data['code']);

        return redirect()->route('admin.alerts.index')->with('status', 'Alert email channel verified.');
    }
}
