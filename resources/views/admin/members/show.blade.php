@extends('layouts.app', ['area' => 'admin', 'title' => $member->full_name])
@section('content')
@php
    $F = \App\Support\Format::class;
    $u = auth()->user();
    $account = $member->user;
    $issuesActivation = ! $account || $account->status === 'pending_activation';
@endphp
<x-page-head :title="$member->full_name" :subtitle="'Membership number '.$member->membership_number">
    <a class="btn secondary" href="{{ route('admin.members.index') }}">Member register</a>
</x-page-head>

@if ($member->incompleteFields())
    <div class="alert warning" role="status">This record is missing: {{ implode(', ', $member->incompleteFields()) }}. Complete it from verified information only.</div>
@endif
@if ($duplicates->isNotEmpty())
    <div class="alert warning" role="status">
        <strong>Other records have the same name:</strong>
        <ul>@foreach ($duplicates as $dup)<li><a href="{{ route('admin.members.show', $dup->id) }}">{{ $dup->membership_number }} — {{ $dup->full_name }}</a></li>@endforeach</ul>
        <p class="small">Check whether these are the same person before issuing access or recording payments.</p>
    </div>
@endif

<dl class="stats">
    <div><dt>Status</dt><dd><x-status :value="$member->status" /></dd></div>
    <div><dt>Portal account</dt><dd>@if ($account)<x-status :value="$account->status" />@else No account @endif</dd></div>
    <div><dt>Member since</dt><dd>{{ $F::date($member->joined_on) }}</dd></div>
</dl>

<div class="grid-2">
    <section class="panel">
        <h2>Record</h2>
        <dl class="details">
            <dt>Membership number</dt><dd>{{ $member->membership_number }}</dd>
            <dt>Full name</dt><dd>{{ $member->full_name }}</dd>
            <dt>Preferred name</dt><dd>{{ $member->preferred_name ?: '—' }}</dd>
            <dt>Email</dt><dd>{{ $member->email ?: '—' }}</dd>
            <dt>Phone</dt><dd>{{ $member->phone ?: '—' }}</dd>
            <dt>Date of birth</dt><dd>{{ $F::date($member->date_of_birth) }}</dd>
            <dt>Ijebu town</dt><dd>{{ $member->ijebu_town ?: '—' }}</dd>
            <dt>City of residence</dt><dd>{{ $member->city ?: '—' }}</dd>
            <dt>Occupation</dt><dd>{{ $member->occupation ?: '—' }}</dd>
            <dt>Address</dt><dd>{{ $member->address ?: '—' }}</dd>
            <dt>Joined</dt><dd>{{ $F::date($member->joined_on) }}</dd>
            <dt>Status</dt><dd><x-status :value="$member->status" />@if ($member->status_reason)<br><span class="muted small">{{ $member->status_reason }}</span>@endif</dd>
            <dt>Officer notes</dt><dd>{!! $member->admin_notes ? nl2br(e($member->admin_notes)) : '—' !!}</dd>
            <dt>Recorded</dt><dd>{{ $F::dateTime($member->created_at) }}</dd>
        </dl>
    </section>
    <div>
        <section class="panel">
            <h2>Origin</h2>
            <dl class="details">
                <dt>Source</dt><dd>{{ $F::label($member->origin) }}</dd>
                @if ($member->application)
                    <dt>Application</dt><dd>
                        @if ($u->hasPermission('applications.review'))
                            <a href="{{ route('admin.applications.show', $member->application) }}">{{ $member->application->reference }}</a>
                        @else
                            {{ $member->application->reference }}
                        @endif
                    </dd>
                @endif
                @if ($member->importBatch)
                    <dt>Import batch</dt><dd>
                        @if ($u->hasPermission('members.import'))
                            <a href="{{ route('admin.imports.members.show', $member->importBatch) }}">{{ $member->importBatch->reference }}</a>
                        @else
                            {{ $member->importBatch->reference }}
                        @endif
                    </dd>
                @endif
            </dl>
        </section>
        <section class="panel">
            <h2>Portal account</h2>
            @if ($account)
                <dl class="details">
                    <dt>Status</dt><dd><x-status :value="$account->status" /></dd>
                    <dt>Sign-in email</dt><dd>{{ $account->email }}</dd>
                    <dt>Roles</dt><dd>{{ $account->roles->pluck('name')->join(', ') ?: '—' }}</dd>
                    <dt>Activated</dt><dd>{{ $F::dateTime($account->activated_at) }}</dd>
                    <dt>Last signed in</dt><dd>{{ $F::dateTime($account->last_login_at) }}</dd>
                </dl>
            @else
                <p class="muted">This member has no portal account yet. Issuing an activation link creates one.</p>
            @endif
            @if ($u->hasPermission('accounts.issue'))
                <form method="post" action="{{ route('admin.members.access', $member) }}" data-confirm="{{ $issuesActivation ? 'Issue an activation link' : 'Issue a password reset link' }} for {{ $member->full_name }}? Any earlier unused link stops working.">
                    @csrf
                    <x-check name="confirm_identity" label="I have verified this member’s identity and contact details." />
                    <button class="btn" type="submit">{{ $issuesActivation ? 'Issue activation link' : 'Issue password reset link' }}</button>
                </form>
                <p class="small muted">The link is shown once. Send it through the approved private channel, then record the delivery.</p>
            @endif
        </section>
        @if ($u->hasPermission('finance.read'))
            <section class="panel">
                <h2>Finance summary</h2>
                @if ($summary)
                    <dl class="details">
                        <dt>Outstanding dues and levies</dt><dd><x-money :kobo="$summary['outstanding']" /></dd>
                        <dt>Verified payments</dt><dd><x-money :kobo="$summary['verified_payments']" /></dd>
                        <dt>Unallocated credit</dt><dd><x-money :kobo="$summary['unallocated_credit']" /></dd>
                        <dt>Awaiting verification</dt><dd><x-money :kobo="$summary['pending']" /></dd>
                    </dl>
                @else
                    <p class="muted">No financial summary is available.</p>
                @endif
                <a class="text-link" href="{{ route('admin.finance.members.show', $member) }}">Open financial record</a>
            </section>
        @endif
    </div>
