@extends('layouts.app', ['area' => 'admin', 'title' => $payment->reference])
@section('content')
@php $u = auth()->user(); @endphp
<x-page-head :title="'Payment '.$payment->reference" :subtitle="$payment->member->membership_number.' · '.$payment->member->full_name">
    <a class="btn secondary" href="{{ route('admin.finance.members.show', $payment->member) }}">Member ledger</a>
</x-page-head>
<div class="grid-2">
    <section class="panel">
        <h2>Receipt details</h2>
        <dl class="details">
            <dt>Status</dt><dd><x-status :value="$payment->status" /></dd>
            <dt>Amount</dt><dd><x-money :kobo="$payment->amount_kobo" /></dd>
            <dt>Received</dt><dd>{{ \App\Support\Format::date($payment->received_on) }} · {{ \App\Support\Format::label($payment->method) }}</dd>
            <dt>Bank / receipt ref.</dt><dd>{{ $payment->external_reference ?: '—' }}</dd>
            @if ($payment->payer_name)<dt>Paid by</dt><dd>{{ $payment->payer_name }}</dd>@endif
            @if ($payment->narrative)<dt>Note</dt><dd>{{ $payment->narrative }}</dd>@endif
            <dt>Origin</dt><dd>{{ \App\Support\Format::label($payment->origin) }}</dd>
            <dt>Recorded by</dt><dd>{{ $payment->recorder->name }} · {{ \App\Support\Format::dateTime($payment->created_at) }}</dd>
            @if ($payment->verifier)<dt>Verified by</dt><dd>{{ $payment->verifier->name }} · {{ \App\Support\Format::dateTime($payment->verified_at) }}@if ($payment->verification_note)<br><span class="small muted">{{ $payment->verification_note }}</span>@endif</dd>@endif
            @if ($payment->rejecter)<dt>Rejected by</dt><dd>{{ $payment->rejecter->name }} · {{ \App\Support\Format::dateTime($payment->rejected_at) }}<br>{{ $payment->rejection_reason }}</dd>@endif
            @if ($payment->evidence)<dt>Evidence</dt><dd><a href="{{ route('admin.finance.payments.evidence', $payment) }}">Download {{ $payment->evidence->original_name }}</a></dd>@endif
        </dl>
    </section>
    <section class="panel">
        <h2>Funds</h2>
        <dl class="stats">
            <div><dt>Amount</dt><dd><x-money :kobo="$payment->amount_kobo" /></dd></div>
            <div><dt>Allocated</dt><dd><x-money :kobo="$allocated" /></dd></div>
            <div><dt>Available to allocate</dt><dd class="value wine"><x-money :kobo="$available" /></dd></div>
        </dl>
        @if ($payment->status === 'pending')
            <p>This payment does not count towards any balance until it is verified.</p>
            @if ($u->hasPermission('finance.payments.verify'))
                @if ($payment->requires_distinct_verifier && $payment->recorded_by === $u->id)
                    <div class="alert warning">You recorded this payment, so a different officer must verify it.</div>
                @else
                    <form method="post" action="{{ route('admin.finance.payments.verify', $payment) }}" data-confirm="Verify this payment of {{ \App\Support\Money::format($payment->amount_kobo) }}?">
                        @csrf
                        <x-field name="note" label="Verification note (optional)" hint="For example the bank statement line checked." />
                        <x-check name="confirm" label="I have checked this payment against the bank statement or cash record." />
                        <button class="btn" type="submit">Verify payment</button>
                    </form>
                    <form method="post" action="{{ route('admin.finance.payments.reject', $payment) }}" class="section-gap" data-confirm="Reject this payment record?">
                        @csrf
                        <x-field name="reason" label="Reason for rejection" required prefix="rej-" />
                        <button class="btn secondary" type="submit">Reject payment</button>
                    </form>
                @endif
            @endif
        @endif
    </section>
</div>

