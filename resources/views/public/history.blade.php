@extends('layouts.public', ['title' => 'History and heritage'])
@section('content')
<x-public-hero title="History and" em="heritage" crumb="History and heritage" :lead="$page?->summary" />
<section class="section">
    <div class="narrow">
        @if ($page)
            <h2>{{ $page->title }}</h2>
            <div class="prose">{{ \App\Support\Content::markdown($page->body) }}</div>
            <p class="muted small">Last published {{ \App\Support\Format::date($page->created_at) }}.</p>
        @else
            <x-empty title="Our history is being prepared">
                <p>The association’s official history is being reviewed by its officers before publication. Please check back soon, or <a href="{{ route('contact') }}">contact the secretariat</a>.</p>
            </x-empty>
        @endif
    </div>
</section>
@endsection
