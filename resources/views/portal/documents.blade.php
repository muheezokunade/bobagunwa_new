@extends('layouts.app', ['area' => 'portal', 'title' => 'Documents'])
@section('content')
<x-page-head title="Documents" subtitle="Association documents shared with members." />
@if ($documents->isEmpty())
    <x-empty title="No documents yet"><p>Documents shared by the secretariat will appear here.</p></x-empty>
@else
<div class="table-wrap" tabindex="0" role="region" aria-label="Table (scrolls sideways on small screens)"><table>
    <thead><tr><th scope="col">Document</th><th scope="col">Added</th><th scope="col">Download</th></tr></thead>
    <tbody>@foreach ($documents as $document)
        <tr><td>{{ $document->title }}<br><span class="muted small">{{ $document->description }}</span></td><td>{{ \App\Support\Format::date($document->created_at) }}</td><td><a href="{{ route('portal.documents.download', $document) }}">Download <span class="sr-only">{{ $document->title }}</span> ({{ strtoupper(pathinfo($document->file->path, PATHINFO_EXTENSION)) }}, {{ max(1, round($document->file->size_bytes / 1024)) }} KB)</a></td></tr>
    @endforeach</tbody>
</table></div>
@endif
@endsection
