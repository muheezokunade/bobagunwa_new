@extends('layouts.app', ['area' => 'admin', 'title' => 'Import existing members'])
@section('content')
<x-page-head title="Import existing members" subtitle="Add verified existing members from an approved list. Every file is previewed before anything is saved." />
<div class="grid-2">
    <section class="panel">
        <h2>How to prepare the file</h2>
        <ol>
            <li><a href="{{ route('admin.imports.members.template') }}">Download the CSV template</a> and fill in one row per member.</li>
            <li>The only required column is <strong>full_name</strong>.</li>
            <li>Optional columns: email, phone, date_of_birth, membership_number, joined_on, ijebu_town, city, occupation, address.</li>
            <li>Write dates as YYYY-MM-DD (for example 1981-03-24) or DD/MM/YYYY (24/03/1981).</li>
            <li>Leave membership_number empty to issue the next number automatically. Numbers already in use are refused.</li>
            <li>The file may contain at most {{ number_format((int) config('boba.uploads.import_max_rows')) }} rows.</li>
        </ol>
        <p class="small muted">Imported rows never create login accounts. Issue activation links later, member by member, after verifying identity. Every row must be valid before the import can be completed; nothing is saved from a file with errors or duplicates.</p>
    </section>
    <form class="panel" method="post" action="{{ route('admin.imports.members.preview') }}" enctype="multipart/form-data">
        @csrf
        <h2>Upload a file for preview</h2>
        <x-field name="file" label="CSV file" type="file" required accept=".csv,text/csv" hint="Saved as CSV (UTF-8). Maximum {{ number_format((int) config('boba.uploads.import_max_kb')) }} KB." />
        <button class="btn" type="submit">Preview import</button>
        <p class="small muted">The preview checks each row against the register. No records are changed until you confirm.</p>
    </form>
</div>
<h2>Previous imports</h2>
@if ($batches->isEmpty())
    <x-empty title="No imports yet"><p>Uploaded files and their outcome will appear here.</p></x-empty>
@else
<div class="table-wrap" tabindex="0" role="region" aria-label="Table (scrolls sideways on small screens)"><table>
    <thead><tr><th scope="col">Reference</th><th scope="col">File</th><th scope="col">Status</th><th scope="col" class="num">Rows</th><th scope="col" class="num">Valid</th><th scope="col" class="num">Problems</th><th scope="col">Uploaded</th><th scope="col">Completed</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>@foreach ($batches as $batch)
        <tr>
            <td>{{ $batch->reference }}</td>
            <td>{{ $batch->original_filename }}</td>
            <td><x-status :value="$batch->status" /></td>
            <td class="num">{{ $batch->row_count }}</td>
            <td class="num">{{ $batch->valid_count }}</td>
            <td class="num">{{ $batch->invalid_count + $batch->duplicate_count }}</td>
            <td>{{ \App\Support\Format::dateTime($batch->created_at) }}<br><span class="muted small">{{ $batch->creator?->name }}</span></td>
            <td>@if ($batch->committed_at){{ \App\Support\Format::dateTime($batch->committed_at) }}<br><span class="muted small">{{ $batch->committer?->name }}</span>@else — @endif</td>
            <td><a class="text-link" href="{{ route('admin.imports.members.show', $batch) }}">Open <span class="sr-only">import {{ $batch->reference }}</span></a></td>
        </tr>
    @endforeach</tbody>
</table></div>
{{ $batches->links() }}
@endif
@endsection
