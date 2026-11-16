@extends('layouts.public', ['title' => 'Activate your account', 'noindex' => true])
@section('content')
<x-public-hero title="Activate your" em="account" crumb="Activate account" />
<section class="section">
    <div class="narrow">
        @include('partials.flash')
        @if (! $valid)
            <x-empty title="This link cannot be used">
                <p>The activation link is invalid, has expired, or has already been used. Please <a href="{{ route('contact') }}">contact the secretariat</a> for a new link.</p>
            </x-empty>
        @else
            <p>Welcome, {{ $user->name }}. Choose a password to activate your account.</p>
            <form method="post" action="{{ route('account.activate.store') }}" class="panel">
                @csrf
                <input type="text" name="username" value="{{ $user->email }}" autocomplete="username" hidden>
                <x-field name="password" label="New password" type="password" required autocomplete="new-password" hint="At least {{ config('boba.accounts.password_min_length') }} characters. A short sentence you will remember works well." />
                <x-field name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
                <button type="submit" class="btn">Activate account</button>
            </form>
        @endif
    </div>
</section>
@endsection
