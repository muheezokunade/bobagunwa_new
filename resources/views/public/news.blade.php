@extends('layouts.public', ['title' => 'News'])
@section('content')
<x-public-hero title="News" lead="Announcements and reports from the association." />
<section class="section">
    <div class="wrap">
        @if ($posts->isEmpty())
            <x-empty title="No news yet"><p>News published by the secretariat will appear here.</p></x-empty>
        @else
            <ul class="listing">
                @foreach ($posts as $post)
                    <li><a class="row" href="{{ route('news.show', $post->slug) }}">
                        <span class="datebox"><strong>{{ $post->published_at->format('d') }}</strong><span>{{ $post->published_at->format('M Y') }}</span></span>
                        <span><h3>{{ $post->title }}</h3><p>{{ $post->excerpt }}</p></span>
                        <span class="row-end text-link">Read</span>
                    </a></li>
                @endforeach
            </ul>
            {{ $posts->links() }}
        @endif
    </div>
</section>
@endsection
