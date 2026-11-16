@php $local = $event->starts_at->copy()->timezone(config('boba.display_timezone')); @endphp
<li><a class="row" href="{{ route('events.show', $event->slug) }}">
    <span class="datebox"><strong>{{ $local->format('d') }}</strong><span>{{ $local->format('M Y') }}</span></span>
    <span>
        @if ($event->status === 'cancelled')<x-status value="cancelled" />@endif
        <h3>{{ $event->title }}</h3>
        <p>{{ $local->format('l, g:i a') }}@if ($event->location) · {{ $event->location }}@endif</p>
    </span>
    <span class="row-end text-link">Details</span>
</a></li>
