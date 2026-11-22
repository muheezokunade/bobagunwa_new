@extends('layouts.public', ['title' => 'History and heritage'])
@section('content')
<x-public-hero title="History and" em="heritage" crumb="History and heritage" :lead="$page?->summary" />
<section class="section">
    <div class="narrow">
        @if ($page)
            <h2>{{ $page->title }}</h2>
            <div class="prose">{{ \App\Support\Content::markdown($page->body) }}</div>
            <p class="muted small">Last published {{ \App\Support\Format::date($page->created_at) }}.</p>
        @elseif ($figures->isEmpty())
            <x-empty title="Our history is being prepared">
                <p>The association’s official history is being reviewed by its officers before publication. Please check back soon, or <a href="{{ route('contact') }}">contact the secretariat</a>.</p>
            </x-empty>
        @endif
    </div>
</section>
@if ($figures->isNotEmpty())
<section class="section mist" aria-labelledby="figures-title">
    <div class="wrap">
        <h2 id="figures-title">The Awujales of Ijebuland</h2>
        <p class="muted">Select a name to read the full history.</p>
        <div class="people people-lead">
            @foreach ($figures as $figure)
                @php $profileId = 'figure-'.$figure->id; @endphp
                <figure>
                    <a class="person-link" href="#{{ $profileId }}" data-dialog-open="{{ $profileId }}" aria-haspopup="dialog">
                        @if ($figure->photo)
                            <img src="{{ $figure->photo->url() }}" alt="{{ $figure->photo->alt_text ?: 'Picture of '.$figure->name }}" width="{{ $figure->photo->width }}" height="{{ $figure->photo->height }}" loading="lazy">
                        @else
                            <div class="placeholder"><img src="{{ asset('images/crest-160.webp') }}" alt="" width="80" height="88"></div>
                        @endif
                        <figcaption>
                            @if ($figure->title)<span>{{ $figure->title }}</span>@endif
                            <strong>{{ $figure->name }}</strong>
                            @if ($figure->years_label)<small class="muted">{{ $figure->years_label }}</small>@endif
                            <small class="person-summary">{{ $figure->summary }}</small>
                            <small class="person-more">Read the history</small>
                        </figcaption>
                    </a>
                </figure>
                <dialog id="{{ $profileId }}" class="person-dialog" aria-labelledby="{{ $profileId }}-name">
                    <div class="person-dialog-body {{ $figure->photo ? 'has-photo' : '' }}">
                        @if ($figure->photo)
                            <img src="{{ $figure->photo->url() }}" alt="{{ $figure->photo->alt_text ?: 'Picture of '.$figure->name }}" width="{{ $figure->photo->width }}" height="{{ $figure->photo->height }}" loading="lazy">
                        @endif
                        <div>
                            @if ($figure->title)<p class="tag">{{ $figure->title }}</p>@endif
                            <h3 id="{{ $profileId }}-name">{{ $figure->name }}</h3>
                            @if ($figure->years_label)<p class="muted">{{ $figure->years_label }}</p>@endif
                            <div class="prose">{{ \App\Support\Content::markdown($figure->body ?: $figure->summary) }}</div>
                        </div>
                    </div>
                    <form method="dialog" class="person-dialog-close"><a class="btn small secondary" href="#" data-dialog-close>Close</a></form>
                </dialog>
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection
