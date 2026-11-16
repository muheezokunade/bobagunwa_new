@extends('layouts.public', ['title' => $event->title, 'description' => $event->summary])
@section('content')
<x-public-hero :title="$event->title" crumb="Event" :lead="$event->summary" />
<section class="section">
    <div class="wrap split wide-left">
        <div>
            @if ($event->status === 'cancelled')<div class="alert warning" role="status">This event has been cancelled.</div>@endif
            @if ($event->cover)<img src="{{ $event->cover->url() }}" alt="{{ $event->cover->alt_text }}" width="{{ $event->cover->width }}" height="{{ $event->cover->height }}">@endif
            <div class="prose">{{ \App\Support\Content::markdown($event->body) }}</div>
        </div>
        <aside class="panel">
            @php $tz = config('boba.display_timezone'); @endphp
            <dl class="details">
                <dt>Date</dt><dd>{{ $event->starts_at->copy()->timezone($tz)->format('l j F Y') }}</dd>
                <dt>Time</dt><dd>{{ $event->starts_at->copy()->timezone($tz)->format('g:i a') }}@if ($event->ends_at) – {{ $event->ends_at->copy()->timezone($tz)->format('g:i a') }}@endif (WAT)</dd>
                @if ($event->location)<dt>Location</dt><dd>{{ $event->location }}</dd>@endif
            </dl>
            @if ($event->rsvp_enabled)<p>Members can respond in the <a href="{{ route('portal.events') }}">member portal</a>.</p>@endif
            <a class="text-link" href="{{ route('events.index') }}">All events</a>
        </aside>
    </div>
</section>
@endsection
