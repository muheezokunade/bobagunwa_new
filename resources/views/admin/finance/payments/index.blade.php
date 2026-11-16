@extends('layouts.app', ['area' => 'admin', 'title' => 'Payments'])
@section('content')
<x-page-head title="Payments" subtitle="Recorded payments count only after independent verification.">
    @if (auth()->user()->hasPermission('finance.payments.record'))<a class="btn" href="{{ route('admin.finance.payments.create') }}">Record a payment</a>@endif
</x-page-head>
<form method="get" class="filters">
    <x-select name="status" label="Status" :value="$filters['status'] ?? ''" placeholder="All" :options="['pending' => 'Awaiting verification', 'verified' => 'Verified', 'rejected' => 'Rejected', 'reversed' => 'Reversed']" />
    <x-field name="q" label="Search" type="search" :value="$filters['q'] ?? ''" hint="Reference, bank reference, member number or name" />
    <x-field name="from" label="Received from" type="date" :value="$filters['from'] ?? ''" />
    <x-field name="to" label="Received to" type="date" :value="$filters['to'] ?? ''" />
    <button class="btn secondary" type="submit">Apply filters</button>
</form>
@if ($payments->isEmpty())
    <x-empty title="No payments match"><p>Adjust the filters or record a payment.</p></x-empty>
@else
<div class="table-wrap" tabindex="0" role="region" aria-label="Table (scrolls sideways on small screens)"><table>
    <thead><tr><th scope="col">Reference</th><th scope="col">Member</th><th scope="col">Received</th><th scope="col">Method</th><th scope="col">Bank / receipt ref.</th><th scope="col">Status</th><th scope="col" class="num">Amount</th><th scope="col">Recorded by</th></tr></thead>
    <tbody>@foreach ($payments as $p)
        <tr><td><a href="{{ route('admin.finance.payments.show', $p) }}">{{ $p->reference }}</a></td><td>{{ $p->member->membership_number }}<br><span class="small muted">{{ $p->member->full_name }}</span></td><td>{{ \App\Support\Format::date($p->received_on) }}</td><td>{{ \App\Support\Format::label($p->method) }}</td><td>{{ $p->external_reference ?: '—' }}</td><td><x-status :value="$p->status" /></td><td class="num"><x-money :kobo="$p->amount_kobo" /></td><td>{{ $p->recorder->name }}</td></tr>
    @endforeach</tbody>
</table></div>
{{ $payments->links() }}
@endif
@endsection
