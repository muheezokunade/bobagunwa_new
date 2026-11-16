@extends('layouts.app', ['area' => 'admin', 'title' => 'Historical financial records'])
@section('content')
<x-page-head title="Historical financial records" subtitle="Import only records approved by the association, with a documented source." />
<div class="alert warning">Never invent opening balances, past payments, dates or references. Take a backup first. A preview with any invalid row cannot be imported, and when the two-officer rule is on a different officer must confirm the import.</div>
<div class="grid-2">
    <section class="panel">
        <h2>Historical transactions</h2>
        <p>Individual obligations and payments, each traceable to a source document. Payments need the bank or receipt reference. Imported payments are recorded as verified and left unallocated for an officer to allocate.</p>
        <p><a class="text-link" href="{{ route('admin.finance.imports.template', 'finance_transactions') }}">Download template</a></p>
        <form method="post" action="{{ route('admin.finance.imports.preview') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="kind" value="finance_transactions">
            <x-field name="file" label="CSV file" type="file" accept=".csv,text/csv" required prefix="t-" />
            <x-field name="source_approval_reference" label="Approved source reference" required prefix="t-" hint="For example the minute, signed schedule or audit reference that approved these records." />
            <button class="btn" type="submit">Preview</button>
        </form>
    </section>
    <section class="panel">
        <h2>Opening balances</h2>
        <p>Amounts owed at an approved cut-off date, when detailed history is unavailable. They are labelled as opening balances and never shown as reconstructed history. Credit balances must be supported by real payment records instead.</p>
        <p><a class="text-link" href="{{ route('admin.finance.imports.template', 'opening_balances') }}">Download template</a></p>
        <form method="post" action="{{ route('admin.finance.imports.preview') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="kind" value="opening_balances">
            <x-field name="file" label="CSV file" type="file" accept=".csv,text/csv" required prefix="o-" />
            <x-field name="cutoff_date" label="Approved cut-off date" type="date" required prefix="o-" />
            <x-select name="fee_category_id" label="Category label" required prefix="o-" placeholder="Choose" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->name])->all()" />
            <x-field name="source_approval_reference" label="Approved source reference" required prefix="o-" />
            <button class="btn" type="submit">Preview</button>
        </form>
    </section>
</div>
<h2>Import history</h2>
@if ($batches->isEmpty())
    <p class="muted">No financial imports yet.</p>
@else
<div class="table-wrap"><table>
    <thead><tr><th scope="col">Reference</th><th scope="col">Type</th><th scope="col">File</th><th scope="col">Rows</th><th scope="col">Status</th><th scope="col">Uploaded by</th><th scope="col">Confirmed by</th></tr></thead>
    <tbody>@foreach ($batches as $b)
        <tr><td><a href="{{ route('admin.finance.imports.show', $b) }}">{{ $b->reference }}</a></td><td>{{ \App\Support\Format::label($b->kind) }}</td><td>{{ $b->original_filename }}</td><td>{{ $b->valid_count }}/{{ $b->row_count }} valid</td><td><x-status :value="$b->status" /></td><td>{{ $b->creator->name }}</td><td>{{ $b->committer?->name ?? '—' }}</td></tr>
    @endforeach</tbody>
</table></div>
{{ $batches->links() }}
@endif
@endsection