</div>

@if ($u->hasPermission('members.manage'))
<div class="grid-2">
    <form class="panel" method="post" action="{{ route('admin.members.update', $member) }}">
        @csrf
        <h2>Correct the record</h2>
        <p class="small muted">Use verified information only. Every change is recorded in the history with your reason. The membership number cannot be changed.</p>
        <input type="hidden" name="version" value="{{ $member->version }}">
        <x-field name="full_name" label="Full name" :value="$member->full_name" required />
        <x-field name="preferred_name" label="Preferred name" :value="$member->preferred_name" />
        <x-field name="email" label="Email" type="email" :value="$member->email" />
        <x-field name="phone" label="Phone" type="tel" :value="$member->phone" />
        <x-field name="date_of_birth" label="Date of birth" type="date" :value="$member->date_of_birth?->format('Y-m-d')" />
        <x-field name="ijebu_town" label="Ijebu town" :value="$member->ijebu_town" />
        <x-field name="city" label="City of residence" :value="$member->city" />
        <x-field name="occupation" label="Occupation" :value="$member->occupation" />
        <x-field name="address" label="Address" :value="$member->address" />
        <x-field name="joined_on" label="Joined on" type="date" :value="$member->joined_on?->format('Y-m-d')" />
        <x-textarea name="admin_notes" label="Officer notes" :value="$member->admin_notes" rows="4" hint="Visible to officers only." />
        <x-field prefix="u-" name="reason" label="Reason for the correction" required hint="For example: “Email confirmed by the member in person on 16 Nov”." />
        <button class="btn" type="submit">Save correction</button>
    </form>
    <form class="panel" method="post" action="{{ route('admin.members.status', $member) }}" data-confirm="Change the membership status of {{ $member->full_name }}? A suspended or inactive member cannot sign in to the portal.">
        @csrf
        <h2>Membership status</h2>
        <p>Current status: <x-status :value="$member->status" /></p>
        <x-select prefix="s-" name="status" label="New status" :value="$member->status" required :options="['active' => 'Active', 'suspended' => 'Suspended', 'inactive' => 'Inactive']" />
        <x-field prefix="s-" name="reason" label="Reason" required />
        <button class="btn secondary" type="submit">Change status</button>
    </form>
</div>
@endif

<section class="panel">
    <h2>Change history</h2>
    @if ($history->isEmpty())
        <p class="muted">No changes recorded.</p>
    @else
    <div class="table-wrap"><table>
        <thead><tr><th scope="col">Time</th><th scope="col">Officer</th><th scope="col">Action</th><th scope="col">Details</th></tr></thead>
        <tbody>@foreach ($history as $entry)
            @php $ctx = is_array($entry->context) ? $entry->context : []; @endphp
            <tr>
                <td>{{ $F::dateTime($entry->created_at) }}</td>
                <td>{{ $entry->actor?->name ?? 'System' }}</td>
                <td>{{ $F::label($entry->action) }}</td>
                <td>
                    {{ $entry->summary }}
                    @if (! empty($ctx['changes']) && is_array($ctx['changes']))
                        <ul class="small">@foreach ($ctx['changes'] as $field => $change)
                            <li>{{ $F::label((string) $field) }}: {{ is_array($change) ? (($change['from'] ?? null) ?? '—').' → '.(($change['to'] ?? null) ?? '—') : '' }}</li>
                        @endforeach</ul>
                    @elseif (isset($ctx['from'], $ctx['to']) && is_string($ctx['from']))
                        <br><span class="small">{{ $F::label($ctx['from']) }} → {{ $F::label((string) $ctx['to']) }}</span>
                    @endif
                    @if (! empty($ctx['reason']) && is_string($ctx['reason']))<br><span class="muted small">Reason: {{ $ctx['reason'] }}</span>@endif
                </td>
            </tr>
        @endforeach</tbody>
    </table></div>
    @endif
</section>
@endsection
