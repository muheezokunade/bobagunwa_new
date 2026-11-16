@extends('layouts.app', ['area' => 'admin', 'title' => 'Media library'])
@section('content')
<x-page-head title="Media library" subtitle="Public images for news, activities, leadership and events. Uploaded images are re-encoded and resized on upload.">
    <a class="btn secondary" href="{{ route('admin.content.index') }}">All content</a>
</x-page-head>
@if (auth()->user()->hasPermission('content.manage'))
<form class="panel" method="post" action="{{ route('admin.content.media.upload') }}" enctype="multipart/form-data">
    @csrf
    <h2>Upload an image</h2>
    <x-field name="image" label="Image file" type="file" required accept="image/jpeg,image/png" hint="JPEG or PNG, up to {{ number_format((int) config('boba.uploads.image_max_kb') / 1024, 0) }} MB." />
    <x-field name="alt_text" label="Description for screen readers (alt text)" required hint="Describe what the image shows, e.g. “Members at the 2025 annual general meeting”." />
    <x-check name="rights_confirmed" label="The association has permission to publish this image, including from anyone clearly shown in it." />
    <button class="btn" type="submit">Upload image</button>
</form>
@endif
<h2>Images</h2>
@if ($media->isEmpty())
    <x-empty title="No images yet"><p>Uploaded images will appear here.</p></x-empty>
@else
<div class="media-grid">
    @foreach ($media as $m)
        <figure>
            <img class="thumb" src="{{ $m->url() }}" alt="{{ $m->alt_text }}" width="{{ $m->width }}" height="{{ $m->height }}" loading="lazy">
            <figcaption>
                {{ $m->original_name }}<br>
                <span class="muted">{{ $m->width }} × {{ $m->height }} · {{ number_format($m->size_bytes / 1024, 0) }} KB · {{ \App\Support\Format::date($m->created_at) }}</span>
                @unless ($m->alt_text)<br><span class="badge warn">No alt text</span>@endunless
            </figcaption>
        </figure>
    @endforeach
</div>
{{ $media->links() }}
@endif
@endsection
