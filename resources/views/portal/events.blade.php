@extends('layouts.app', ['area' => 'portal', 'title' => 'Events'])
@section('content')
<x-page-head title="Events" subtitle="Public and member-only gatherings." />
<h2>Upcoming</h2>
@if ($upcoming->isEmpty())
    <x-empty title="No upcoming events"><p>Events will appear here when published.</p></x-empty>
@else
<div class="table-wrap" tabindex="0" role="region" aria-label="Table (scrolls sideways on small screens)"><table>
    <thead><tr><th scope="col">Event</th><th scope="col">When</th><th scope="col">Where</th><th scope="col">For</th><th scope="col">Your response</th></tr></thead>
    <tbody>@foreach ($upcoming as $event)
        <tr><td><a href="{{ route('portal.events.show', $event) }}">{{ $event->title }}</a> @if ($event->status === 'cancelled')<x-status value="cancelled" />@endif</td><td>{{ \App\Support\Format::dateTime($event->starts_at) }}</td><td>{{ $event->location ?: '—' }}</td><td>{{ $event->audience === 'members' ? 'Members' : 'Public' }}</td><td>@if (isset($responses[$event->id]))<x-status :value="$responses[$event->id]" />@elseif ($event->rsvp_enabled) <a href="{{ route('portal.events.show', $event) }}">Respond</a> @else — @endif</td></tr>
    @endforeach</tbody>
</table></div>
@endif
@if ($past->isNotEmpty())
    <h2>Recent</h2>
    <ul>@foreach ($past as $event)<li><a href="{{ route('portal.events.show', $event) }}">{{ $event->title }}</a> — {{ \App\Support\Format::date($event->starts_at) }}</li>@endforeach</ul>
@endif
@endsection
