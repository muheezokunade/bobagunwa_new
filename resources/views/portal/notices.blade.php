@extends('layouts.app', ['area' => 'portal', 'title' => 'Notices'])
@section('content')
<x-page-head title="Notices" subtitle="Announcements for members of the association." />
@forelse ($notices as $notice)
    <article class="panel">
        <h2>{{ $notice->title }} @unless ($read->has($notice->id))<span class="badge info">New</span>@endunless</h2>
        <p class="muted small">{{ \App\Support\Format::date($notice->published_at) }}</p>
        <div class="prose">{{ \App\Support\Content::markdown($notice->body) }}</div>
        @unless ($read->has($notice->id))
            <form method="post" action="{{ route('portal.notices.read', $notice) }}">@csrf<button class="btn small secondary" type="submit">Mark as read</button></form>
        @endunless
    </article>
@empty
    <x-empty title="No notices"><p>Notices from the secretariat will appear here.</p></x-empty>
@endforelse
{{ $notices->links() }}
@endsection
