@props(['title', 'em' => null, 'lead' => null, 'crumb' => null])
<section class="page-hero">
    <div class="wrap">
        <div>
            <ol class="breadcrumb" aria-label="Breadcrumb">
                <li><a href="{{ route('home') }}">Home</a></li>
                <li aria-current="page">{{ $crumb ?? trim($title.' '.$em) }}</li>
            </ol>
            <h1>{{ $title }}@if ($em) <em>{{ $em }}</em>@endif</h1>
            @if ($lead)<p class="lead">{{ $lead }}</p>@endif
        </div>
        <img src="{{ asset('images/crest-240.webp') }}" alt="" width="150" height="166">
    </div>
</section>
