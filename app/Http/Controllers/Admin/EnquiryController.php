<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\User;
use App\Services\EnquiryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $query = Enquiry::with('assignee:id,name')->latest();
        if (in_array($status, EnquiryService::STATUSES, true)) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', ['new', 'in_progress']);
        }
        if ($request->query('mine')) {
            $query->where('assigned_to', $request->user()->id);
        }

        return view('admin.enquiries.index', ['enquiries' => $query->paginate(30)->withQueryString(), 'status' => $status]);
    }

    public function show(Enquiry $enquiry): View
    {
        return view('admin.enquiries.show', [
            'enquiry' => $enquiry->load('notes.user', 'assignee'),
            'officers' => User::where('status', 'active')->whereHas('roles', fn ($q) => $q->where('is_staff', true))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Enquiry $enquiry, EnquiryService $enquiries): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', EnquiryService::STATUSES)], 'assigned_to' => ['nullable', 'integer'], 'note' => ['nullable', 'string', 'max:3000']]);
        $enquiries->update($enquiry, $request->user(), $data['status'], $data['assigned_to'] ?? null, $data['note'] ?? null);

        return redirect()->route('admin.enquiries.show', $enquiry)->with('status', 'Enquiry updated.');
    }
}
