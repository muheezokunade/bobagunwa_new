<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Enquiry;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\OutboundMessage;
use App\Models\Payment;
use App\Models\RecoveryRequest;
use App\Services\Finance\LedgerQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Work queues for the signed-in officer, limited to what they may see. */
class DashboardController extends Controller
{
    public function index(Request $request, LedgerQuery $ledger): View
    {
        $user = $request->user();
        $tiles = [];
        if ($user->hasPermission('applications.review')) {
            $tiles[] = ['label' => 'Applications awaiting review', 'value' => MembershipApplication::whereIn('status', ['submitted', 'under_review'])->count(), 'route' => route('admin.applications.index', ['status' => 'open'])];
            $tiles[] = ['label' => 'Flagged for eligibility review', 'value' => MembershipApplication::whereIn('status', MembershipApplication::OPEN_STATUSES)->where('eligibility_status', 'flagged')->count(), 'route' => route('admin.applications.index', ['status' => 'open', 'eligibility' => 'flagged'])];
        }
        if ($user->hasAnyPermission('applications.review', 'accounts.issue', 'access.manage')) {
            $tiles[] = ['label' => 'Messages awaiting delivery', 'value' => OutboundMessage::whereIn('status', ['pending', 'failed'])->count(), 'route' => route('admin.messages.index')];
        }
        if ($user->hasPermission('members.read')) {
            $tiles[] = ['label' => 'Active members', 'value' => Member::where('status', 'active')->count(), 'route' => route('admin.members.index', ['status' => 'active'])];
        }
        if ($user->hasPermission('finance.read')) {
            $tiles[] = ['label' => 'Payments awaiting verification', 'value' => Payment::where('status', 'pending')->count(), 'route' => route('admin.finance.payments.index', ['status' => 'pending'])];
            $exceptions = count(array_filter($ledger->exceptions()));
            $tiles[] = ['label' => 'Reconciliation exceptions', 'value' => $exceptions, 'route' => route('admin.finance.reports.index').'#exceptions', 'alert' => $exceptions > 0];
        }
        if ($user->hasPermission('enquiries.manage')) {
            $tiles[] = ['label' => 'New enquiries', 'value' => Enquiry::where('status', 'new')->count(), 'route' => route('admin.enquiries.index', ['status' => 'new'])];
        }
        if ($user->hasPermission('access.manage')) {
            $tiles[] = ['label' => 'Account recovery requests', 'value' => RecoveryRequest::where('status', 'pending')->count(), 'route' => route('admin.access.index').'#recovery'];
        }

        return view('admin.dashboard', [
            'tiles' => $tiles,
            'recent' => $user->hasPermission('audit.read') ? AuditEvent::with('actor')->latest('id')->limit(8)->get() : collect(),
        ]);
    }
}
