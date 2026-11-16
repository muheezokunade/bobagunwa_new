@extends('layouts.public', ['title' => $gallery->title])
@section('content')
<x-public-hero :title="$gallery->title" crumb="Gallery" :lead="$gallery->description" />
<section class="section">
    <div class="wrap">
        <div class="gallery-grid">
            @foreach ($gallery->items as $item)
                <figure><img src="{{ $item->media->url() }}" alt="{{ $item->media->alt_text }}" width="{{ $item->media->width }}" height="{{ $item->media->height }}" loading="lazy">@if ($item->caption)<figcaption>{{ $item->caption }}</figcaption>@endif</figure>
            @endforeach
        </div>
        <p><a class="text-link" href="{{ route('gallery.index') }}">All galleries</a></p>
    </div>
</section>
@endsection
