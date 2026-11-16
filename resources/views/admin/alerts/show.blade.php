@extends('layouts.app', ['area' => 'admin', 'title' => 'Alert #'.$alert->id])
@section('content')
<x-page-head :title="'Alert #'.$alert->id.': '.$alert->title" :subtitle="ucfirst($alert->severity).' · '.ucfirst($alert->status)" />
<div class="grid-2">
    <section class="panel">
        <h2>What was detected</h2>
        <p>{{ $alert->summary }}</p>
        <dl class="details">
            <dt>First detected</dt><dd>{{ \App\Support\Format::dateTime($alert->first_detected_at) }}</dd>
            <dt>Last detected</dt><dd>{{ \App\Support\Format::dateTime($alert->last_detected_at) }} ({{ $alert->occurrences }} occurrence(s))</dd>
            <dt>Condition</dt><dd>{{ $alert->condition_cleared_at ? 'Cleared by a passing check at '.\App\Support\Format::dateTime($alert->condition_cleared_at) : 'Still present' }}</dd>
            @if ($alert->acknowledger)<dt>Acknowledged</dt><dd>{{ $alert->acknowledger->name }} · {{ \App\Support\Format::dateTime($alert->acknowledged_at) }}<br>{{ $alert->acknowledgement_note }}</dd>@endif
            @if ($alert->resolver)<dt>Resolved</dt><dd>{{ $alert->resolver->name }} · {{ \App\Support\Format::dateTime($alert->resolved_at) }}<br>{{ $alert->resolution_note }}</dd>@endif
        </dl>
        <h3>Escalation</h3>
        <ol>
            <li>Do not edit, delete or “balance” financial records.</li>
            <li>Acknowledge below, saying who is investigating.</li>
            <li>Inform the Treasurer and the Executive the same day; escalate to the technical maintainer.</li>
            <li>The maintainer finds the cause (see docs/05-database-integrity-safeguards.md); corrections are made only through adjustments or reversals.</li>
            <li>Run the check again. Once it passes, resolve the alert with a note of the cause and fix.</li>
        </ol>
    </section>
    <section class="panel">
        @if ($alert->status === 'open')
            <form method="post" action="{{ route('admin.alerts.acknowledge', $alert) }}">
                @csrf
                <h2>Acknowledge</h2>
                <x-textarea name="note" label="Who is investigating and what happens next" required rows="3" prefix="ack-" />
                <button class="btn" type="submit">Acknowledge</button>
            </form>
        @endif
        @if ($alert->isUnresolved())
            <form method="post" action="{{ route('admin.alerts.resolve', $alert) }}" class="section-gap">
                @csrf
                <h2>Resolve</h2>
                @unless ($alert->condition_cleared_at)<p class="small muted">Available only after a later integrity check passes.</p>@endunless
                <x-textarea name="note" label="Cause found and correction made" required rows="3" prefix="res-" />
                <button class="btn secondary" type="submit" @disabled(! $alert->condition_cleared_at)>Resolve alert</button>
            </form>
        @endif
    </section>
</div>
<div class="grid-2">
    <section class="panel">
        <h2>Seen in the administration area</h2>
        @forelse ($alert->receipts as $r)<p>{{ $r->user->name }} · first seen {{ \App\Support\Format::dateTime($r->first_seen_at) }}</p>@empty<p class="muted">No officer has opened the administration area since this alert was raised.</p>@endforelse
    </section>
    <section class="panel">
        <h2>Email delivery</h2>
        @forelse ($alert->notifications as $n)
            <p>{{ $n->user?->name ?? '—' }} · {{ ['accepted_by_mail_server' => 'accepted by the mail server', 'failed' => 'failed', 'not_configured' => 'not sent: email channel not configured or not verified', 'displayed' => 'displayed'][$n->status] }} · {{ \App\Support\Format::dateTime($n->attempted_at) }}@if ($n->error)<br><span class="small muted">{{ $n->error }}</span>@endif</p>
        @empty<p class="muted">No delivery attempts.</p>@endforelse
        <p class="small muted">“Accepted by the mail server” does not prove the email reached the inbox.</p>
    </section>
</div>
@endsection
