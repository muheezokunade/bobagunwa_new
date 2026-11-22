<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ $org['name'] }}</title>
    <meta name="description" content="{{ $description ?? 'Ẹgbẹ́ Bobagunwa Okunrin Akile Ijebu — an association of Ijebu men preserving heritage, fellowship and service.' }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:title" content="{{ $title ?? $org['name'] }}">
    <meta property="og:description" content="{{ $description ?? 'Heritage, brotherhood and service.' }}">
    <meta property="og:image" content="{{ asset('images/og.png') }}">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @if (! empty($noindex)) <meta name="robots" content="noindex, nofollow"> @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
@if ($org['publish_contact'] && ($org['email'] || $org['address']))
    <div class="topbar">
        <div class="wrap">
            @if ($org['address'])<span>{{ $org['address'] }}</span>@endif
            @if ($org['email'])<a href="mailto:{{ $org['email'] }}">{{ $org['email'] }}</a>@endif
        </div>
    </div>
@endif
@php
    $nav = [['About', 'about'], ['History', 'history'], ['Leadership', 'leadership'], ['Membership', 'membership'], ['Events', 'events.index'], ['News', 'news.index'], ['Contact', 'contact']];
    $current = fn ($r) => request()->routeIs($r) || request()->routeIs(str_replace('.index', '', $r).'.*') || ($r === 'membership' && request()->routeIs('membership.*'));
@endphp
<header class="site-header">
    <div class="wrap">
        <a class="brand" href="{{ route('home') }}">
            <img src="{{ asset('images/crest-160.webp') }}" alt="" width="58" height="64">
            <span><strong>Ẹgbẹ́ Bobagunwa Okunrin</strong><small>Akile Ijebu</small></span>
        </a>
        <nav class="main-nav" aria-label="Main">
            @foreach ($nav as [$label, $route])
                <a href="{{ route($route) }}" @if ($current($route)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="header-actions">
            @auth
                <a class="btn small secondary" href="{{ auth()->user()->member_id ? route('portal.dashboard') : route('admin.dashboard') }}">My account</a>
            @else
                <a class="btn small secondary" href="{{ route('login') }}">Member sign in</a>
            @endauth
        </div>
        <details class="mobile-menu">
            <summary aria-label="Menu"><span></span></summary>
            <nav aria-label="Main (mobile)">
                <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>Home</a>
                @foreach ($nav as [$label, $route])
                    <a href="{{ route($route) }}" @if ($current($route)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
                <a href="{{ route('activities') }}">Activities</a>
                <a href="{{ route('membership.apply') }}">Apply for membership</a>
                @auth
                    <a href="{{ auth()->user()->member_id ? route('portal.dashboard') : route('admin.dashboard') }}">My account</a>
                @else
                    <a href="{{ route('login') }}">Member sign in</a>
                @endauth
            </nav>
        </details>
    </div>
</header>
<main id="main" tabindex="-1">
    @yield('content')
</main>
<footer class="site-footer">
    <div class="wrap">
        <div>
            <a class="brand" href="{{ route('home') }}">
                <img src="{{ asset('images/crest-160.webp') }}" alt="" width="58" height="64">
                <span><strong>Ẹgbẹ́ Bobagunwa Okunrin</strong><small>Akile Ijebu</small></span>
            </a>
            <p class="yo">Motto: <em>{{ $org['motto'] }}</em></p>
        </div>
        <div>
            <h2>The association</h2>
            <ul>
                <li><a href="{{ route('about') }}">About</a></li>
                <li><a href="{{ route('history') }}">History and heritage</a></li>
                <li><a href="{{ route('leadership') }}">Leadership</a></li>
                <li><a href="{{ route('activities') }}">Activities</a></li>
                <li><a href="{{ route('gallery.index') }}">Gallery</a></li>
            </ul>
        </div>
        <div>
            <h2>Members</h2>
            <ul>
                <li><a href="{{ route('membership') }}">Membership</a></li>
                <li><a href="{{ route('membership.apply') }}">Apply</a></li>
                <li><a href="{{ route('membership.status') }}">Track an application</a></li>
                <li><a href="{{ route('login') }}">Member sign in</a></li>
                <li><a href="{{ route('contact') }}">Contact the secretariat</a></li>
                @if ($org['publish_contact'] && $org['phone'])<li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $org['phone']) }}">{{ $org['phone'] }}</a></li>@endif
            </ul>
        </div>
    </div>
    <div class="wrap footer-bottom">
        <span>© {{ now()->year }} {{ $org['name'] }}</span>
        <span><a href="{{ route('privacy') }}">Privacy notice</a> · <a href="{{ route('terms') }}">Terms</a></span>
    </div>
</footer>
</body>
</html>
