@extends('layouts.app', ['area' => 'admin', 'title' => $user->name])
@section('content')
@php
    $F = \App\Support\Format::class;
    $self = $user->id === auth()->id();
    $staffRoles = $roles->where('is_staff', true);
    $current = $user->roles->pluck('key')->all();
    $oldRoles = old('roles');
    $activation = $user->status === 'pending_activation';
@endphp
<x-page-head :title="$user->name" subtitle="Officer account">
    <a class="btn secondary" href="{{ route('admin.access.index') }}">All officers</a>
</x-page-head>
@if ($self)
    <div class="alert warning" role="status">This is your own account. You cannot change your own roles or status; ask another administrator.</div>
@endif
<div class="grid-2">
    <section class="panel">
        <h2>Account</h2>
        <dl class="details">
            <dt>Status</dt><dd><x-status :value="$user->status" /></dd>
            <dt>Email</dt><dd>{{ $user->email }}</dd>
            <dt>Linked member</dt><dd>
                @if ($user->member)
                    @if (auth()->user()->hasPermission('members.read'))
                        <a href="{{ route('admin.members.show', $user->member) }}">{{ $user->member->membership_number }} — {{ $user->member->full_name }}</a>
                    @else
                        {{ $user->member->membership_number }} — {{ $user->member->full_name }}
                    @endif
                    <x-status :value="$user->member->status" />
                @else
                    —
                @endif
            </dd>
            <dt>Roles</dt><dd>{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</dd>
            <dt>Activated</dt><dd>{{ $F::dateTime($user->activated_at) }}</dd>
            <dt>Last signed in</dt><dd>{{ $F::dateTime($user->last_login_at) }}</dd>
            <dt>Password last changed</dt><dd>{{ $F::dateTime($user->password_changed_at) }}</dd>
            <dt>Created</dt><dd>{{ $F::dateTime($user->created_at) }}</dd>
        </dl>
    </section>
    <form class="panel" method="post" action="{{ route('admin.access.link', $user) }}" data-confirm="{{ $activation ? 'Issue an activation link' : 'Issue a password reset link' }} for {{ $user->name }}? Any earlier unused link stops working.">
        @csrf
        <h2>{{ $activation ? 'Activation link' : 'Password reset link' }}</h2>
        <p>{{ $activation ? 'This account has not been activated. Issue a new one-time activation link if the earlier one expired or was lost.' : 'Issue a one-time password reset link if the officer cannot sign in.' }}</p>
        <x-check prefix="l-" name="confirm_identity" label="I have verified this person’s identity." />
        <button class="btn" type="submit">{{ $activation ? 'Issue activation link' : 'Issue password reset link' }}</button>
        <p class="small muted">The link is shown once. Send it through the approved private channel, then record the delivery.</p>
    </form>
</div>

@unless ($self)
<div class="grid-2">
    <form class="panel" method="post" action="{{ route('admin.access.roles', $user) }}" data-confirm="Change the roles of {{ $user->name }}? They will be signed out so the change takes effect.">
        @csrf
        <h2>Roles</h2>
        <fieldset>
            <legend>Officer roles</legend>
            @foreach ($staffRoles as $role)
                <x-check prefix="ro-" name="roles[]" :value="$role->key" :label="$role->name.' — '.$role->description" :checked="in_array($role->key, is_array($oldRoles) ? $oldRoles : $current, true)" />
            @endforeach
            @error('roles')<span class="error-text">{{ $message }}</span>@enderror
        </fieldset>
        @if ($user->member_id)<p class="small muted">The Member role is kept automatically because this account is linked to a member record.</p>@endif
        <p class="small muted">Removing every officer role removes access to administration. The last active account able to manage access cannot be removed.</p>
        <x-field prefix="ro-" name="reason" label="Reason and approval reference" required />
        <button class="btn" type="submit">Save roles</button>
    </form>
    <form class="panel" method="post" action="{{ route('admin.access.status', $user) }}" data-confirm="Change the account status of {{ $user->name }}? Suspending or disabling signs them out immediately.">
        @csrf
        <h2>Account status</h2>
        <p>Current status: <x-status :value="$user->status" /></p>
        <x-select prefix="st-" name="status" label="New status" required :value="$activation ? null : $user->status" placeholder="Choose…" :options="['active' => 'Active', 'suspended' => 'Suspended (temporary)', 'disabled' => 'Disabled']" />
        <x-field prefix="st-" name="reason" label="Reason" required />
        @if ($activation)<p class="small muted">An account awaiting activation becomes active only when the person uses their activation link.</p>@endif
        <button class="btn secondary" type="submit">Change status</button>
    </form>
</div>
@endunless

<section class="panel">
    <h2>Recent security events</h2>
    @if ($events->isEmpty())
        <p class="muted">No security events recorded.</p>
    @else
    <div class="table-wrap" tabindex="0" role="region" aria-label="Table (scrolls sideways on small screens)"><table>
        <thead><tr><th scope="col">Time</th><th scope="col">Event</th><th scope="col">Details</th></tr></thead>
        <tbody>@foreach ($events as $event)
            <tr>
                <td>{{ $F::dateTime($event->created_at) }}</td>
                <td>{{ $F::label($event->event) }}</td>
                <td>@if ($event->context)<code class="small">{{ \Illuminate\Support\Str::limit(json_encode($event->context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200) }}</code>@else<span class="muted">—</span>@endif</td>
            </tr>
        @endforeach</tbody>
    </table></div>
    @endif
</section>
@endsection
