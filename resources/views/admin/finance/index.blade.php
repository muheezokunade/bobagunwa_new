@extends('layouts.app', ['area' => 'admin', 'title' => 'Finance overview'])
@section('content')
<x-page-head title="Finance overview" subtitle="All figures are calculated from posted and verified records.">
    @if (auth()->user()->hasPermission('finance.payments.record'))<a class="btn" href="{{ route('admin.finance.payments.create') }}">Record a payment</a>@endif
    @if (auth()->user()->hasPermission('finance.assessments.manage'))<a class="btn secondary" href="{{ route('admin.finance.assessments.create') }}">New assessment</a>@endif
</x-page-head>
@if ($exceptions)
    <div class="alert error" role="alert"><strong>Reconciliation exceptions found.</strong> {{ implode('; ', array_keys($exceptions)) }}. <a href="{{ route('admin.finance.reports.index') }}#exceptions">Review them</a> and escalate to the treasurer and auditors.</div>
@endif
<dl class="stats">
    <div><dt>Effective obligations</dt><dd><x-money :kobo="$totals['effective_obligations']" /></dd></div>
    <div><dt>Allocated to obligations</dt><dd><x-money :kobo="$totals['allocated']" /></dd></div>
    <div><dt>Outstanding</dt><dd class="value wine"><x-money :kobo="$totals['outstanding']" /></dd></div>
    <div><dt>Verified payments</dt><dd><x-money :kobo="$totals['verified_payments']" /></dd></div>
    <div><dt>Unallocated credit</dt><dd><x-money :kobo="$totals['unallocated_credit']" /></dd></div>
    <div><dt>Awaiting verification ({{ $totals['pending_count'] }})</dt><dd><x-money :kobo="$totals['pending']" /></dd></div>
</dl>
<h2>Payments awaiting verification</h2>
@if ($pending->isEmpty())
    <x-empty title="Nothing waiting"><p>All recorded payments have been verified or rejected.</p></x-empty>
@else
<div class="table-wrap"><table>
    <thead><tr><th scope="col">Payment</th><th scope="col">Member</th><th scope="col">Received</th><th scope="col" class="num">Amount</th></tr></thead>
    <tbody>@foreach ($pending as $p)
        <tr><td><a href="{{ route('admin.finance.payments.show', $p) }}">{{ $p->reference }}</a></td><td>{{ $p->member->membership_number }} · {{ $p->member->full_name }}</td><td>{{ \App\Support\Format::date($p->received_on) }}</td><td class="num"><x-money :kobo="$p->amount_kobo" /></td></tr>
    @endforeach</tbody>
</table></div>
@endif
<p class="small muted">Definitions: outstanding = posted obligations ± adjustments − active allocations. Unallocated credit = verified payments − active allocations. Pending payments are excluded from every balance until verified. See <a href="{{ route('admin.finance.reports.index') }}">Reports</a> for period figures.</p>
@endsection
