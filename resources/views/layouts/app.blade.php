{{-- Shared shell for the member portal and the administration area. --}}
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Account' }} · {{ $area === 'admin' ? 'Administration' : 'Member portal' }} · Bobagunwa</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body">
<a class="skip-link" href="#main">Skip to main content</a>
<header class="app-header">
    <div class="wrap">
        <a class="brand" href="{{ route('home') }}">
            <img src="{{ asset('images/crest-160.webp') }}" alt="" width="38" height="42">
            <span><strong>Bobagunwa</strong><small>{{ $area === 'admin' ? 'Administration' : 'Member portal' }}</small></span>
        </a>
        <button type="button" class="btn small light app-nav-toggle" data-nav-toggle aria-controls="app-nav" aria-expanded="false">Menu</button>
        <div class="who">
            <span>{{ auth()->user()->name }}</span>
            @if ($area === 'admin' && auth()->user()->member_id)
                · <a class="link-button" href="{{ route('portal.dashboard') }}">My membership</a>
            @elseif ($area === 'portal' && auth()->user()->isStaff())
                · <a class="link-button" href="{{ route('admin.dashboard') }}">Administration</a>
            @endif
            · <form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="link-button">Sign out</button></form>
        </div>
    </div>
</header>
<div class="app-layout">
    <nav class="app-nav" id="app-nav" aria-label="{{ $area === 'admin' ? 'Administration' : 'Member portal' }}">
        @include($area === 'admin' ? 'partials.admin-nav' : 'partials.portal-nav')
    </nav>
    <main class="app-main" id="main" tabindex="-1">
        @include('partials.flash')
        @yield('content')
    </main>
</div>
</body>
</html>
