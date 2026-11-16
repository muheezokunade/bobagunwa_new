<form method="get" action="{{ $action }}" class="filters no-print">
    <x-field name="from" label="From" type="date" :value="$from->toDateString()" />
    <x-field name="to" label="To" type="date" :value="$to->toDateString()" />
    <button class="btn secondary" type="submit">Show period</button>
</form>
<p class="print-only">Statement period {{ $from->format('j M Y') }} to {{ $to->format('j M Y') }} · produced {{ \App\Support\Format::dateTime(now()) }}</p>
<dl class="stats">
    <div><dt>Opening position ({{ $from->format('j M Y') }})</dt><dd><x-money :kobo="$statement['opening']" /></dd></div>
    <div><dt>Charges in period</dt><dd><x-money :kobo="$statement['totals']['debits']" /></dd></div>
    <div><dt>Payments in period</dt><dd><x-money :kobo="$statement['totals']['credits']" /></dd></div>
    <div><dt>Closing position ({{ $to->format('j M Y') }})</dt><dd class="value wine"><x-money :kobo="$statement['closing']" /></dd></div>
</dl>
<p class="small muted">A positive position is an amount owed to the association; a negative position is a credit in your favour.</p>
<div class="table-wrap"><table>
    <caption>Transactions from {{ $from->format('j M Y') }} to {{ $to->format('j M Y') }}</caption>
    <thead><tr><th scope="col">Date</th><th scope="col">Transaction</th><th scope="col">Reference</th><th scope="col" class="num">Charges</th><th scope="col" class="num">Payments</th><th scope="col" class="num">Position</th></tr></thead>
    <tbody>
        <tr><td>{{ $from->format('j M Y') }}</td><td colspan="4">Opening position</td><td class="num"><x-money :kobo="$statement['opening']" /></td></tr>
        @forelse ($statement['lines'] as $line)
            <tr><td>{{ \Carbon\Carbon::parse($line['date'])->format('j M Y') }}</td><td>{{ $line['type'] }}<br><span class="muted small">{{ $line['description'] }}</span></td><td>{{ $line['reference'] }}</td>
                <td class="num">{{ $line['debit'] ? \App\Support\Money::format($line['debit']) : '' }}</td><td class="num">{{ $line['credit'] ? \App\Support\Money::format($line['credit']) : '' }}</td><td class="num"><x-money :kobo="$line['balance']" /></td></tr>
        @empty
            <tr><td colspan="6" class="muted">No transactions in this period.</td></tr>
        @endforelse
    </tbody>
    <tfoot><tr><td colspan="5">Closing position</td><td class="num"><x-money :kobo="$statement['closing']" /></td></tr></tfoot>
</table></div>
<h2>Position at {{ $to->format('j M Y') }}</h2>
<dl class="details">
    <dt>Outstanding dues and levies</dt><dd><x-money :kobo="$statement['as_at']['outstanding']" /></dd>
    <dt>Unallocated credit</dt><dd><x-money :kobo="$statement['as_at']['unallocated_credit']" /></dd>
</dl>
@if ($statement['pending']->isNotEmpty())
    <p class="small muted">Not yet included: {{ $statement['pending']->count() }} payment(s) totalling {{ \App\Support\Money::format((int) $statement['pending']->sum('amount_kobo')) }} awaiting verification.</p>
@endif
