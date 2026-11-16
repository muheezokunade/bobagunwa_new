@extends('layouts.public', ['title' => 'Choose a new password', 'noindex' => true])
@section('content')
<x-public-hero title="Choose a new" em="password" crumb="Reset password" />
<section class="section">
    <div class="narrow">
        @include('partials.flash')
        @if (! $valid)
            <x-empty title="This link cannot be used">
                <p>The reset link is invalid, has expired, or has already been used. <a href="{{ route('password.request') }}">Request help again</a>.</p>
            </x-empty>
        @else
            <form method="post" action="{{ route('password.update') }}" class="panel">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <x-field name="password" label="New password" type="password" required autocomplete="new-password" hint="At least {{ config('boba.accounts.password_min_length') }} characters." />
                <x-field name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
                <button type="submit" class="btn">Save new password</button>
            </form>
        @endif
    </div>
</section>
@endsection
