<figure>
    @if ($leader->photo)
        <img src="{{ $leader->photo->url() }}" alt="{{ $leader->photo->alt_text ?: 'Photograph of '.$leader->name }}" width="{{ $leader->photo->width }}" height="{{ $leader->photo->height }}" loading="lazy">
    @else
        <div class="placeholder"><img src="{{ asset('images/crest-160.webp') }}" alt="" width="80" height="88"></div>
    @endif
    <figcaption>
        <span>{{ $leader->office }}</span>
        <strong>{{ $leader->name }}</strong>
        @if ($leader->term_label)<small class="muted">{{ $leader->term_label }}</small>@endif
    </figcaption>
</figure>
