@extends('layouts.public', ['title' => 'Activities'])
@section('content')
<x-public-hero title="Activities" lead="How the association turns fellowship into service." />
<section class="section">
    <div class="wrap">
        @if ($activities->isEmpty())
            <x-empty title="No activities published yet"><p>Activities appear here once the secretariat publishes them.</p></x-empty>
        @else
            <div class="feature-grid">
                @foreach ($activities as $activity)
                    <article>
                        @if ($activity->media)<img src="{{ $activity->media->url() }}" alt="{{ $activity->media->alt_text }}" width="{{ $activity->media->width }}" height="{{ $activity->media->height }}" loading="lazy">@endif
                        @if ($activity->category)<span class="tag">{{ $activity->category }}</span>@endif
                        <h3>{{ $activity->title }}</h3>
                        <p>{{ $activity->summary }}</p>
                        @if ($activity->body)<div class="prose small">{{ \App\Support\Content::markdown($activity->body) }}</div>@endif
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
