<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\Finance\LedgerQuery;
use App\Services\Finance\PaymentService;
use App\Services\UploadService;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', 'in:pending,verified,rejected,reversed'], 'q' => ['nullable', 'string', 'max:100'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);
        $query = Payment::with('member:id,membership_number,full_name', 'recorder:id,name')->latest('received_on')->latest('id');
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['q'])) {
            $q = '%'.$filters['q'].'%';
            $query->where(fn ($w) => $w->where('reference', 'like', $q)->orWhere('external_reference', 'like', $q)->orWhereHas('member', fn ($m) => $m->where('membership_number', 'like', $q)->orWhere('full_name', 'like', $q)));
        }
        if (! empty($filters['from'])) {
            $query->where('received_on', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('received_on', '<=', $filters['to']);
        }

        return view('admin.finance.payments.index', ['payments' => $query->paginate(30)->withQueryString(), 'filters' => $filters]);
    }

    public function create(Request $request): View
    {
        return view('admin.finance.payments.create', [
            'members' => Member::orderBy('membership_number')->get(['id', 'membership_number', 'full_name', 'status']),
            'selected' => $request->integer('member') ?: null,
        ]);
    }

    public function store(Request $request, UploadService $uploads): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'amount' => ['required', 'string', 'max:20'],
            'method' => ['required', 'in:bank_transfer,cash,pos,other'],
            'received_on' => ['required', 'date_format:Y-m-d'],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'payer_name' => ['nullable', 'string', 'max:190'],
            'narrative' => ['nullable', 'string', 'max:255'],
            'confirm_possible_duplicate' => ['nullable', 'boolean'],
            'idempotency_key' => ['required', 'string'],
            'evidence' => ['nullable', 'file', 'max:'.config('boba.uploads.document_max_kb')],
        ]);
        $evidence = $request->hasFile('evidence') ? $uploads->storePrivate($request->file('evidence'), 'payment_evidence', $request->user()) : null;
        try {
            [$payment, $replayed] = $this->payments->record($data, $request->user(), $data['idempotency_key'], $evidence?->id);
        } catch (\Throwable $e) {
            // The payment was refused, so the just-uploaded evidence belongs to nothing: remove it.
            if ($evidence) {
                $uploads->deletePrivate($evidence);
            }
            throw $e;
        }
        if ($replayed && $evidence && $payment->evidence_file_id !== $evidence->id) {
            $uploads->deletePrivate($evidence);
        }

        return redirect()->route('admin.finance.payments.show', $payment)->with('status', $replayed ? 'This payment was already recorded; it was not duplicated.' : 'Payment recorded. It now needs verification before it counts.');
    }

    public function show(Request $request, Payment $payment, LedgerQuery $ledger): View
    {
        $payment->load('member', 'recorder', 'verifier', 'rejecter', 'evidence', 'allocations.obligation.assessment', 'allocations.allocator', 'reversals.creator');
        $allocated = (int) $payment->allocations->where('status', 'active')->sum('amount_kobo');

        return view('admin.finance.payments.show', [
            'payment' => $payment,
            'allocated' => $allocated,
            'available' => $payment->status === 'verified' ? $payment->amount_kobo - $allocated : 0,
            'obligations' => $payment->status === 'verified' ? $ledger->openObligations($payment->member_id)->where('outstanding', '>', 0)->values() : collect(),
        ]);
    }

    public function evidence(Payment $payment, UploadService $uploads): BinaryFileResponse
    {
        abort_unless($payment->evidence, 404);
        Audit::record('payment.evidence_viewed', "Evidence for {$payment->reference} downloaded", $payment);

        return response()->download($uploads->privatePath($payment->evidence), $payment->evidence->original_name, ['Content-Type' => $payment->evidence->mime]);
    }

    public function verify(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255'], 'confirm' => ['accepted']], ['confirm.accepted' => 'Confirm that you have checked the payment against the bank statement or cash record.']);
        $this->payments->verify($payment, $request->user(), $data['note'] ?? null);

        return redirect()->route('admin.finance.payments.show', $payment)->with('status', 'Payment verified. It can now be allocated.');
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        $this->payments->reject($payment, $request->user(), $data['reason']);

        return redirect()->route('admin.finance.payments.show', $payment)->with('status', 'Payment rejected. It will not count towards any balance.');
    }

    public function allocate(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(['amounts' => ['required', 'array'], 'amounts.*' => ['nullable', 'string', 'max:20'], 'idempotency_key' => ['required', 'string']]);
        $kobo = [];
        foreach ($data['amounts'] as $obligationId => $amount) {
            if (trim((string) $amount) !== '') {
                $kobo[(int) $obligationId] = Money::parse($amount);
            }
        }
        [, $replayed] = $this->payments->allocate($payment, $kobo, $request->user(), $data['idempotency_key']);

        return redirect()->route('admin.finance.payments.show', $payment)->with('status', $replayed ? 'These allocations were already recorded.' : 'Allocation recorded.');
    }

    public function reverse(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500'], 'idempotency_key' => ['required', 'string'], 'confirm' => ['accepted']], ['confirm.accepted' => 'Confirm that this payment should be reversed.']);
        [, $replayed] = $this->payments->reversePayment($payment, $data['reason'], $request->user(), $data['idempotency_key']);

        return redirect()->route('admin.finance.payments.show', $payment)->with('status', $replayed ? 'This reversal was already recorded.' : 'Payment reversed. Its allocations were reversed first.');
    }

    public function reverseAllocation(Request $request, PaymentAllocation $allocation): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500'], 'idempotency_key' => ['required', 'string']]);
        [, $replayed] = $this->payments->reverseAllocation($allocation, $data['reason'], $request->user(), $data['idempotency_key']);

        return redirect()->route('admin.finance.payments.show', $allocation->payment_id)->with('status', $replayed ? 'This reversal was already recorded.' : 'Allocation reversed. The amount is available to allocate again.');
    }
}
