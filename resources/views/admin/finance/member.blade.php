@extends('layouts.app', ['area' => 'admin', 'title' => 'Ledger '.$member->membership_number])
@section('content')
@php $u = auth()->user(); @endphp
<x-page-head :title="$member->full_name" :subtitle="'Member ledger · '.$member->membership_number.' · '.ucfirst($member->status)">
    @if ($u->hasPermission('finance.payments.record'))<a class="btn" href="{{ route('admin.finance.payments.create', ['member' => $member->id]) }}">Record a payment</a>@endif
    @if ($u->hasPermission('members.read'))<a class="btn secondary" href="{{ route('admin.members.show', $member) }}">Member record</a>@endif
</x-page-head>
<dl class="stats">
    <div><dt>Effective obligations</dt><dd><x-money :kobo="$summary['effective_obligations']" /></dd></div>
    <div><dt>Allocated</dt><dd><x-money :kobo="$summary['allocated']" /></dd></div>
    <div><dt>Outstanding</dt><dd class="value wine"><x-money :kobo="$summary['outstanding']" /></dd></div>
    <div><dt>Verified payments</dt><dd><x-money :kobo="$summary['verified_payments']" /></dd></div>
    <div><dt>Unallocated credit</dt><dd><x-money :kobo="$summary['unallocated_credit']" /></dd></div>
    <div><dt>Awaiting verification</dt><dd><x-money :kobo="$summary['pending']" /></dd></div>
</dl>

<h2>Obligations</h2>
@if ($obligations->isEmpty())
    <p class="muted">No obligations have been posted to this member.</p>
@else
<div class="table-wrap"><table>
    <thead><tr><th scope="col">Assessment</th><th scope="col">Date</th><th scope="col" class="num">Original</th><th scope="col" class="num">Adjustments</th><th scope="col" class="num">Allocated</th><th scope="col" class="num">Outstanding</th>@if ($u->hasPermission('finance.adjust'))<th scope="col">Adjust</th>@endif</tr></thead>
    <tbody>@foreach ($obligations as $o)
        <tr><td>{{ $o->title }}<br><span class="small muted">{{ $o->reference }} · {{ $o->category }}</span></td><td>{{ \Carbon\Carbon::parse($o->effective_on)->format('j M Y') }}</td>
            <td class="num"><x-money :kobo="$o->amount_kobo" /></td><td class="num"><x-money :kobo="$o->adjustments" /></td><td class="num"><x-money :kobo="$o->allocated" /></td><td class="num"><x-money :kobo="$o->outstanding" /></td>
            @if ($u->hasPermission('finance.adjust'))
            <td><details><summary>Adjust <span class="sr-only">{{ $o->title }}</span></summary>
                <form method="post" action="{{ route('admin.finance.obligations.adjust', $o->id) }}" data-confirm="Record this adjustment? It cannot be edited afterwards.">
                    @csrf <x-idem />
                    <x-select name="kind" label="Type" required :prefix="'o'.$o->id.'-'" :options="['waiver' => 'Waiver (reduce)', 'correction' => 'Correction (+ or −)', 'increase' => 'Increase', 'cancellation' => 'Cancel remaining balance']" />
                    <x-field name="amount" label="Amount (₦)" :prefix="'o'.$o->id.'-'" hint="Not needed for cancellation. Use a minus sign to reduce with a correction." />
                    <x-field name="effective_on" label="Effective date" type="date" required :prefix="'o'.$o->id.'-'" :value="now()->toDateString()" />
                    <x-field name="reason" label="Reason" required :prefix="'o'.$o->id.'-'" />
                    <button class="btn small" type="submit">Record adjustment</button>
                </form>
            </details></td>
            @endif
        </tr>
    @endforeach</tbody>
</table></div>
@endif

<h2>Payments</h2>
@if ($payments->isEmpty())
    <p class="muted">No payments recorded.</p>
@else
<div class="table-wrap"><table>
    <thead><tr><th scope="col">Reference</th><th scope="col">Received</th><th scope="col">Method</th><th scope="col">Status</th><th scope="col" class="num">Amount</th><th scope="col" class="num">Allocated</th></tr></thead>
    <tbody>@foreach ($payments as $p)
        <tr><td><a href="{{ route('admin.finance.payments.show', $p->id) }}">{{ $p->reference }}</a></td><td>{{ \Carbon\Carbon::parse($p->received_on)->format('j M Y') }}</td><td>{{ \App\Support\Format::label($p->method) }}</td><td><x-status :value="$p->status" /></td><td class="num"><x-money :kobo="$p->amount_kobo" /></td><td class="num"><x-money :kobo="$p->allocated" /></td></tr>
    @endforeach</tbody>
</table></div>
@endif

<h2>Statement</h2>
@if ($u->hasPermission('finance.reports.export'))
    <p class="no-print"><a class="btn secondary small" href="{{ route('admin.finance.members.statement.csv', [$member, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Download statement CSV</a></p>
@endif
@include('partials.statement', ['statement' => $statement, 'from' => $from, 'to' => $to, 'action' => route('admin.finance.members.show', $member)])
@endsection
