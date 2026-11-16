@extends('layouts.public', ['title' => 'Account recovery', 'noindex' => true])
@section('content')
<x-public-hero title="Account" em="recovery" crumb="Account recovery" />
<section class="section">
    <div class="narrow">
        @include('partials.flash')
        <p>Enter the email address registered with the association. For your security, an officer may confirm your identity before a private reset link is sent to you.</p>
        <form method="post" action="{{ route('password.email') }}" class="panel">
            @csrf
            <x-field name="email" label="Email address" type="email" required autocomplete="email" />
            <button type="submit" class="btn">Request help</button>
        </form>
    </div>
</section>
@endsection
