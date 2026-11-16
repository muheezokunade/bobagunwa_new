@extends('layouts.app', ['area' => 'admin', 'title' => $record ? 'Edit '.$spec['singular'] : 'New '.$spec['singular']])
@section('content')
@php
    $F = \App\Support\Format::class;
    $canEdit = auth()->user()->hasPermission('content.manage');
    $canPublish = auth()->user()->hasPermission('content.publish');
    $multipart = collect($spec['fields'])->contains(fn ($f) => $f[2] === 'private_file');
    $mediaOptions = $media->mapWithKeys(fn ($m) => [$m->id => $m->original_name.($m->alt_text ? ' — '.$m->alt_text : '')])->all();
    $heading = $record ? ($record->{$spec['title']} ?: 'Edit '.$spec['singular']) : 'New '.$spec['singular'];
@endphp
<x-page-head :title="$heading" :subtitle="$spec['label']">
    <a class="btn secondary" href="{{ route('admin.content.list', $type) }}">All {{ strtolower($spec['label']) }}</a>
</x-page-head>

@if ($record)
<section class="panel">
    <h2>Publication</h2>
    <p>Status: <x-status :value="$record->status" /> <span class="muted small">Last updated {{ $F::dateTime($record->updated_at) }}</span></p>
    @if ($canPublish)
        <div class="actions">
            @foreach (['published' => 'Publish', 'draft' => 'Return to draft', 'archived' => 'Archive'] as $target => $label)
                @continue($target === $record->status)
                <form class="inline-form" method="post" action="{{ route('admin.content.status', [$type, $record->id]) }}" @if ($target !== 'draft') data-confirm="{{ $label }} this {{ $spec['singular'] }}?" @endif>
                    @csrf
                    <input type="hidden" name="status" value="{{ $target }}">
                    <button class="btn small {{ $target === 'published' ? '' : 'secondary' }}" type="submit">{{ $label }}</button>
                </form>
            @endforeach
        </div>
    @else
        <p class="small muted">A publisher must publish this {{ $spec['singular'] }} before it appears on the website.</p>
    @endif
</section>
@endif

@if ($canEdit)
<form class="panel" method="post" action="{{ $record ? route('admin.content.update', [$type, $record->id]) : route('admin.content.store', $type) }}" @if ($multipart) enctype="multipart/form-data" @endif>
    @csrf
    <h2>{{ $record ? 'Details' : 'New '.$spec['singular'] }}</h2>
    @foreach ($spec['fields'] as $field)
        @php
            [$name, $label, $kind, $required] = $field;
            $current = $record?->{$name};
        @endphp
        @switch($kind)
            @case('textarea')
                <x-textarea :name="$name" :label="$label" :value="$current" :required="$required" rows="4" />
                @break
            @case('markdown')
                <x-textarea :name="$name" :label="$label" :value="$current" :required="$required" rows="14" hint="Markdown: leave a blank line between paragraphs; ## starts a heading; - starts a list item; links are written [link text](https://example.org)." />
                @break
            @case('number')
                <x-field :name="$name" :label="$label" type="number" :value="$current ?? 0" :required="$required" min="-1000" max="1000" hint="Lower numbers are shown first." />
                @break
            @case('select')
                <x-select :name="$name" :label="$label" :options="$field[4] ?? []" :value="$current" :required="$required" placeholder="Choose…" />
                @break
            @case('media')
                <x-select :name="$name" :label="$label" :options="$mediaOptions" :value="$current" placeholder="No image" hint="Choose from the 60 most recent uploads." />
                <p class="small muted">To use a new image, <a href="{{ route('admin.content.media') }}">upload it to the media library</a> first.</p>
                @break
            @case('private_file')
                @if ($record && $record->file)
                    <p class="small">Current file: <strong>{{ $record->file->original_name }}</strong> ({{ number_format($record->file->size_bytes / 1024, 0) }} KB). Choose a new file only to replace it.</p>
                @endif
                <x-field :name="$name" :label="$label" type="file" :required="$required && ! $record" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" hint="Stored privately. Only signed-in members can download it once published." />
                @break
            @default
                <x-field :name="$name" :label="$label" :value="$current" :required="$required" />
        @endswitch
    @endforeach
    <button class="btn" type="submit">{{ $record ? 'Save changes' : 'Save draft' }}</button>
</form>
@endif

@if ($record && $type === 'galleries')
<section class="panel">
    <h2>Images in this gallery</h2>
    @if ($record->items->isEmpty())
        <p class="muted">No images have been added yet.</p>
    @else
        <div class="media-grid">
            @foreach ($record->items as $item)
                <figure>
                    @if ($item->media)
                        <img class="thumb" src="{{ $item->media->url() }}" alt="{{ $item->media->alt_text }}" width="{{ $item->media->width }}" height="{{ $item->media->height }}" loading="lazy">
                    @endif
                    <figcaption>{{ $item->caption ?: ($item->media?->alt_text ?? '') }}</figcaption>
                </figure>
            @endforeach
        </div>
    @endif
    @if ($canEdit)
        <form method="post" action="{{ route('admin.content.galleries.items', $record->id) }}" enctype="multipart/form-data">
            @csrf
            <h3>Add an image</h3>
            <x-field prefix="g-" name="image" label="Image file" type="file" required accept="image/jpeg,image/png" hint="JPEG or PNG." />
            <x-field prefix="g-" name="alt_text" label="Description for screen readers (alt text)" required hint="Describe what the image shows." />
            <x-field prefix="g-" name="caption" label="Caption (optional)" />
            <x-check prefix="g-" name="rights_confirmed" label="The association has permission to publish this image (and consent from anyone pictured)." />
            <button class="btn" type="submit">Add image</button>
        </form>
    @endif
</section>
@endif
@endsection
