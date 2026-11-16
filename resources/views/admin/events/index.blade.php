@extends('layouts.app', ['area' => 'admin', 'title' => 'Events'])
@section('content')
<x-page-head title="Events" subtitle="Public and members-only events. Times are shown in Lagos time.">
    <a class="btn" href="{{ route('admin.events.create') }}">New event</a>
</x-page-head>
@if ($events->isEmpty())
    <x-empty title="No events yet"><p>Create an event to publish it on the website or in the member portal.</p></x-empty>
@else
<div class="table-wrap"><table>
    <thead><tr><th scope="col">Event</th><th scope="col">Starts</th><th scope="col">Audience</th><th scope="col">Status</th><th scope="col" class="num">Attending</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>@foreach ($events as $event)
        <tr>
            <td>{{ $event->title }}@if ($event->location)<br><span class="muted small">{{ $event->location }}</span>@endif</td>
            <td>{{ \App\Support\Format::dateTime($event->starts_at) }}</td>
            <td>{{ $event->audience === 'members' ? 'Members only' : 'Public' }}</td>
            <td><x-status :value="$event->status" /></td>
            <td class="num">{{ $event->rsvp_enabled ? $event->attending_count : '—' }}</td>
            <td><a class="text-link" href="{{ route('admin.events.edit', $event) }}">Edit <span class="sr-only">{{ $event->title }}</span></a></td>
        </tr>
    @endforeach</tbody>
</table></div>
{{ $events->links() }}
@endif
@endsection
