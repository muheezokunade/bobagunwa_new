<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OutboundMessage;
use App\Services\MessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Prepared notifications awaiting delivery through an approved private channel. */
class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');
        $query = OutboundMessage::with('attester')->latest();
        if ($status === 'open') {
            $query->whereIn('status', ['pending', 'failed']);
        } elseif (in_array($status, ['pending', 'failed', 'accepted_by_mail_server', 'delivered_manually', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        return view('admin.messages.index', ['messages' => $query->paginate(30)->withQueryString(), 'status' => $status]);
    }

    public function show(OutboundMessage $message): View
    {
        return view('admin.messages.show', ['message' => $message]);
    }

    public function delivered(Request $request, OutboundMessage $message, MessageService $messages): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:255'], 'confirm' => ['accepted']], ['confirm.accepted' => 'Confirm that you have actually sent this message.']);
        $messages->attestManualDelivery($message, $request->user(), $data['note']);

        return redirect()->route('admin.messages.index')->with('status', 'Delivery recorded.');
    }

    public function cancel(Request $request, OutboundMessage $message, MessageService $messages): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:255']]);
        $messages->cancel($message, $data['note']);

        return redirect()->route('admin.messages.index')->with('status', 'Message cancelled.');
    }
}
