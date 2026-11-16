@extends('layouts.public', ['title' => $key === 'privacy' ? 'Privacy notice' : 'Terms of use'])
@section('content')
<x-public-hero :title="$key === 'privacy' ? 'Privacy notice' : 'Terms of use'" />
<section class="section">
    <div class="narrow">
        @if ($page)
            <p class="muted small">Published {{ \App\Support\Format::date($page->created_at) }}</p>
            <div class="prose">{{ \App\Support\Content::markdown($page->body) }}</div>
        @else
            <div class="alert warning" role="note">This is the working version of the {{ $key === 'privacy' ? 'privacy notice' : 'terms' }}. The final wording is awaiting the association’s review.</div>
            <div class="prose">{{ \App\Support\Content::markdown(view('public.legal-'.$key)->render()) }}</div>
        @endif
    </div>
</section>
@endsection
