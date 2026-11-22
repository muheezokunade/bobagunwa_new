@extends('layouts.app', ['area' => 'admin', 'title' => 'Officers and access'])
@section('content')
@php
    $F = \App\Support\Format::class;
    $staffRoles = $roles->where('is_staff', true);
    $oldRoles = (array) old('roles', []);
@endphp
<x-page-head title="Officers and access" subtitle="Officer accounts, role assignments and account recovery requests. Permissions come only from assigned roles.">
    @if ($recovery->isNotEmpty())<a class="btn secondary" href="#recovery">Recovery requests ({{ $recovery->count() }})</a>@endif
</x-page-head>

<h2>Officers</h2>
@if ($officers->isEmpty())
    <x-empty title="No officer accounts"><p>Create an officer account below.</p></x-empty>
@else
<div class="table-wrap" tabindex="0" role="region" aria-label="Table (scrolls sideways on small screens)"><table>
    <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Roles</th><th scope="col">Linked member</th><th scope="col">Status</th><th scope="col">Last signed in</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>@foreach ($officers as $officer)
        <tr>
            <td>{{ $officer->name }}@if ($officer->id === auth()->id()) <span class="badge info">You</span>@endif</td>
            <td>{{ $officer->email }}</td>
            <td>{{ $officer->roles->where('is_staff', true)->pluck('name')->join(', ') }}</td>
            <td>{{ $officer->member?->membership_number ?? '—' }}</td>
            <td><x-status :value="$officer->status" /></td>
            <td>{{ $F::dateTime($officer->last_login_at) }}</td>
            <td><a class="text-link" href="{{ route('admin.access.show', $officer) }}">Manage <span class="sr-only">{{ $officer->name }}</span></a></td>
        </tr>
    @endforeach</tbody>
</table></div>
@endif

<div class="grid-2">
    <form class="panel" method="post" action="{{ route('admin.access.store') }}" data-confirm="Grant officer access with the selected roles?">
        @csrf
        <h2>Add an officer</h2>
        <p class="small muted">Assign only the roles the association has approved for this person. If they do not yet have an account, a one-time activation link is issued.</p>
        <x-field name="name" label="Full name" required autocomplete="off" />
        <x-field name="email" label="Email" type="email" required autocomplete="off" />
        <fieldset>
            <legend>Roles <span class="req" aria-hidden="true">*</span></legend>
            @foreach ($staffRoles as $role)
                <x-check name="roles[]" :value="$role->key" :label="$role->name.' — '.$role->description" :checked="in_array($role->key, $oldRoles, true)" />
            @endforeach
            @error('roles')<span class="error-text">{{ $message }}</span>@enderror
        </fieldset>
        <p class="small muted">Officer logins are separate from member portal logins. If the officer is also a member, use a different email address from the one on his member account (for example an office address).</p>
        <x-field name="reason" label="Reason and approval reference" required hint="For example: “Elected treasurer, AGM minutes 12 Oct 2026”." />
        <button class="btn" type="submit">Grant officer access</button>
    </form>

    <section class="panel">
        <h2>Role catalogue</h2>
        <p class="small muted">The System Administrator role is technical only: it has no membership-approval or treasury powers. Payment recording and payment verification are held by different roles.</p>
        @foreach ($roles as $role)
            <h3>{{ $role->name }} @unless ($role->is_staff)<span class="badge">Not an officer role</span>@endunless</h3>
            <p class="small">{{ $role->description }}</p>
            @if ($role->permissions->isEmpty())
                <p class="small muted">No administration permissions.</p>
            @else
                <ul class="small">@foreach ($role->permissions as $permission)<li><code>{{ $permission->key }}</code> — {{ $permission->description }}</li>@endforeach</ul>
            @endif
        @endforeach
    </section>
</div>

<section class="panel" id="recovery">
    <h2>Account recovery requests</h2>
    <p class="small muted">Before issuing a link, verify the person’s identity through a channel the association already holds for them. Never rely on details supplied only in the request.</p>
    @if ($recovery->isEmpty())
        <p class="muted">No pending requests.</p>
    @else
    <div class="table-wrap" tabindex="0" role="region" aria-label="Table (scrolls sideways on small screens)"><table>
        <thead><tr><th scope="col">Requested</th><th scope="col">Account</th><th scope="col">Decision</th></tr></thead>
        <tbody>@foreach ($recovery as $item)
            <tr>
                <td>{{ $F::dateTime($item->created_at) }}</td>
                <td>
                    @if ($item->user)
                        {{ $item->user->name }}<br><span class="muted small">{{ $item->user->email }}</span>
                        @if ($item->user->member)<br><span class="muted small">Member {{ $item->user->member->membership_number }}</span>@endif
                        <br><x-status :value="$item->user->status" />
                    @else
                        <span class="muted">No matching account</span>
                    @endif
                </td>
                <td>
                    <form method="post" action="{{ route('admin.access.recovery', $item) }}" data-confirm="Record this decision on the recovery request?">
                        @csrf
                        <x-select :prefix="'r'.$item->id.'-'" name="action" label="Decision" required :options="$item->user ? ['issue' => 'Issue a one-time link', 'dismiss' => 'Dismiss'] : ['dismiss' => 'Dismiss']" />
                        <x-field :prefix="'r'.$item->id.'-'" name="note" label="Note" required hint="How identity was checked, or why the request is dismissed." />
                        @if ($item->user)
                            <x-check :prefix="'r'.$item->id.'-'" name="confirm_identity" label="I have verified this person’s identity (required to issue a link)." />
                        @endif
                        <button class="btn small" type="submit">Save decision</button>
                    </form>
                </td>
            </tr>
        @endforeach</tbody>
    </table></div>
    @endif
</section>
@endsection
