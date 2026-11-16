@extends('layouts.public', ['title' => 'Events'])
@section('content')
<x-public-hero title="Events" lead="Gatherings of the association open to the public." />
<section class="section">
    <div class="wrap">
        <h2>Upcoming</h2>
        @if ($upcoming->isEmpty())
            <x-empty title="No public events scheduled"><p>Members can see member-only gatherings in the <a href="{{ route('login') }}">member portal</a>.</p></x-empty>
        @else
            <ul class="listing">@foreach ($upcoming as $event) @include('public.partials.event-row') @endforeach</ul>
        @endif
        @if ($past->isNotEmpty())
            <h2 class="section-gap">Past events</h2>
            <ul class="listing">@foreach ($past as $event) @include('public.partials.event-row') @endforeach</ul>
        @endif
    </div>
</section>
@endsection
