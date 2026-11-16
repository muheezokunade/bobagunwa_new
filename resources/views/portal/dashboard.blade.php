@extends('layouts.app', ['area' => 'portal', 'title' => 'Overview'])
@section('content')
<x-page-head :title="'Welcome, '.$member->displayName()" :subtitle="'Membership number '.$member->membership_number" />
<dl class="stats">
    <div><dt>Membership status</dt><dd><x-status :value="$member->status" /></dd></div>
    <div><dt>Outstanding dues and levies</dt><dd class="value wine"><x-money :kobo="$summary['outstanding']" /></dd></div>
    <div><dt>Unallocated credit</dt><dd><x-money :kobo="$summary['unallocated_credit']" /></dd></div>
    <div><dt>Payments awaiting verification</dt><dd><x-money :kobo="$summary['pending']" /></dd></div>
</dl>
<div class="grid-2">
    <section class="panel" aria-labelledby="notices-h">
        <h2 id="notices-h">Notices @if ($unread)<span class="badge info">{{ $unread }} unread</span>@endif</h2>
        @forelse ($notices as $notice)
            <p><strong>{{ $notice->title }}</strong><br><span class="muted small">{{ \App\Support\Format::date($notice->published_at) }}</span></p>
        @empty
            <p class="muted">There are no notices at the moment.</p>
        @endforelse
        <a class="text-link" href="{{ route('portal.notices') }}">All notices</a>
    </section>
    <section class="panel" aria-labelledby="events-h">
        <h2 id="events-h">Upcoming events</h2>
        @forelse ($events as $event)
            <p><a href="{{ route('portal.events.show', $event) }}"><strong>{{ $event->title }}</strong></a><br><span class="muted small">{{ \App\Support\Format::dateTime($event->starts_at) }}</span></p>
        @empty
            <p class="muted">No upcoming events have been published.</p>
        @endforelse
        <a class="text-link" href="{{ route('portal.events') }}">All events</a>
    </section>
</div>
<section class="panel">
    <h2>Quick links</h2>
    <div class="actions">
        <a class="btn secondary" href="{{ route('portal.finances') }}">Dues and payments</a>
        <a class="btn secondary" href="{{ route('portal.statement') }}">My statement</a>
        <a class="btn secondary" href="{{ route('portal.profile') }}">Update my details</a>
    </div>
</section>
@endsection
