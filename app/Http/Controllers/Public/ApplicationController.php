<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\ApplicationService;
use App\Support\FormGuard;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function __construct(private ApplicationService $applications) {}

    public function create(): View
    {
        return view('public.apply', ['open' => Settings::bool('membership.applications_open'), 'stamp' => FormGuard::stamp()]);
    }

    public function store(Request $request): RedirectResponse
    {
        FormGuard::check($request, 'membership_application');
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'other_names' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{7,40}$/'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'ijebu_town' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'occupation' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'declaration' => ['accepted'],
            'privacy' => ['accepted'],
        ], [
            'date_of_birth.date_format' => 'Enter your date of birth as a valid date.',
            'phone.regex' => 'Enter a valid phone number.',
            'reason.min' => 'Please tell us a little more (at least 20 characters).',
            'declaration.accepted' => 'You must confirm the declaration.',
            'privacy.accepted' => 'You must confirm that you have read the privacy notice.',
        ]);
        [$application, $token] = $this->applications->submit($data);
        // One-time display of the private tracking code; it is never shown again.
        $request->session()->flash('application_receipt', ['reference' => $application->reference, 'token' => $token, 'flagged' => $application->eligibility_status === 'flagged']);

        return redirect()->route('membership.receipt');
    }

    public function receipt(Request $request): View|RedirectResponse
    {
        $receipt = $request->session()->get('application_receipt');
        if (! $receipt) {
            return redirect()->route('membership.status');
        }

        return view('public.apply-receipt', ['receipt' => $receipt]);
    }

    public function statusForm(): View
    {
        return view('public.status', ['application' => null]);
    }

    public function status(Request $request): View
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:40'], 'code' => ['required', 'string', 'max:100']]);
        $application = $this->applications->findForApplicant($data['reference'], $data['code']);
        if (! $application) {
            return view('public.status', ['application' => null, 'notFound' => true]);
        }

        return view('public.status', [
            'application' => $application,
            'events' => $application->events()->whereNotNull('applicant_message')->get(),
            'code' => $data['code'],
        ]);
    }

    public function respond(Request $request): View
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:40'],
            'code' => ['required', 'string', 'max:100'],
            'response' => ['required', 'string', 'min:5', 'max:3000'],
        ]);
        $application = $this->applications->findForApplicant($data['reference'], $data['code']);
        if (! $application) {
            return view('public.status', ['application' => null, 'notFound' => true]);
        }
        $this->applications->applicantRespond($application, $data['response']);
        $application->refresh();

        return view('public.status', [
            'application' => $application,
            'events' => $application->events()->whereNotNull('applicant_message')->get(),
            'code' => $data['code'],
            'responded' => true,
        ]);
    }
}
