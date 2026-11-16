@extends('layouts.app', ['area' => 'portal', 'title' => $event->title])
@section('content')
<x-page-head :title="$event->title" :subtitle="\App\Support\Format::dateTime($event->starts_at).($event->location ? ' · '.$event->location : '')" />
@if ($event->status === 'cancelled')<div class="alert warning" role="status">This event has been cancelled.</div>@endif
<div class="grid-2">
    <div class="panel">
        @if ($event->summary)<p class="lead">{{ $event->summary }}</p>@endif
        <div class="prose">{{ \App\Support\Content::markdown($event->body) }}</div>
    </div>
    <div class="panel">
        <h2>Your response</h2>
        @if ($event->rsvpOpen())
            <form method="post" action="{{ route('portal.events.rsvp', $event) }}">
                @csrf
                <x-select name="response" label="Will you attend?" required :value="$rsvp?->response" placeholder="Choose…" :options="['attending' => 'Yes, I will attend', 'maybe' => 'Maybe', 'not_attending' => 'No, I cannot attend']" />
                <x-field name="guests" label="Additional guests" type="number" min="0" max="10" :value="$rsvp?->guests ?? 0" />
                <x-field name="note" label="Note to the organisers (optional)" :value="$rsvp?->note" />
                <button class="btn" type="submit">Save response</button>
            </form>
            @if ($event->capacity)<p class="small muted">{{ $attending }} member(s) attending; capacity {{ $event->capacity }}.</p>@endif
        @elseif ($rsvp)
            <p>You responded: <x-status :value="$rsvp->response" />. Responses are now closed.</p>
        @else
            <p class="muted">Responses are not being collected for this event.</p>
        @endif
    </div>
</div>
<p><a class="text-link" href="{{ route('portal.events') }}">All events</a></p>
@endsection
