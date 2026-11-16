@extends('layouts.app', ['area' => 'admin', 'title' => 'Reports and reconciliation'])
@section('content')
@php $canExport = auth()->user()->hasPermission('finance.reports.export'); $q = ['from' => $from->toDateString(), 'to' => $to->toDateString()]; @endphp
<x-page-head title="Reports and reconciliation" :subtitle="'Period '.$from->format('j M Y').' to '.$to->format('j M Y')" />
<form method="get" class="filters no-print">
    <x-field name="from" label="From" type="date" :value="$from->toDateString()" />
    <x-field name="to" label="To" type="date" :value="$to->toDateString()" />
    <button class="btn secondary" type="submit">Show period</button>
</form>

<h2>Position today (all time)</h2>
<dl class="stats">
    <div><dt>Assessed</dt><dd><x-money :kobo="$totals['assessed']" /></dd></div>
    <div><dt>Adjustments</dt><dd><x-money :kobo="$totals['adjustments']" /></dd></div>
    <div><dt>Allocated</dt><dd><x-money :kobo="$totals['allocated']" /></dd></div>
    <div><dt>Outstanding</dt><dd class="value wine"><x-money :kobo="$totals['outstanding']" /></dd></div>
    <div><dt>Verified payments (net of reversals)</dt><dd><x-money :kobo="$totals['verified_payments']" /></dd></div>
    <div><dt>Unallocated credit</dt><dd><x-money :kobo="$totals['unallocated_credit']" /></dd></div>
    <div><dt>Awaiting verification</dt><dd><x-money :kobo="$totals['pending']" /></dd></div>
    <div><dt>Payments reversed (all time)</dt><dd><x-money :kobo="$totals['reversed_total']" /></dd></div>
</dl>

<h2>Dues and levy performance</h2>
<p class="small muted">Obligations with an effective date in the period. “Allocated” is the part of those obligations already settled by verified payments.</p>
@if ($canExport)<p class="no-print"><a class="text-link" href="{{ route('admin.finance.reports.export', ['report' => 'categories'] + $q) }}">Export CSV</a></p>@endif
<div class="table-wrap"><table>
    <thead><tr><th scope="col">Category</th><th scope="col" class="num">Obligations</th><th scope="col" class="num">Assessed</th><th scope="col" class="num">Adjustments</th><th scope="col" class="num">Allocated</th><th scope="col" class="num">Outstanding</th><th scope="col" class="num">Collected</th></tr></thead>
    <tbody>@forelse ($period['by_category'] as $r)
        <tr><td>{{ $r->name }} <span class="small muted">{{ $r->code }}</span></td><td class="num">{{ $r->obligations }}</td><td class="num"><x-money :kobo="(int) $r->assessed" /></td><td class="num"><x-money :kobo="(int) $r->adjustments" /></td><td class="num"><x-money :kobo="(int) $r->allocated" /></td><td class="num"><x-money :kobo="$r->outstanding" /></td><td class="num">{{ $r->collection_rate !== null ? $r->collection_rate.'%' : '—' }}</td></tr>
    @empty
        <tr><td colspan="7" class="muted">No obligations in this period.</td></tr>
    @endforelse</tbody>
</table></div>

<div class="grid-2">
    <section class="panel">
        <h2>Collections in period</h2>
        <p>{{ $period['collections']['count'] }} verified payment(s) received, totalling <strong><x-money :kobo="$period['collections']['total']" /></strong> (by date received, including payments later reversed).</p>
        <p>{{ $period['reversals']['count'] }} payment reversal(s) recorded in the period, totalling <x-money :kobo="$period['reversals']['total']" />.</p>
        <ul>@foreach ($period['by_method'] as $m)<li>{{ \App\Support\Format::label($m->method) }}: {{ $m->n }} · <x-money :kobo="(int) $m->total" /></li>@endforeach</ul>
        @if ($canExport)<a class="text-link no-print" href="{{ route('admin.finance.reports.export', ['report' => 'collections'] + $q) }}">Export collections CSV</a>@endif
    </section>
    <section class="panel">
        <h2>Payments awaiting verification</h2>
        @forelse ($pending as $p)
            <p><a href="{{ route('admin.finance.payments.show', $p) }}">{{ $p->reference }}</a> · {{ $p->member->membership_number }} · <x-money :kobo="$p->amount_kobo" /> · received {{ \App\Support\Format::date($p->received_on) }}</p>
        @empty
            <p class="muted">None.</p>
        @endforelse
    </section>
