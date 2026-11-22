@extends('layouts.app', ['area' => 'admin', 'title' => 'Administrator recovery'])
@section('content')
<x-page-head title="Administrator recovery" subtitle="If the System Administrator loses access (forgotten password, lost phone), you can restore it here. You cannot change roles, settings or records." />
@include('partials.flash')
@if ($administrators->count() > 1)
    <div class="alert warning" role="status">There are {{ $administrators->count() }} accounts with System Administrator rights. The association agreed to keep one top-level administrator so it is always clear who made a change.</div>
@endif
@forelse ($administrators as $admin)
    <section class="panel">
        <h2>{{ $admin->name }}</h2>
        <dl class="details">
            <dt>Email</dt><dd>{{ $admin->email }}</dd>
            <dt>Status</dt><dd><x-status :value="$admin->status" /></dd>
            <dt>Two-factor sign-in</dt><dd>{{ $admin->two_factor_confirmed_at ? 'Set up' : 'Not set up' }}</dd>
            <dt>Last signed in</dt><dd>{{ \App\Support\Format::dateTime($admin->last_login_at) }}</dd>
        </dl>
        @if ($admin->id === auth()->id())
            <p class="muted small">This is your own account; use “Password and sessions” instead.</p>
        @else
            <div class="split">
                <form method="post" action="{{ route('admin.administrator-recovery.link', $admin) }}" data-confirm="Issue a one-time password link for {{ $admin->name }}?">
                    @csrf
                    <h3>Forgotten password</h3>
                    <x-field name="reason" prefix="link-{{ $admin->id }}-" label="Reason" required hint="For example: “Administrator forgot password, confirmed by phone”." />
                    <x-check name="confirm_identity" prefix="link-{{ $admin->id }}-" label="I have confirmed this administrator’s identity in person or by phone." />
                    <button type="submit" class="btn secondary">Issue a one-time password link</button>
                </form>
                <form method="post" action="{{ route('admin.administrator-recovery.two-factor', $admin) }}" data-confirm="Reset two-factor sign-in for {{ $admin->name }}? They will be signed out everywhere.">
                    @csrf
                    <h3>Lost phone (two-factor)</h3>
                    <x-field name="reason" prefix="2fa-{{ $admin->id }}-" label="Reason" required hint="For example: “Phone lost, confirmed in person”." />
                    <x-check name="confirm_identity" prefix="2fa-{{ $admin->id }}-" label="I have confirmed this administrator’s identity in person or by phone." />
                    <button type="submit" class="btn secondary">Reset two-factor sign-in</button>
                </form>
            </div>
        @endif
    </section>
@empty
    <x-empty title="No System Administrator account yet"><p>The first administrator is created on the server with <code>boba:admin:create</code>.</p></x-empty>
@endforelse
<p class="small muted">Every action here is recorded in the audit history and the administrator’s security log. Send a link only through the association’s approved private channel.</p>
@endsection
