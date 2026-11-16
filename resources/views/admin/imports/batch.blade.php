@extends('layouts.app', ['area' => 'admin', 'title' => 'Import '.$batch->reference])
@section('content')
@php
    $F = \App\Support\Format::class;
    $isMembers = $batch->kind === 'members';
    $summary = is_array($batch->summary) ? $batch->summary : [];
    $clean = ($batch->invalid_count + $batch->duplicate_count) === 0;
    $kindLabels = ['members' => 'Existing members', 'finance_transactions' => 'Historical financial records', 'opening_balances' => 'Opening balances'];
@endphp
<x-page-head :title="'Import '.$batch->reference" :subtitle="($kindLabels[$batch->kind] ?? $F::label($batch->kind)).' · '.$batch->original_filename">
    <a class="btn secondary" href="{{ route($backRoute) }}">All imports</a>
</x-page-head>

<dl class="stats">
    <div><dt>Rows</dt><dd>{{ $batch->row_count }}</dd></div>
    <div><dt>Valid</dt><dd>{{ $batch->valid_count }}</dd></div>
    <div><dt>Invalid</dt><dd>{{ $batch->invalid_count }}</dd></div>
    <div><dt>Duplicates</dt><dd>{{ $batch->duplicate_count }}</dd></div>
</dl>

<div class="grid-2">
    <section class="panel">
        <h2>Batch details</h2>
        <dl class="details">
            <dt>Reference</dt><dd>{{ $batch->reference }}</dd>
            <dt>Type</dt><dd>{{ $kindLabels[$batch->kind] ?? $F::label($batch->kind) }}</dd>
            <dt>Status</dt><dd><x-status :value="$batch->status" /></dd>
            <dt>File</dt><dd>{{ $batch->original_filename }}</dd>
            <dt>File fingerprint (SHA-256)</dt><dd><code class="small">{{ $batch->file_sha256 }}</code></dd>
            <dt>Uploaded</dt><dd>{{ $F::dateTime($batch->created_at) }}@if ($batch->creator) by {{ $batch->creator->name }}@endif</dd>
            @if ($batch->committed_at)<dt>Completed</dt><dd>{{ $F::dateTime($batch->committed_at) }}@if ($batch->committer) by {{ $batch->committer->name }}@endif</dd>@endif
            @if ($batch->cancelled_at)<dt>Cancelled</dt><dd>{{ $F::dateTime($batch->cancelled_at) }}</dd>@endif
            @if ($batch->source_approval_reference)<dt>Source approval reference</dt><dd>{{ $batch->source_approval_reference }}</dd>@endif
            @if ($batch->cutoff_date)<dt>Cut-off date</dt><dd>{{ $F::date($batch->cutoff_date) }}</dd>@endif
        </dl>
    </section>
    <section class="panel">
        <h2>Totals of valid rows</h2>
        @if (isset($summary['obligations_kobo']) || isset($summary['payments_kobo']) || isset($summary['members']))
            <dl class="details">
                @isset($summary['obligations_kobo'])<dt>Amounts owed</dt><dd><x-money :kobo="$summary['obligations_kobo']" /></dd>@endisset
                @isset($summary['payments_kobo'])<dt>Payments</dt><dd><x-money :kobo="$summary['payments_kobo']" /></dd>@endisset
                @isset($summary['members'])<dt>Members affected</dt><dd>{{ $summary['members'] }}</dd>@endisset
            </dl>
            <p class="small muted">Reconcile these totals with the approved source records before completing the import.</p>
        @else
            <p>{{ $batch->valid_count }} of {{ $batch->row_count }} rows are ready to import.</p>
        @endif
        @if (! empty($summary['unknown_columns']) && is_array($summary['unknown_columns']))
            <p class="small">Columns that were not recognised and will be ignored: {{ implode(', ', $summary['unknown_columns']) }}</p>
        @endif
    </section>
</div>

@if ($batch->status === 'previewed')
    @if ($clean)
        <div class="grid-2">
            <form class="panel" method="post" action="{{ route($commitRoute, $batch) }}" data-confirm="Complete import {{ $batch->reference }}? {{ $batch->valid_count }} rows will be saved permanently.">
                @csrf
                <h2>Complete the import</h2>
                <p>All {{ $batch->row_count }} rows passed the checks.@if ($isMembers) No login accounts will be created.@endif</p>
                <x-check name="confirm" :label="$isMembers ? 'I confirm this list is the association’s approved source and has been checked.' : 'I confirm the source records are approved and the totals above have been reconciled.'" />
                <button class="btn" type="submit">Complete import</button>
            </form>
            <form class="panel" method="post" action="{{ route($cancelRoute, $batch) }}" data-confirm="Cancel this preview? No records will be changed.">
                @csrf
                <h2>Cancel the preview</h2>
                <p>Cancelling discards this preview. No records are changed.</p>
                <button class="btn secondary" type="submit">Cancel preview</button>
            </form>
        </div>
    @else
        <div class="alert error" role="alert">
            {{ $batch->invalid_count + $batch->duplicate_count }} row(s) have problems, so this file cannot be imported. Correct the rows listed below in the original file and upload it again.
        </div>
        <form method="post" action="{{ route($cancelRoute, $batch) }}" data-confirm="Cancel this preview? No records will be changed.">
            @csrf
            <button class="btn secondary" type="submit">Cancel preview</button>
        </form>
    @endif
@endif

<h2>Rows</h2>
<div class="table-wrap" tabindex="0" role="region" aria-label="Table (scrolls sideways on small screens)"><table>
    <thead><tr>
        <th scope="col" class="num">Row</th><th scope="col">Status</th>
        @if ($isMembers)
            <th scope="col">Full name</th><th scope="col">Email</th><th scope="col">Phone</th><th scope="col">Membership number</th>
        @else
            <th scope="col">Record type</th><th scope="col">Membership number</th><th scope="col">Date</th><th scope="col" class="num">Amount</th><th scope="col">Reference</th>
        @endif
        <th scope="col">Messages</th>
    </tr></thead>
    <tbody>@forelse ($rows as $row)
        @php $n = is_array($row->normalized) ? $row->normalized : []; $msgs = is_array($row->messages) ? $row->messages : []; @endphp
        <tr>
            <td class="num">{{ $row->row_number }}</td>
            <td><x-status :value="$row->status" /></td>
            @if ($isMembers)
                <td>{{ $n['full_name'] ?? '' }}</td>
                <td>{{ $n['email'] ?? '' }}</td>
                <td>{{ $n['phone'] ?? '' }}</td>
                <td>{{ $n['membership_number'] ?? 'Next available' }}</td>
            @else
                <td>{{ $F::label($n['record_type'] ?? null) }}</td>
                <td>{{ $n['membership_number'] ?? '' }}</td>
                <td>{{ $n['date'] ?? ($batch->cutoff_date ? $F::date($batch->cutoff_date) : '') }}</td>
                <td class="num">@if (isset($n['amount_kobo']))<x-money :kobo="$n['amount_kobo']" />@endif</td>
                <td>{{ $n['external_reference'] ?? '' }}</td>
            @endif
            <td>@if ($msgs)<ul class="small">@foreach ($msgs as $msg)<li>{{ is_string($msg) ? $msg : json_encode($msg) }}</li>@endforeach</ul>@else<span class="muted">—</span>@endif</td>
        </tr>
    @empty
        <tr><td colspan="{{ $isMembers ? 7 : 8 }}" class="muted">No rows were found in this file.</td></tr>
    @endforelse</tbody>
</table></div>
{{ $rows->links() }}
<p><a class="text-link" href="{{ route($backRoute) }}">Back to imports</a></p>
@endsection
