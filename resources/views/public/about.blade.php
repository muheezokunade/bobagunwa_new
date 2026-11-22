@extends('layouts.public', ['title' => 'About', 'description' => 'About Ẹgbẹ́ Bobagunwa Okunrin Akile Ijebu: identity, purpose and values.'])
@section('content')
<x-public-hero title="About" em="Bobagunwa" crumb="About" :lead="$page?->summary" />
<section class="section">
    <div class="wrap split wide-left">
        <div>
            @if ($page)
                <h2>{{ $page->title }}</h2>
                <div class="prose">{{ \App\Support\Content::markdown($page->body) }}</div>
            @else
                <p class="kicker">About the association</p>
                <h2>A fellowship formed by shared identity and <em>purpose.</em></h2>
                <p class="lead">Ẹgbẹ́ Bobagunwa Okunrin Akile Ijebu is an association of Ijebu men born between 1980 and 1982, united by heritage, fellowship and service.</p>
                <p class="muted">A fuller introduction to the association is being prepared for publication by the secretariat.</p>
            @endif
            <div class="actions">
                <a class="btn secondary" href="{{ route('history') }}">History and heritage</a>
                <a class="btn secondary" href="{{ route('leadership') }}">Leadership</a>
                <a class="btn secondary" href="{{ route('activities') }}">Activities</a>
            </div>
        </div>
        <aside class="heritage-panel" aria-label="The crest">
            <p class="top"><span>AKILE IJEBU</span><span>1980–82</span></p>
            <img src="{{ asset('images/crest-480.webp') }}" alt="The crest of Ẹgbẹ́ Bobagunwa Okunrin Akile Ijebu" width="300" height="332" loading="lazy">
            <p class="motto">{{ $org['motto'] }}</p>
        </aside>
    </div>
</section>
<section class="section dark">
    <div class="wrap">
        <p class="kicker on-dark">What guides us</p>
        <h2>United by values. <em>Committed to service.</em></h2>
        @include('partials.values')
    </div>
</section>
@endsection
