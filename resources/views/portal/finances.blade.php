@extends('layouts.app', ['area' => 'portal', 'title' => 'Dues and payments'])
@section('content')
<x-page-head title="Dues and payments" subtitle="Balances are calculated from your verified payments and posted assessments.">
    <a class="btn secondary" href="{{ route('portal.statement') }}">View statement</a>
</x-page-head>
<dl class="stats">
    <div><dt>Total assessed</dt><dd><x-money :kobo="$summary['effective_obligations']" /></dd></div>
    <div><dt>Paid towards dues</dt><dd><x-money :kobo="$summary['allocated']" /></dd></div>
    <div><dt>Outstanding</dt><dd class="value wine"><x-money :kobo="$summary['outstanding']" /></dd></div>
    <div><dt>Unallocated credit</dt><dd><x-money :kobo="$summary['unallocated_credit']" /></dd></div>
</dl>
<h2>Dues and levies</h2>
@if ($obligations->isEmpty())
    <x-empty title="Nothing has been assessed to you yet"><p>Dues and levies appear here once the treasurer posts them.</p></x-empty>
@else
<div class="table-wrap"><table>
    <caption class="sr-only">Your dues and levies</caption>
    <thead><tr><th scope="col">Item</th><th scope="col">Reference</th><th scope="col">Date</th><th scope="col">Due</th><th scope="col" class="num">Amount</th><th scope="col" class="num">Paid</th><th scope="col" class="num">Outstanding</th></tr></thead>
    <tbody>
    @foreach ($obligations as $o)
        <tr><td>{{ $o->title }}<br><span class="muted small">{{ $o->category }}</span></td><td>{{ $o->reference }}</td><td>{{ \Carbon\Carbon::parse($o->effective_on)->format('j M Y') }}</td><td>{{ $o->due_on ? \Carbon\Carbon::parse($o->due_on)->format('j M Y') : '—' }}</td>
            <td class="num"><x-money :kobo="$o->effective" /></td><td class="num"><x-money :kobo="$o->allocated" /></td><td class="num"><x-money :kobo="$o->outstanding" /></td></tr>
    @endforeach
    </tbody>
</table></div>
@endif
<h2>Payments</h2>
@if ($payments->isEmpty())
    <x-empty title="No payments recorded"><p>When you pay, the treasurer records and verifies the payment. It then appears here.</p></x-empty>
@else
<div class="table-wrap"><table>
    <caption class="sr-only">Your payments</caption>
    <thead><tr><th scope="col">Reference</th><th scope="col">Received</th><th scope="col">Method</th><th scope="col">Status</th><th scope="col" class="num">Amount</th><th scope="col" class="num">Applied to dues</th></tr></thead>
    <tbody>
    @foreach ($payments as $p)
        <tr><td>{{ $p->reference }}</td><td>{{ \Carbon\Carbon::parse($p->received_on)->format('j M Y') }}</td><td>{{ \App\Support\Format::label($p->method) }}</td><td><x-status :value="$p->status" /></td><td class="num"><x-money :kobo="$p->amount_kobo" /></td><td class="num">{{ $p->status === 'verified' ? \App\Support\Money::format((int) $p->allocated) : '—' }}</td></tr>
    @endforeach
    </tbody>
</table></div>
<p class="small muted">Pending payments are being checked by the treasury and are not yet included in your balance. Rejected and reversed payments are not counted.</p>
@endif
@endsection
