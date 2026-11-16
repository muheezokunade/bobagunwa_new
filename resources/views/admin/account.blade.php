@extends('layouts.app', ['area' => 'admin', 'title' => 'Password and sessions'])
@section('content')
<x-page-head title="Password and sessions" :subtitle="auth()->user()->email" />
<div class="grid-2">
    <form class="panel" method="post" action="{{ route('admin.account.password') }}">
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
        <p class="small muted">Changing your password signs out every other session.</p>
    </section>
</div>
<section class="panel">
    <h2>Two-factor sign-in</h2>
    @php $u = auth()->user(); $privileged = \App\Services\TwoFactorService::isPrivileged($u); $enforced = \App\Services\TwoFactorService::enforced(); @endphp
    @if (session('recovery_codes'))
        <div class="secret-box">
            <h3>Your recovery codes — shown once</h3>
            <p>Store these somewhere safe and separate from your phone. Each can be used once if you lose your phone.</p>
            <code id="recovery-codes">{{ implode('  ', session('recovery_codes')) }}</code>
        </div>
    @endif
    @if ($u->two_factor_confirmed_at)
        <p><x-status value="active" /> On since {{ \App\Support\Format::dateTime($u->two_factor_confirmed_at) }}. {{ count($u->two_factor_recovery_codes ?? []) }} recovery code(s) left.</p>
        <div class="grid-2">
            <form method="post" action="{{ route('admin.account.two-factor.recovery') }}">@csrf<x-field name="code" label="Current code" prefix="rc-" autocomplete="one-time-code" inputmode="numeric" required /><button class="btn secondary small" type="submit">Create new recovery codes</button></form>
            @if (! ($enforced && $privileged))
                <form method="post" action="{{ route('admin.account.two-factor.disable') }}" data-confirm="Switch off two-factor sign-in?">@csrf<x-field name="password" label="Password" type="password" prefix="d-" autocomplete="current-password" required /><x-field name="code" label="Current code" prefix="d-" autocomplete="one-time-code" inputmode="numeric" required /><button class="btn secondary small" type="submit">Switch off</button></form>
            @endif
        </div>
    @elseif (session('two_factor_setup'))
        @php $setup = session('two_factor_setup'); @endphp
        <ol>
            <li>Install a free authenticator app (for example Google Authenticator, Microsoft Authenticator or Aegis).</li>
            <li>On your phone, <a href="{{ $setup['uri'] }}">open this set-up link</a>, or add an account manually with this key: <code id="totp-secret">{{ trim(chunk_split($setup['secret'], 4, ' ')) }}</code> (time-based, 6 digits).</li>
            <li>Enter the 6-digit code the app shows.</li>
        </ol>
        <form method="post" action="{{ route('admin.account.two-factor.confirm') }}">@csrf<x-field name="code" label="Code from the app" autocomplete="one-time-code" inputmode="numeric" required /><button class="btn" type="submit">Confirm and switch on</button></form>
    @else
        <p>{{ $privileged ? 'Recommended for your role.' : 'Optional.' }} @if ($enforced && $privileged)<strong>Required for your role.</strong>@endif After your password you will also enter a code from an authenticator app on your phone.</p>
        <form method="post" action="{{ route('admin.account.two-factor.start') }}">@csrf<button class="btn" type="submit">Set up two-factor sign-in</button></form>
    @endif
</section>
@endsection
