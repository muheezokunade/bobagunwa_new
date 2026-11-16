@extends('layouts.app', ['area' => 'admin', 'title' => $event ? 'Edit event' : 'New event'])
@section('content')
@php
    $F = \App\Support\Format::class;
    $tz = config('boba.display_timezone');
    $local = fn ($value) => $value?->copy()->timezone($tz)->format('Y-m-d\TH:i');
    $transitions = ['draft' => ['published', 'archived'], 'published' => ['cancelled', 'archived', 'draft'], 'cancelled' => ['archived', 'published'], 'archived' => ['draft']];
    $actionLabels = ['published' => 'Publish', 'draft' => 'Return to draft', 'cancelled' => 'Mark as cancelled', 'archived' => 'Archive'];
    $mediaOptions = $media->mapWithKeys(fn ($m) => [$m->id => $m->original_name.($m->alt_text ? ' — '.$m->alt_text : '')])->all();
@endphp
<x-page-head :title="$event ? $event->title : 'New event'" subtitle="Enter dates and times in Lagos time.">
    <a class="btn secondary" href="{{ route('admin.events.index') }}">All events</a>
</x-page-head>

@if ($event)
<section class="panel">
    <h2>Publication</h2>
    <p>Status: <x-status :value="$event->status" />@if ($event->published_at) <span class="muted small">First published {{ $F::dateTime($event->published_at) }}</span>@endif</p>
    <div class="actions">
        @foreach ($transitions[$event->status] ?? [] as $target)
            <form class="inline-form" method="post" action="{{ route('admin.events.status', $event) }}" @if ($target !== 'draft') data-confirm="{{ $actionLabels[$target] }} “{{ $event->title }}”?" @endif>
                @csrf
                <input type="hidden" name="status" value="{{ $target }}">
                <button class="btn small {{ $target === 'published' ? '' : ($target === 'cancelled' ? 'danger' : 'secondary') }}" type="submit">{{ $actionLabels[$target] }}</button>
            </form>
        @endforeach
    </div>
    @if ($event->status === 'cancelled')<p class="small muted">A cancelled event stays visible with a cancellation notice until it is archived.</p>@endif
</section>
@endif

<form class="panel" method="post" action="{{ $event ? route('admin.events.update', $event) : route('admin.events.store') }}">
    @csrf
    <h2>Event details</h2>
    <x-field name="title" label="Title" :value="$event?->title" required />
    <x-textarea name="summary" label="Summary" :value="$event?->summary" rows="3" hint="One or two sentences shown in event lists." />
    <x-textarea name="body" label="Full description (Markdown)" :value="$event?->body" rows="12" hint="Leave a blank line between paragraphs; ## starts a heading; - starts a list item; links are written [link text](https://example.org)." />
    <x-field name="location" label="Location" :value="$event?->location" />
    <div class="form-grid">
        <x-field name="starts_at" label="Starts" type="datetime-local" :value="$local($event?->starts_at)" required />
        <x-field name="ends_at" label="Ends (optional)" type="datetime-local" :value="$local($event?->ends_at)" />
    </div>
    <x-select name="audience" label="Who can see this event?" :value="$event?->audience ?? 'members'" required :options="['public' => 'Everyone (public website and portal)', 'members' => 'Members only (portal)']" />
    <fieldset>
        <legend>Attendance responses</legend>
        <x-check name="rsvp_enabled" label="Ask members whether they will attend" :checked="(bool) $event?->rsvp_enabled" />
        <div class="form-grid">
            <x-field name="rsvp_closes_at" label="Responses close (optional)" type="datetime-local" :value="$local($event?->rsvp_closes_at)" hint="Must be no later than the start time." />
            <x-field name="capacity" label="Capacity (optional)" type="number" min="1" max="100000" :value="$event?->capacity" />
        </div>
    </fieldset>
    <x-select name="cover_media_id" label="Cover image" :options="$mediaOptions" :value="$event?->cover_media_id" placeholder="No image" />
    <p class="small muted">To use a new image, <a href="{{ route('admin.content.media') }}">upload it to the media library</a> first.</p>
    <button class="btn" type="submit">{{ $event ? 'Save changes' : 'Save as draft' }}</button>
</form>

@if ($event)
<section class="panel">
    <h2>Attendance responses</h2>
    @if ($rsvps->isEmpty())
        <p class="muted">No responses yet.</p>
    @else
        <p>
            {{ $rsvps->where('response', 'attending')->count() }} attending
            (plus {{ $rsvps->where('response', 'attending')->sum('guests') }} guests),
            {{ $rsvps->where('response', 'maybe')->count() }} maybe,
            {{ $rsvps->where('response', 'not_attending')->count() }} not attending.
            <a class="text-link" href="{{ route('admin.events.responses', $event) }}">Download responses (CSV)</a>
        </p>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Table (scrolls sideways on small screens)"><table>
            <thead><tr><th scope="col">Membership number</th><th scope="col">Name</th><th scope="col">Response</th><th scope="col" class="num">Guests</th><th scope="col">Note</th></tr></thead>
            <tbody>@foreach ($rsvps as $rsvp)
                <tr>
                    <td>{{ $rsvp->member?->membership_number }}</td>
                    <td>{{ $rsvp->member?->full_name }}</td>
                    <td><x-status :value="$rsvp->response" /></td>
                    <td class="num">{{ $rsvp->guests }}</td>
                    <td>{{ $rsvp->note ?: '—' }}</td>
                </tr>
            @endforeach</tbody>
        </table></div>
    @endif
</section>
@endif
@endsection
