@extends('layouts.public', ['title' => $post->title, 'description' => $post->excerpt])
@section('content')
<x-public-hero :title="$post->title" crumb="News" />
<article class="section">
    <div class="narrow">
        <p class="muted">Published {{ \App\Support\Format::date($post->published_at) }}</p>
        @if ($post->cover)<img src="{{ $post->cover->url() }}" alt="{{ $post->cover->alt_text }}" width="{{ $post->cover->width }}" height="{{ $post->cover->height }}">@endif
        @if ($post->excerpt)<p class="lead">{{ $post->excerpt }}</p>@endif
        <div class="prose">{{ \App\Support\Content::markdown($post->body) }}</div>
        <p><a class="text-link" href="{{ route('news.index') }}">All news</a></p>
    </div>
</article>
@endsection