@if ($payment->status === 'verified' && $u->hasPermission('finance.allocate'))
<section class="panel">
    <h2>Allocate to this member’s dues and levies</h2>
    @if ($available <= 0)
        <p class="muted">This payment is fully allocated.</p>
    @elseif ($obligations->isEmpty())
        <p>The member has no outstanding obligations. The amount remains as unallocated credit until a future assessment.</p>
    @else
        <form method="post" action="{{ route('admin.finance.payments.allocate', $payment) }}" data-allocation-form data-available="{{ \App\Support\Money::decimal($available) }}">
            @csrf
            <x-idem />
            <div class="table-wrap"><table>
                <thead><tr><th scope="col">Obligation</th><th scope="col">Date</th><th scope="col" class="num">Outstanding</th><th scope="col">Allocate (₦)</th></tr></thead>
                <tbody>@foreach ($obligations as $o)
                    <tr><td>{{ $o->title }}<br><span class="small muted">{{ $o->reference }} · {{ $o->category }}</span></td><td>{{ \Carbon\Carbon::parse($o->effective_on)->format('j M Y') }}</td><td class="num"><x-money :kobo="$o->outstanding" /></td>
                        <td><label class="sr-only" for="alloc-{{ $o->id }}">Amount to allocate to {{ $o->title }}</label><input class="alloc-input" id="alloc-{{ $o->id }}" type="text" inputmode="decimal" name="amounts[{{ $o->id }}]" value="{{ old('amounts.'.$o->id) }}" data-outstanding="{{ \App\Support\Money::decimal($o->outstanding) }}"></td></tr>
                @endforeach</tbody>
            </table></div>
            <p class="small muted">Allocation order is decided by the officer; the association has not yet approved an automatic priority rule. Any amount left unallocated stays as the member’s credit.</p>
            <div class="actions">
                <button type="button" class="btn secondary" data-fill-oldest hidden>Suggest oldest first</button>
                <button class="btn" type="submit">Record allocation</button>
            </div>
        </form>
    @endif
</section>
@endif

<h2>Allocations</h2>
@if ($payment->allocations->isEmpty())
    <p class="muted">No allocations.</p>
@else
<div class="table-wrap"><table>
    <thead><tr><th scope="col">Obligation</th><th scope="col">By</th><th scope="col">When</th><th scope="col">Status</th><th scope="col" class="num">Amount</th>@if ($u->hasPermission('finance.reverse'))<th scope="col">Reverse</th>@endif</tr></thead>
    <tbody>@foreach ($payment->allocations as $a)
        <tr><td>{{ $a->obligation->assessment->title }} <span class="small muted">{{ $a->obligation->assessment->reference }}</span></td><td>{{ $a->allocator->name }}</td><td>{{ \App\Support\Format::dateTime($a->created_at) }}</td><td><x-status :value="$a->status" /></td><td class="num"><x-money :kobo="$a->amount_kobo" /></td>
            @if ($u->hasPermission('finance.reverse'))
            <td>@if ($a->status === 'active')
                <details><summary>Reverse</summary>
                    <form method="post" action="{{ route('admin.finance.allocations.reverse', $a) }}" data-confirm="Reverse this allocation?">
                        @csrf <x-idem />
                        <x-field name="reason" label="Reason" required :prefix="'a'.$a->id.'-'" />
                        <button class="btn small danger" type="submit">Reverse allocation</button>
                    </form>
                </details>
            @endif</td>
            @endif
        </tr>
    @endforeach</tbody>
</table></div>
@endif

@if ($payment->reversals->isNotEmpty())
<h2>Reversals</h2>
<div class="table-wrap"><table>
    <thead><tr><th scope="col">Reference</th><th scope="col">Type</th><th scope="col">Reason</th><th scope="col">By</th><th scope="col">When</th><th scope="col" class="num">Amount</th></tr></thead>
    <tbody>@foreach ($payment->reversals as $r)
        <tr><td>{{ $r->reference }}</td><td>{{ ucfirst($r->kind) }}</td><td>{{ $r->reason }}</td><td>{{ $r->creator->name }}</td><td>{{ \App\Support\Format::dateTime($r->created_at) }}</td><td class="num"><x-money :kobo="$r->amount_kobo" /></td></tr>
    @endforeach</tbody>
</table></div>
@endif

@if ($payment->status === 'verified' && $u->hasPermission('finance.reverse'))
<form method="post" action="{{ route('admin.finance.payments.reverse', $payment) }}" class="panel" data-confirm="Reverse the whole payment? Its allocations will be reversed first.">
    @csrf <x-idem />
    <h2>Reverse this payment</h2>
    <p>Use only when the payment should never have counted (for example a bounced transfer or a recording error). The original record and all history are kept.</p>
    <x-field name="reason" label="Reason" required prefix="pr-" />
    <x-check name="confirm" label="I confirm this payment should be reversed." prefix="pr-" />
    <button class="btn danger" type="submit">Reverse payment</button>
</form>
@endif
@endsection
