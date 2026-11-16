@extends('layouts.public', ['title' => 'Two-factor sign-in', 'noindex' => true])
@section('content')
<x-public-hero title="Two-factor" em="sign-in" crumb="Two-factor sign-in" />
<section class="section">
    <div class="narrow">
        @include('partials.flash')
        <form method="post" action="{{ route('login.two-factor.verify') }}" class="panel">
            @csrf
            <p>Enter the 6-digit code from your authenticator app. If you cannot use your phone, enter one of your recovery codes.</p>
            <x-field name="code" label="Code" required autocomplete="one-time-code" inputmode="numeric" autofocus />
            <button type="submit" class="btn">Continue</button>
            <p class="small"><a href="{{ route('login') }}">Start again</a> · Lost your phone and recovery codes? Ask another administrator to reset your two-factor sign-in.</p>
        </form>
    </div>
</section>
@endsection
