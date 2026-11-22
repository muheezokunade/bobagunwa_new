<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\OutboundMessage;
use App\Services\ApplicationService;
use App\Support\Normalize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', 'string', 'max:30'], 'eligibility' => ['nullable', 'in:eligible,flagged'], 'q' => ['nullable', 'string', 'max:100']]);
        $query = MembershipApplication::query()->latest();
        $status = $filters['status'] ?? 'open';
        if ($status === 'open') {
            $query->whereIn('status', MembershipApplication::OPEN_STATUSES);
        } elseif ($status !== 'all' && isset(MembershipApplication::TRANSITIONS[$status])) {
            $query->where('status', $status);
        }
        if (! empty($filters['eligibility'])) {
            $query->where('eligibility_status', $filters['eligibility']);
        }
        if (! empty($filters['q'])) {
            $q = '%'.$filters['q'].'%';
            $query->where(fn ($w) => $w->where('reference', 'like', $q)->orWhere('first_name', 'like', $q)->orWhere('last_name', 'like', $q)->orWhere('email', 'like', $q));
        }

        return view('admin.applications.index', ['applications' => $query->paginate(25)->withQueryString(), 'status' => $status, 'filters' => $filters]);
    }

    public function show(MembershipApplication $application): View
    {
        $possibleMatches = Member::where(fn ($q) => $q->where('email_normalized', $application->email_normalized)
            ->orWhere('phone_normalized', $application->phone_normalized)
            ->orWhere('name_normalized', Normalize::name($application->fullName())))
            // The member created by approving this application is not a duplicate of it.
            ->when($application->member_id, fn ($q) => $q->where('id', '!=', $application->member_id))
            ->limit(5)->get();

        return view('admin.applications.show', [
            'application' => $application->load('events.actor', 'member', 'decider'),
            'matches' => $possibleMatches,
            'messages' => OutboundMessage::where('related_type', 'MembershipApplication')->where('related_id', $application->id)->latest()->get(),
            'otherApplications' => MembershipApplication::where('id', '!=', $application->id)->where(fn ($q) => $q->where('email_normalized', $application->email_normalized)->orWhere('phone_normalized', $application->phone_normalized))->get(),
        ]);
    }

    public function decide(Request $request, MembershipApplication $application, ApplicationService $service): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:under_review,changes_requested,approved,rejected'],
            'private_note' => ['required', 'string', 'max:3000'],
            'applicant_message' => ['nullable', 'string', 'max:3000'],
        ]);
        if ($data['decision'] === 'approved') {
            [$member, $link] = $service->approve($application, $request->user(), $data['private_note'], $data['applicant_message'] ?? null);

            return redirect()->route('admin.applications.show', $application)
                ->with('status', "Approved. Member {$member->membership_number} has been created.")
                ->with('one_time_link', ['url' => $link['url'], 'message_id' => $link['message_id'], 'label' => 'Activation link for '.$member->full_name, 'expires' => 'in '.config('boba.accounts.activation_token_days').' days']);
        }
        $service->transition($application, $data['decision'], $request->user(), $data['private_note'], $data['applicant_message'] ?? null);

        return redirect()->route('admin.applications.show', $application)->with('status', 'Application updated.');
    }
}
