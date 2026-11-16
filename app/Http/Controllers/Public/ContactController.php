<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\EnquiryService;
use App\Support\FormGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('public.contact', ['stamp' => FormGuard::stamp()]);
    }

    public function store(Request $request, EnquiryService $enquiries): RedirectResponse
    {
        FormGuard::check($request, 'contact');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{7,40}$/'],
            'topic' => ['required', 'in:membership,events,partnership,general'],
            'subject' => ['required', 'string', 'max:190'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Please confirm that the secretariat may use your details to reply.']);
        $enquiry = $enquiries->submit($data);

        return redirect()->route('contact')->with('enquiry_reference', $enquiry->reference);
    }
}
