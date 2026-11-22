@extends('layouts.public', ['description' => 'Ẹgbẹ́ Bobagunwa Okunrin Akile Ijebu brings together Ijebu men committed to protecting their heritage, strengthening fellowship and serving their community.'])
@section('content')
<section class="hero" aria-labelledby="hero-title">
    <div class="hero-copy">
        <p class="kicker on-dark">Ẹgbẹ́ Bobagunwa Okunrin Akile Ijebu</p>
        <h1 id="hero-title">Heritage.<br><em>Brotherhood.</em><br>Service.</h1>
        <p class="lead">A fellowship of Ijebu sons, united by culture, integrity and a shared commitment to the progress of our people.</p>
        <div class="actions">
            <a class="btn light" href="{{ route('about') }}">Discover our story <span class="arrow" aria-hidden="true">→</span></a>
            <a class="btn secondary light" href="{{ route('membership') }}">Membership</a>
        </div>
    </div>
    <div class="hero-art">
        <span class="hero-seal" aria-hidden="true">Ẹgbẹ́</span>
        <img src="{{ asset('images/crest-480.webp') }}" alt="The crest of Ẹgbẹ́ Bobagunwa Okunrin Akile Ijebu, 1980–82" width="400" height="443" fetchpriority="high">
        <p class="hero-motto">{{ $org['motto'] }}</p>
    </div>
</section>

@if ($welcome)
<section class="section" aria-labelledby="welcome-title">
    <div class="wrap narrow-text">
        <p class="kicker">Giwa’s welcome address</p>
        <h2 id="welcome-title">{{ $welcome->title }}</h2>
        <div class="prose welcome-address">{{ \App\Support\Content::markdown($welcome->body) }}</div>
    </div>
</section>
@endif

<section class="section" aria-labelledby="rooted-title">
    <div class="wrap split wide-left">
        <div>
            <p class="kicker">Rooted in Ijebu tradition</p>
            <h2 id="rooted-title">Building a legacy that honours yesterday and <em>shapes tomorrow.</em></h2>
        </div>
        <div>
            @if ($about && $about->summary)
                <p class="lead">{{ $about->summary }}</p>
            @else
                <p class="lead">Ẹgbẹ́ Bobagunwa Okunrin Akile Ijebu brings together men committed to protecting Ijebu heritage, strengthening social bonds and contributing to the wellbeing of the wider community.</p>
            @endif
            <a class="text-link" href="{{ route('about') }}">About the association</a>
        </div>
    </div>
</section>

<section class="section dark" aria-labelledby="values-title">
    <div class="wrap">
        <p class="kicker on-dark">What guides us</p>
        <h2 id="values-title">Our values are our <em>foundation.</em></h2>
        @include('partials.values')
    </div>
</section>

<section class="section mist" aria-labelledby="join-title">
    <div class="wrap split">
        <div>
            <p class="kicker">Membership</p>
            <h2 id="join-title">For Ijebu men born between <em>1980 and 1982.</em></h2>
        </div>
        <div>
            <p>Membership is open to Ijebu men born between 1 January 1980 and 31 December 1982. Applications are made online and reviewed by the association’s officers. Applying does not by itself confer membership.</p>
            <div class="actions">
                <a class="btn" href="{{ route('membership.apply') }}">Apply for membership <span class="arrow" aria-hidden="true">→</span></a>
                <a class="btn secondary" href="{{ route('membership') }}">How membership works</a>
            </div>
        </div>
    </div>
</section>

@if ($events->isNotEmpty() || $news->isNotEmpty())
<section class="section" aria-labelledby="updates-title">
    <div class="wrap">
        <p class="kicker">From the association</p>
        <h2 id="updates-title">News and gatherings.</h2>
        <ul class="listing">
            @foreach ($events as $event)
                <li><a class="row" href="{{ route('events.show', $event->slug) }}">
                    @php $local = $event->starts_at->copy()->timezone(config('boba.display_timezone')); @endphp
                    <span class="datebox"><strong>{{ $local->format('d') }}</strong><span>{{ $local->format('M') }}</span></span>
                    <span><span class="tag">Event</span><h3>{{ $event->title }}</h3><p>{{ $event->location }}</p></span>
                    <span class="row-end text-link">Details</span>
                </a></li>
            @endforeach
            @foreach ($news as $post)
                <li><a class="row" href="{{ route('news.show', $post->slug) }}">
                    <span class="datebox"><strong>{{ $post->published_at->format('d') }}</strong><span>{{ $post->published_at->format('M') }}</span></span>
                    <span><span class="tag">News</span><h3>{{ $post->title }}</h3><p>{{ $post->excerpt }}</p></span>
                    <span class="row-end text-link">Read</span>
                </a></li>
            @endforeach
        </ul>
        <div class="actions"><a class="text-link" href="{{ route('events.index') }}">All events</a> <a class="text-link" href="{{ route('news.index') }}">All news</a></div>
    </div>
</section>
@endif

@if ($leaders->isNotEmpty())
<section class="section mist" aria-labelledby="leaders-title">
    <div class="wrap">
        <p class="kicker">Giwa-in-Council</p>
        <h2 id="leaders-title">Service before self.</h2>
        <div class="people">
            @foreach ($leaders as $leader)
                @include('public.partials.leader', ['leader' => $leader])
            @endforeach
        </div>
        <div class="actions"><a class="text-link" href="{{ route('leadership') }}">Full leadership</a></div>
    </div>
</section>
@endif

<section class="section wine" aria-labelledby="contact-title">
    <div class="wrap cta-band">
        <h2 id="contact-title">Culture gives us roots. <em class="yo">Brotherhood gives us strength.</em></h2>
        <a class="btn light" href="{{ route('contact') }}">Contact the secretariat <span class="arrow" aria-hidden="true">→</span></a>
    </div>
</section>
@endsection
