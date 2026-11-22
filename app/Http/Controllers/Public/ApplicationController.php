<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ApplicationAttachment;
use App\Models\MembershipApplication;
use App\Services\ApplicationService;
use App\Services\UploadService;
use App\Support\DateParts;
use App\Support\FormGuard;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ApplicationController extends Controller
{
    public function __construct(private ApplicationService $applications) {}

    public function create(): View
    {
        return view('public.apply', [
            'open' => Settings::bool('membership.applications_open'), 'stamp' => FormGuard::stamp(),
            'requireDocuments' => Settings::bool('membership.require_documents'), 'maritalStatuses' => self::MARITAL_STATUSES,
            'maxMb' => intdiv((int) config('boba.uploads.application_file_max_kb'), 1024),
        ]);
    }

    public const MARITAL_STATUSES = ['single' => 'Single', 'married' => 'Married', 'separated' => 'Separated', 'divorced' => 'Divorced', 'widowed' => 'Widowed'];

    public function store(Request $request, UploadService $uploads): RedirectResponse
    {
        FormGuard::check($request, 'membership_application');
        DateParts::merge($request, 'date_of_birth');
        $required = Settings::bool('membership.require_documents') ? 'required' : 'nullable';
        $maxKb = (int) config('boba.uploads.application_file_max_kb');
        $fileRules = [];
        $attributes = [
            'first_name' => 'first name', 'other_names' => 'middle name', 'last_name' => 'surname', 'date_of_birth' => 'date of birth',
            'phone' => 'mobile number', 'occupation' => 'profession or trade', 'ijebu_town' => 'town of origin', 'city' => 'city of residence',
            'country' => 'country of residence', 'marital_status' => 'marital status', 'profile_summary' => 'profile summary',
        ];
        foreach (ApplicationAttachment::KINDS as $kind => [$label, $isRequired, $multiple, $imagesOnly]) {
            $type = ['file', $imagesOnly ? 'mimes:jpg,jpeg,png' : 'mimes:pdf,jpg,jpeg,png', "max:{$maxKb}"];
            $rule = $isRequired ? $required : 'nullable';
            if ($multiple) {
                $fileRules["attachments.{$kind}"] = [$rule, 'array', 'max:'.ApplicationAttachment::MAX_MULTIPLE];
                $fileRules["attachments.{$kind}.*"] = $type;
                $attributes["attachments.{$kind}.*"] = mb_strtolower($label);
            } else {
                $fileRules["attachments.{$kind}"] = array_merge([$rule], $type);
            }
            $attributes["attachments.{$kind}"] = mb_strtolower($label);
        }
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'other_names' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:80'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{7,40}$/'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'occupation' => ['required', 'string', 'max:120'],
            'ijebu_town' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'marital_status' => ['nullable', 'in:'.implode(',', array_keys(self::MARITAL_STATUSES))],
            'profile_summary' => ['nullable', 'string', 'max:1500'],
            'declaration' => ['accepted'],
            'privacy' => ['accepted'],
        ] + $fileRules, [
            'date_of_birth.date_format' => 'Enter your date of birth as a valid date.',
            'phone.regex' => 'Enter a valid phone number.',
            'declaration.accepted' => 'You must confirm the declaration.',
            'privacy.accepted' => 'You must agree to the data privacy notice.',
            'mimes' => 'The :attribute must be a PDF, JPEG or PNG file.',
            'max.file' => 'The :attribute must be smaller than '.intdiv($maxKb, 1024).' MB.',
        ], $attributes);

        // Store the documents privately first; if anything fails, nothing is kept.
        $stored = [];
        try {
            foreach (ApplicationAttachment::KINDS as $kind => [$label, , $multiple]) {
                $files = $multiple ? ($request->file("attachments.{$kind}") ?? []) : array_filter([$request->file("attachments.{$kind}")]);
                foreach ($files as $file) {
                    $stored[$kind][] = $uploads->storePrivate($file, 'application_'.$kind, null, $maxKb);
                }
            }
            [$application, $token] = $this->applications->submit($data, $stored);
        } catch (Throwable $e) {
            foreach (array_merge([], ...array_values($stored)) as $file) {
                $uploads->deletePrivate($file);
            }
            throw $e;
        }
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

    public function statusForm(Request $request): View
    {
        // After a reply the applicant is redirected here once (no resubmission on refresh).
        $id = $request->session()->get('applicant_reply_application_id');
        $application = $id ? MembershipApplication::find($id) : null;
        if ($application) {
            return view('public.status', [
                'application' => $application,
                'events' => $application->events()->whereNotNull('applicant_message')->get(),
                'code' => null,
                'responded' => true,
            ]);
        }

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

    /** Refresh, back button or bookmark on the reply address: back to the status form, not a 405. */
    public function respondRedirect(): RedirectResponse
    {
        return redirect()->route('membership.status');
    }

    public function respond(Request $request): View|RedirectResponse
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

        return redirect()->route('membership.status')->with('applicant_reply_application_id', $application->id);
    }
}
