@extends('layouts.app', ['area' => 'admin', 'title' => 'Integrity alerts'])
@section('content')
<x-page-head title="Integrity alerts" subtitle="Automatic checks of the financial safeguards and ledger reconciliation. Alerts show check names and counts only.">
    <form method="post" action="{{ route('admin.alerts.check') }}" class="inline-form">@csrf<button class="btn secondary" type="submit">Run the check now</button></form>
</x-page-head>

<h2>Alerts</h2>
@if ($alerts->isEmpty())
    <x-empty title="No alerts"><p>No integrity check has failed.</p></x-empty>
@else
<div class="table-wrap" tabindex="0" role="region" aria-label="Alerts table">
<table>
    <thead><tr><th scope="col">Alert</th><th scope="col">Severity</th><th scope="col">Status</th><th scope="col">First detected</th><th scope="col">Last detected</th><th scope="col" class="num">Occurrences</th></tr></thead>
    <tbody>@foreach ($alerts as $a)
        <tr><td><a href="{{ route('admin.alerts.show', $a) }}">#{{ $a->id }} {{ $a->title }}</a>@if ($a->isUnresolved() && $a->condition_cleared_at)<br><span class="small muted">Condition cleared — ready to resolve</span>@endif</td>
            <td><span class="badge {{ $a->severity === 'critical' ? 'danger' : 'warn' }}">{{ ucfirst($a->severity) }}</span></td><td><x-status :value="$a->status === 'resolved' ? 'resolved' : ($a->status === 'acknowledged' ? 'in_progress' : 'new')" /></td>
            <td>{{ \App\Support\Format::dateTime($a->first_detected_at) }}</td><td>{{ \App\Support\Format::dateTime($a->last_detected_at) }}</td><td class="num">{{ $a->occurrences }}</td></tr>
    @endforeach</tbody>
</table></div>
{{ $alerts->links() }}
@endif

<h2>Recent check runs</h2>
<div class="table-wrap" tabindex="0" role="region" aria-label="Check history">
<table>
    <thead><tr><th scope="col">Started</th><th scope="col">How</th><th scope="col">Result</th><th scope="col" class="num">Triggers</th><th scope="col">Findings</th></tr></thead>
    <tbody>@forelse ($runs as $r)
        <tr><td>{{ \App\Support\Format::dateTime($r->started_at) }}</td><td>{{ ucfirst($r->trigger) }}@if ($r->requester) · {{ $r->requester->name }}@endif</td>
            <td><x-status :value="$r->status === 'passed' ? 'succeeded' : 'failed'" /> {{ ucfirst($r->status) }}</td><td class="num">{{ $r->triggers_installed }}</td>
            <td>{{ collect($r->exception_counts ?? [])->map(fn ($n, $l) => "{$l}: {$n}")->merge(count($r->missing_triggers ?? []) ? [count($r->missing_triggers).' missing trigger(s)'] : [])->implode('; ') ?: ($r->error ? 'Could not complete' : 'None') }}</td></tr>
    @empty
        <tr><td colspan="5" class="muted">The check has not run yet. It runs daily at 01:30 through the server cron job, or use “Run the check now”.</td></tr>
    @endforelse</tbody>
</table></div>

<h2>How officers are told</h2>
<div class="panel">
    <p><strong>In the administration area:</strong> every unresolved alert is shown at the top of every page to the {{ $recipients->count() }} active officer(s) with the alerts permission ({{ $recipients->pluck('name')->implode(', ') ?: 'none' }}). It cannot be closed; the first time each officer sees it is recorded.</p>
    <p><strong>By email:</strong>
        @if ($emailReady)
            enabled and verified — emails contain no financial details and only show that the mail server accepted them.
        @elseif (in_array($mailer, ['log', 'array'], true))
            not available: no outgoing mail server is configured (MAIL_MAILER is “{{ $mailer }}”). Alerts are recorded as “not configured” for email.
        @else
            a mail server is configured but alert email is {{ $emailEnabled ? 'enabled but not yet verified' : 'switched off' }}. {{ $emailVerified ? 'Verified '.\App\Support\Format::dateTime(\Carbon\Carbon::parse($emailVerified['verified_at'])).'.' : '' }}
        @endif
    </p>
    @if (auth()->user()->hasPermission('settings.manage') && ! in_array($mailer, ['log', 'array'], true))
        <div class="grid-2">
            <form method="post" action="{{ route('admin.alerts.email.test') }}">@csrf<p>Send a verification code to {{ auth()->user()->email }} to prove the mail channel works.</p><button class="btn secondary small" type="submit">Send test email</button></form>
            <form method="post" action="{{ route('admin.alerts.email.verify') }}">@csrf<x-field name="code" label="Code from the test email" inputmode="numeric" autocomplete="one-time-code" /><button class="btn small" type="submit">Confirm channel</button></form>
        </div>
        <p class="small muted">After verification, switch on “Also email integrity alerts” in Settings.</p>
    @endif
</div>
@endsection
