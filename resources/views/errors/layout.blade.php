<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · Bobagunwa</title>
    @vite(['resources/css/app.css'])
</head>
<body>
<main class="section" id="main">
    <div class="narrow">
        <a class="brand" href="{{ url('/') }}"><img src="{{ asset('images/crest-160.webp') }}" alt="" width="58" height="64"><span><strong>Ẹgbẹ́ Bobagunwa Okunrin</strong><small>Akile Ijebu</small></span></a>
        <h1>@yield('title')</h1>
        <p class="lead">@yield('message')</p>
        <p><a class="btn" href="{{ url('/') }}">Return to the home page</a></p>
    </div>
</main>
</body>
</html>
