@extends('layouts.app', ['area' => 'admin', 'title' => 'Website content'])
@section('content')
<x-page-head title="Website content" subtitle="Pages are versioned: edits are saved as drafts and become public only when a publisher publishes them.">
    <a class="btn secondary" href="{{ route('admin.content.media') }}">Media library</a>
</x-page-head>
<h2>Managed pages</h2>
<div class="table-wrap"><table>
    <thead><tr><th scope="col">Page</th><th scope="col">State</th><th scope="col">Latest draft</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>@foreach ($pages as $page)
        <tr>
            <td>{{ $page->label }}<br><span class="muted small">{{ $page->key }}</span></td>
            <td>
                @if ($page->publishedRevision)
                    <x-status value="published" />
                    @if ($page->latestRevision && $page->latestRevision->id !== $page->publishedRevision->id)<br><span class="muted small">Newer draft not yet published</span>@endif
                @elseif ($page->latestRevision)
                    <x-status value="draft" /> <span class="muted small">Draft only</span>
                @else
                    <span class="muted">Not started</span>
                @endif
            </td>
            <td>{{ $page->latestRevision ? \App\Support\Format::dateTime($page->latestRevision->created_at) : '—' }}</td>
            <td><a class="text-link" href="{{ route('admin.content.pages.edit', $page) }}">{{ auth()->user()->hasPermission('content.manage') ? 'Edit' : 'Review' }} <span class="sr-only">{{ $page->label }}</span></a></td>
        </tr>
    @endforeach</tbody>
</table></div>
<h2>Collections</h2>
<ul class="listing">
    @foreach ($types as $type => $spec)
        <li><a class="text-link" href="{{ route('admin.content.list', $type) }}">{{ $spec['label'] }}</a></li>
    @endforeach
</ul>
@endsection
