@extends('layouts.public', ['title' => 'Sign in', 'noindex' => true])
@section('content')
<x-public-hero title="Member" em="sign in" crumb="Sign in" />
<section class="section">
    <div class="narrow">
        <div class="split">
            <form method="post" action="{{ route('login.attempt') }}" class="panel">
                @csrf
                @include('partials.flash')
                <x-field name="email" label="Email address" type="email" required autocomplete="username" />
                <x-field name="password" label="Password" type="password" required autocomplete="current-password" />
                <button type="submit" class="btn">Sign in</button>
                <p class="small"><a href="{{ route('password.request') }}">Forgotten your password?</a></p>
            </form>
            <div>
                <h2>New to the portal?</h2>
                <p>Accounts are created for approved members. Once your membership is confirmed, the secretariat sends you a private link to set your password.</p>
                <p><a class="text-link" href="{{ route('membership') }}">About membership</a></p>
            </div>
        </div>
    </div>
</section>
@endsection
