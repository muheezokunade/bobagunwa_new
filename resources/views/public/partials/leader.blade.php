@php $profileId = 'leader-'.$leader->id; @endphp
<figure>
    {{-- The whole card opens the profile. Without JavaScript the link jumps to the profile, which CSS shows via :target. --}}
    <a class="person-link" href="#{{ $profileId }}" data-dialog-open="{{ $profileId }}" aria-haspopup="dialog">
        @if ($leader->photo)
            <img src="{{ $leader->photo->url() }}" alt="{{ $leader->photo->alt_text ?: 'Photograph of '.$leader->name }}" width="{{ $leader->photo->width }}" height="{{ $leader->photo->height }}" loading="lazy">
        @else
            <div class="placeholder"><img src="{{ asset('images/crest-160.webp') }}" alt="" width="80" height="88"></div>
        @endif
        <figcaption>
            <span>{{ $leader->office }}</span>
            <strong>{{ $leader->name }}</strong>
            @if ($leader->term_label)<small class="muted">{{ $leader->term_label }}</small>@endif
            <small class="person-more">View profile</small>
        </figcaption>
    </a>
</figure>
<dialog id="{{ $profileId }}" class="person-dialog" aria-labelledby="{{ $profileId }}-name">
    <div class="person-dialog-body {{ $leader->photo ? 'has-photo' : '' }}">
        @if ($leader->photo)
            <img src="{{ $leader->photo->url() }}" alt="{{ $leader->photo->alt_text ?: 'Photograph of '.$leader->name }}" width="{{ $leader->photo->width }}" height="{{ $leader->photo->height }}" loading="lazy">
        @endif
        <div>
            <p class="tag">{{ $leader->office }}</p>
            <h3 id="{{ $profileId }}-name">{{ $leader->name }}</h3>
            @if ($leader->term_label)<p class="muted">{{ $leader->term_label }}</p>@endif
            @if (filled($leader->bio))
                <p class="person-bio">{{ $leader->bio }}</p>
            @else
                <p class="muted">A profile for this office will be published once it has been approved.</p>
            @endif
        </div>
    </div>
    <form method="dialog" class="person-dialog-close">
        <a class="btn small secondary" href="#" data-dialog-close>Close</a>
    </form>
</dialog>
