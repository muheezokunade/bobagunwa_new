@extends('layouts.app', ['area' => 'admin', 'title' => 'Overview'])
@section('content')
<x-page-head title="Overview" subtitle="Work waiting for your attention." />
@if (empty($tiles))
    <x-empty title="No work queues for your role"><p>Your account has no administrative duties assigned yet.</p></x-empty>
@else
<div class="tile-grid">
    @foreach ($tiles as $tile)
        <a class="tile {{ ! empty($tile['alert']) ? 'alert-tile' : '' }}" href="{{ $tile['route'] }}"><strong>{{ number_format($tile['value']) }}</strong><span>{{ $tile['label'] }}</span></a>
    @endforeach
</div>
@endif
@if ($recent->isNotEmpty())
    <h2>Recent activity</h2>
    <div class="table-wrap"><table>
        <thead><tr><th scope="col">When</th><th scope="col">Officer</th><th scope="col">Activity</th></tr></thead>
        <tbody>@foreach ($recent as $event)
            <tr><td>{{ \App\Support\Format::dateTime($event->created_at) }}</td><td>{{ $event->actor?->name ?? 'System or applicant' }}</td><td>{{ $event->summary }}</td></tr>
        @endforeach</tbody>
    </table></div>
    <a class="text-link" href="{{ route('admin.audit.index') }}">Full audit history</a>
@endif
@endsection
