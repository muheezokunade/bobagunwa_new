@extends('layouts.app', ['area' => 'admin', 'title' => 'Member register'])
@section('content')
<x-page-head title="Member register" subtitle="Every member record, ordered by membership number. Records are never deleted.">
    @if (auth()->user()->hasPermission('members.export'))
        <a class="btn secondary" href="{{ route('admin.members.export', array_filter(['status' => $filters['status'] ?? null])) }}">Export register (CSV)</a>
    @endif
</x-page-head>
<form method="get" action="{{ route('admin.members.index') }}" class="filters">
    <x-field name="q" label="Search" type="search" :value="$filters['q'] ?? null" hint="Number, name, email or phone" />
    <x-select name="status" label="Status" :value="$filters['status'] ?? null" placeholder="Any" :options="['active' => 'Active', 'suspended' => 'Suspended', 'inactive' => 'Inactive']" />
    <x-select name="origin" label="Origin" :value="$filters['origin'] ?? null" placeholder="Any" :options="['application' => 'Approved application', 'import' => 'Imported', 'manual' => 'Entered manually']" />
    <x-select name="account" label="Portal account" :value="$filters['account'] ?? null" placeholder="Any" :options="['none' => 'No account', 'pending' => 'Awaiting activation', 'active' => 'Active']" />
    <x-check name="incomplete" label="Missing contact or date details" :checked="! empty($filters['incomplete'])" />
    <button class="btn secondary" type="submit">Filter</button>
</form>
@if ($members->isEmpty())
    <x-empty title="No members match these filters"><p>Change the filters, or add members through an approved application or import.</p></x-empty>
@else
<div class="table-wrap"><table>
    <thead><tr><th scope="col">Membership number</th><th scope="col">Name</th><th scope="col">Status</th><th scope="col">Origin</th><th scope="col">Portal account</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>@foreach ($members as $member)
        <tr>
            <td>{{ $member->membership_number }}</td>
            <td>{{ $member->full_name }}@if ($member->incompleteFields())<br><span class="muted small">Missing: {{ implode(', ', $member->incompleteFields()) }}</span>@endif</td>
            <td><x-status :value="$member->status" /></td>
            <td>{{ \App\Support\Format::label($member->origin) }}</td>
            <td>@if ($member->user)<x-status :value="$member->user->status" />@else<span class="muted">No account</span>@endif</td>
            <td><a class="text-link" href="{{ route('admin.members.show', $member) }}">Open <span class="sr-only">record for {{ $member->full_name }}</span></a></td>
        </tr>
    @endforeach</tbody>
</table></div>
{{ $members->links() }}
@endif
@endsection
