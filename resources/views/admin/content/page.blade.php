@extends('layouts.app', ['area' => 'admin', 'title' => $page->label])
@section('content')
@php
    $F = \App\Support\Format::class;
    $latest = $revisions->first();
    $canEdit = auth()->user()->hasPermission('content.manage');
    $canPublish = auth()->user()->hasPermission('content.publish');
@endphp
<x-page-head :title="$page->label" subtitle="Saving creates a new draft revision. The public page changes only when a revision is published.">
    <a class="btn secondary" href="{{ route('admin.content.index') }}">All content</a>
</x-page-head>

<section class="panel">
    <h2>Published version</h2>
    @if ($page->publishedRevision)
        <p><x-status value="published" /> Revision {{ $page->publishedRevision->id }} — “{{ $page->publishedRevision->title }}”, published {{ $F::dateTime($page->published_at) }}.</p>
        @if ($latest && $latest->id !== $page->publishedRevision->id)<p class="small">A newer draft (revision {{ $latest->id }}) has not been published.</p>@endif
        @if ($canPublish)
            <form method="post" action="{{ route('admin.content.pages.unpublish', $page) }}" data-confirm="Unpublish this page? The public website will stop showing this text.">
                @csrf
                <button class="btn small danger" type="submit">Unpublish page</button>
            </form>
        @endif
    @else
        <p class="muted">This page is not published. The public website shows its standard holding text until a revision is published.</p>
    @endif
</section>

<div class="grid-2">
    @if ($canEdit)
    <form class="panel" method="post" action="{{ route('admin.content.pages.save', $page) }}">
        @csrf
        <h2>Edit draft</h2>
        <x-field name="title" label="Title" :value="$latest?->title" required />
        <x-textarea name="summary" label="Summary" :value="$latest?->summary" rows="3" hint="One or two sentences shown under the title and in search results." />
        <x-textarea name="body" label="Page text (Markdown)" :value="$latest?->body" rows="18" required hint="Leave a blank line between paragraphs. Start a line with ## for a heading, - for a list item. Write links as [link text](https://example.org). HTML is removed." />
        <x-field name="change_note" label="What changed?" hint="A short note for other officers, e.g. “Corrected founding year”." />
        <button class="btn" type="submit">Save draft</button>
    </form>
    @endif
    <section class="panel">
        <h2>Preview of the latest draft</h2>
        @if ($latest)
            <h3>{{ $latest->title }}</h3>
            @if ($latest->summary)<p class="lead">{{ $latest->summary }}</p>@endif
            <div class="prose">{{ \App\Support\Content::markdown($latest->body) }}</div>
        @else
            <p class="muted">No draft has been written yet.</p>
        @endif
    </section>
</div>

<h2>Revisions</h2>
@if ($revisions->isEmpty())
    <x-empty title="No revisions yet"><p>Save a draft to create the first revision.</p></x-empty>
@else
<div class="table-wrap" tabindex="0" role="region" aria-label="Table (scrolls sideways on small screens)"><table>
    <thead><tr><th scope="col">Revision</th><th scope="col">Title</th><th scope="col">Author</th><th scope="col">Saved</th><th scope="col">Note</th><th scope="col">State</th>@if ($canPublish)<th scope="col"><span class="sr-only">Actions</span></th>@endif</tr></thead>
    <tbody>@foreach ($revisions as $revision)
        @php $isPublished = $revision->id === $page->published_revision_id; @endphp
        <tr>
            <td>{{ $revision->id }}</td>
            <td>{{ $revision->title }}</td>
            <td>{{ $revision->author?->name ?? '—' }}</td>
            <td>{{ $F::dateTime($revision->created_at) }}</td>
            <td>{{ $revision->change_note ?: '—' }}</td>
            <td>@if ($isPublished)<x-status value="published" />@else<span class="muted">Draft</span>@endif</td>
            @if ($canPublish)
            <td>
                @unless ($isPublished)
                    <form class="inline-form" method="post" action="{{ route('admin.content.pages.publish', [$page, $revision]) }}" data-confirm="Publish revision {{ $revision->id }}? It will replace the public page immediately.">
                        @csrf
                        <button class="btn small" type="submit">Publish <span class="sr-only">revision {{ $revision->id }}</span></button>
                    </form>
                @endunless
            </td>
            @endif
        </tr>
    @endforeach</tbody>
</table></div>
<p class="small muted">The 20 most recent revisions are shown.</p>
@endif
@endsection
