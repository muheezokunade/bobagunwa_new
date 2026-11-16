@extends('layouts.public', ['title' => 'Gallery'])
@section('content')
<x-public-hero title="Gallery" lead="Photographs from the association’s gatherings, published with permission." />
<section class="section">
    <div class="wrap">
        @if ($galleries->isEmpty())
            <x-empty title="No galleries published yet"><p>Photographs appear here once approved for publication.</p></x-empty>
        @else
            <div class="feature-grid">
                @foreach ($galleries as $gallery)
                    <article>
                        @if ($first = $gallery->items->first()?->media)<img src="{{ $first->url() }}" alt="{{ $first->alt_text }}" width="{{ $first->width }}" height="{{ $first->height }}" loading="lazy">@endif
                        <h3><a href="{{ route('gallery.show', $gallery->slug) }}">{{ $gallery->title }}</a></h3>
                        <p>{{ $gallery->description }}</p>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
