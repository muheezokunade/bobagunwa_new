@extends('layouts.app', ['area' => 'admin', 'title' => 'Membership applications'])
@section('content')
<x-page-head title="Membership applications" subtitle="Applications received through the public membership form." />
@php
    $statusOptions = ['open' => 'Open (awaiting a decision)'];
    foreach (array_keys(\App\Models\MembershipApplication::TRANSITIONS) as $s) { $statusOptions[$s] = \App\Models\MembershipApplication::statusLabel($s); }
    $statusOptions['all'] = 'All applications';
@endphp
<form method="get" action="{{ route('admin.applications.index') }}" class="filters">
    <x-select name="status" label="Status" :options="$statusOptions" :value="$status" />
    <x-select name="eligibility" label="Eligibility" :options="['eligible' => 'Eligible', 'flagged' => 'Flagged for review']" :value="$filters['eligibility'] ?? null" placeholder="Any" />
    <x-field name="q" label="Search" type="search" :value="$filters['q'] ?? null" hint="Reference, name or email" />
    <button class="btn secondary" type="submit">Filter</button>
</form>
@if ($applications->isEmpty())
    <x-empty title="No applications match these filters"><p>Change the filters to see other applications.</p></x-empty>
@else
<div class="table-wrap" tabindex="0" role="region" aria-label="Table (scrolls sideways on small screens)"><table>
    <thead><tr><th scope="col">Reference</th><th scope="col">Name</th><th scope="col">Eligibility</th><th scope="col">Status</th><th scope="col">Received</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>@foreach ($applications as $application)
        <tr>
            <td>{{ $application->reference }}</td>
            <td>{{ $application->fullName() }}<br><span class="muted small">{{ $application->email }}</span></td>
            <td><x-status :value="$application->eligibility_status" /></td>
            <td><x-status :value="$application->status" /></td>
            <td>{{ \App\Support\Format::dateTime($application->created_at) }}</td>
            <td><a class="text-link" href="{{ route('admin.applications.show', $application) }}">Review <span class="sr-only">application {{ $application->reference }}</span></a></td>
        </tr>
    @endforeach</tbody>
</table></div>
{{ $applications->links() }}
@endif
@endsection