</div>

<h2 id="exceptions">Reconciliation checks</h2>
<div class="table-wrap"><table>
    <thead><tr><th scope="col">Check</th><th scope="col">Result</th></tr></thead>
    <tbody>@foreach ($exceptions as $label => $rows)
        <tr><td>{{ $label }}</td><td>@if (count($rows))<span class="badge danger">{{ count($rows) }} exception(s)</span>@else<span class="badge ok">No exceptions</span>@endif</td></tr>
    @endforeach</tbody>
</table></div>

<h2>Member balances</h2>
<form method="get" class="filters no-print">
    <input type="hidden" name="from" value="{{ $from->toDateString() }}"><input type="hidden" name="to" value="{{ $to->toDateString() }}">
    <x-field name="q" label="Member" type="search" :value="request('q')" />
    <x-select name="filter" label="Show" :value="$filter" :options="['all' => 'All members', 'outstanding' => 'With outstanding balances', 'credit' => 'With unallocated credit']" />
    <button class="btn secondary" type="submit">Apply</button>
</form>
@if ($canExport)<p class="no-print"><a class="text-link" href="{{ route('admin.finance.reports.export', ['report' => 'balances']) }}">Export all balances CSV</a></p>@endif
<div class="table-wrap"><table>
    <thead><tr><th scope="col">Member</th><th scope="col" class="num">Effective obligations</th><th scope="col" class="num">Allocated</th><th scope="col" class="num">Outstanding</th><th scope="col" class="num">Unallocated credit</th><th scope="col" class="num">Pending</th></tr></thead>
    <tbody>@forelse ($balances as $b)
        <tr><td><a href="{{ route('admin.finance.members.show', $b['member_id']) }}">{{ $b['membership_number'] }}</a> · {{ $b['full_name'] }}</td><td class="num"><x-money :kobo="$b['effective_obligations']" /></td><td class="num"><x-money :kobo="$b['allocated']" /></td><td class="num"><x-money :kobo="$b['outstanding']" /></td><td class="num"><x-money :kobo="$b['unallocated_credit']" /></td><td class="num"><x-money :kobo="$b['pending']" /></td></tr>
    @empty
        <tr><td colspan="6" class="muted">No members match.</td></tr>
    @endforelse</tbody>
    @if ($balances->isNotEmpty())
    <tfoot><tr><td>Total ({{ $balances->count() }} members)</td><td class="num"><x-money :kobo="$balances->sum('effective_obligations')" /></td><td class="num"><x-money :kobo="$balances->sum('allocated')" /></td><td class="num"><x-money :kobo="$balances->sum('outstanding')" /></td><td class="num"><x-money :kobo="$balances->sum('unallocated_credit')" /></td><td class="num"><x-money :kobo="$balances->sum('pending')" /></td></tr></tfoot>
    @endif
</table></div>

<div class="grid-2">
    <section class="panel">
        <h2>Adjustments in period</h2>
        @forelse ($corrections['adjustments'] as $a)
            <p>{{ $a->reference }} · {{ $a->obligation->member->membership_number }} · {{ ucfirst($a->kind) }} <x-money :kobo="$a->amount_kobo" /><br><span class="small muted">{{ $a->reason }} — {{ $a->creator->name }}</span></p>
        @empty <p class="muted">None.</p> @endforelse
    </section>
    <section class="panel">
        <h2>Reversals in period</h2>
        @forelse ($corrections['reversals'] as $r)
            <p>{{ $r->reference }} · {{ $r->payment->member->membership_number }} · {{ ucfirst($r->kind) }} <x-money :kobo="$r->amount_kobo" /><br><span class="small muted">{{ $r->reason }} — {{ $r->creator->name }}</span></p>
        @empty <p class="muted">None.</p> @endforelse
    </section>
</div>
@endsection
