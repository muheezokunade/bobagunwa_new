@extends('layouts.app', ['area' => 'portal', 'title' => 'Password and sessions'])
@section('content')
<x-page-head title="Password and sessions" />
<div class="grid-2">
    <form class="panel" method="post" action="{{ route('portal.security.password') }}">
        @csrf
        <h2>Change password</h2>
        <input type="text" name="username" value="{{ auth()->user()->email }}" autocomplete="username" hidden>
        <x-field name="current_password" label="Current password" type="password" required autocomplete="current-password" />
        <x-field name="password" label="New password" type="password" required autocomplete="new-password" hint="At least {{ config('boba.accounts.password_min_length') }} characters." />
        <x-field name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
        <button class="btn" type="submit">Change password</button>
    </form>
    <section class="panel">
        <h2>Signed-in sessions</h2>
        <ul>
            @foreach ($sessions as $session)
                <li>{{ \Illuminate\Support\Str::limit($session->user_agent ?: 'Unknown browser', 60) }} — last active {{ \App\Support\Format::dateTime(\Carbon\Carbon::createFromTimestamp($session->last_activity)) }} @if ($session->id === $current)<span class="badge ok">This device</span>@endif</li>
            @endforeach
        </ul>
        @if ($sessions->count() > 1)
            <form method="post" action="{{ route('portal.security.sessions.end') }}" data-confirm="Sign out of all other devices?">@csrf<button class="btn secondary" type="submit">Sign out of other devices</button></form>
        @endif
    </section>
</div>
@endsection
