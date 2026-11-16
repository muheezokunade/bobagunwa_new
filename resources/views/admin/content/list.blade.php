@extends('layouts.app', ['area' => 'admin', 'title' => $spec['label']])
@section('content')
<x-page-head :title="$spec['label']" subtitle="New entries are saved as drafts and appear on the website only once published.">
    @if (auth()->user()->hasPermission('content.manage'))
        <a class="btn" href="{{ route('admin.content.create', $type) }}">New {{ $spec['singular'] }}</a>
    @endif
    <a class="btn secondary" href="{{ route('admin.content.index') }}">All content</a>
</x-page-head>
@if ($records->isEmpty())
    <x-empty title="Nothing here yet"><p>No {{ $spec['singular'] }} entries have been created.</p></x-empty>
@else
<div class="table-wrap"><table>
    <thead><tr><th scope="col">Title</th><th scope="col">Status</th><th scope="col">Last updated</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>@foreach ($records as $record)
        <tr>
            <td>{{ $record->{$spec['title']} }}</td>
            <td><x-status :value="$record->status" /></td>
            <td>{{ \App\Support\Format::dateTime($record->updated_at) }}</td>
            <td><a class="text-link" href="{{ route('admin.content.edit', [$type, $record->id]) }}">{{ auth()->user()->hasPermission('content.manage') ? 'Edit' : 'Review' }} <span class="sr-only">{{ $record->{$spec['title']} }}</span></a></td>
        </tr>
    @endforeach</tbody>
</table></div>
{{ $records->links() }}
@endif
@endsection
