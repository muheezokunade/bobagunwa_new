<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Member;
use App\Services\Finance\LedgerQuery;
use App\Services\MemberService;
use App\Support\Audit;
use App\Support\Csv;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,suspended,inactive'], 'origin' => ['nullable', 'in:application,import,manual'], 'account' => ['nullable', 'in:none,pending,active'], 'incomplete' => ['nullable', 'boolean']]);
        $query = Member::with('user:id,member_id,status')->orderBy('membership_number');
        if (! empty($filters['q'])) {
            $q = '%'.$filters['q'].'%';
            $query->where(fn ($w) => $w->where('membership_number', 'like', $q)->orWhere('full_name', 'like', $q)->orWhere('email', 'like', $q)->orWhere('phone', 'like', $q));
        }
        foreach (['status', 'origin'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (($filters['account'] ?? null) === 'none') {
            $query->whereDoesntHave('user');
        } elseif (! empty($filters['account'])) {
            $query->whereHas('user', fn ($u) => $u->where('status', $filters['account'] === 'pending' ? 'pending_activation' : 'active'));
        }
        if (! empty($filters['incomplete'])) {
            $query->where(fn ($w) => $w->whereNull('email')->orWhereNull('phone')->orWhereNull('date_of_birth')->orWhereNull('joined_on'));
        }

        return view('admin.members.index', ['members' => $query->paginate(30)->withQueryString(), 'filters' => $filters]);
    }

    public function show(Request $request, Member $member, LedgerQuery $ledger): View
    {
        $member->load('user.roles', 'application', 'importBatch');
        $duplicates = Member::where('id', '!=', $member->id)->where('name_normalized', $member->name_normalized)->get(['id', 'membership_number', 'full_name']);

        return view('admin.members.show', [
            'member' => $member,
            'duplicates' => $duplicates,
            'history' => AuditEvent::with('actor')->where('subject_type', 'Member')->where('subject_id', $member->id)->latest('id')->limit(50)->get(),
            'summary' => $request->user()->hasPermission('finance.read') ? $ledger->memberSummary($member->id) : null,
        ]);
    }

    public function update(Request $request, Member $member, MemberService $members): RedirectResponse
    {
        $data = $request->validate([
            'version' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'full_name' => ['required', 'string', 'min:3', 'max:190'],
            'preferred_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{7,40}$/'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'ijebu_town' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'joined_on' => ['nullable', 'date_format:Y-m-d'],
            'admin_notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $members->correct($member, $data, (int) $data['version'], $request->user(), $data['reason']);

        return redirect()->route('admin.members.show', $member)->with('status', 'Record updated.');
    }

    public function status(Request $request, Member $member, MemberService $members): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:active,suspended,inactive'], 'reason' => ['required', 'string', 'min:5', 'max:255']]);
        $members->changeStatus($member, $data['status'], $data['reason'], $request->user());

        return redirect()->route('admin.members.show', $member)->with('status', 'Status changed to '.$data['status'].'.');
    }

    public function access(Request $request, Member $member, MemberService $members): RedirectResponse
    {
        $request->validate(['confirm_identity' => ['accepted']], ['confirm_identity.accepted' => 'Confirm that you have verified the member’s identity and contact details.']);
        $link = $members->issueAccess($member, $request->user());

        return redirect()->route('admin.members.show', $member)
            ->with('status', $link['purpose'] === 'activation' ? 'Activation link issued.' : 'Password reset link issued.')
            ->with('one_time_link', ['url' => $link['url'], 'label' => ($link['purpose'] === 'activation' ? 'Activation' : 'Password reset').' link for '.$member->full_name, 'message_id' => $link['message_id'], 'expires' => $link['purpose'] === 'activation' ? 'in '.config('boba.accounts.activation_token_days').' days' : 'in '.config('boba.accounts.reset_token_minutes').' minutes']);
    }

    public function export(Request $request): StreamedResponse
    {
        $request->validate(['status' => ['nullable', 'in:active,suspended,inactive']]);
        Audit::record('members.exported', 'Member register exported', null, ['status' => $request->query('status')]);
        $rows = Member::query()->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->orderBy('membership_number')->cursor()
            ->map(fn (Member $m) => [$m->membership_number, $m->full_name, $m->status, $m->email, $m->phone, $m->city, $m->ijebu_town, $m->origin, $m->joined_on?->toDateString(), Format::date($m->created_at)]);

        return Csv::download('member-register-'.now()->format('Ymd').'.csv', ['Membership number', 'Full name', 'Status', 'Email', 'Phone', 'City', 'Ijebu town', 'Origin', 'Joined', 'Recorded'], $rows);
    }
}
