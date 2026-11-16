@extends('layouts.app', ['area' => 'admin', 'title' => 'Audit history'])
@section('content')
@php $F = \App\Support\Format::class; $security = $log === 'security'; @endphp
<x-page-head title="Audit history" subtitle="Append-only records of officer actions and security events. Entries cannot be edited or deleted.">
    <a class="btn secondary" href="{{ route('admin.audit.export') }}">Export audit log (CSV)</a>
</x-page-head>
<form method="get" action="{{ route('admin.audit.index') }}" class="filters">
    <x-select name="log" label="Log" :value="$log" :options="['audit' => 'Officer actions', 'security' => 'Security events']" />
    <x-field name="action" label="{{ $security ? 'Event starts with' : 'Action starts with' }}" :value="$filters['action'] ?? null" hint="e.g. {{ $security ? 'login' : 'member.' }}" />
    @unless ($security)
        <x-field name="subject" label="Subject type" :value="$filters['subject'] ?? null" hint="e.g. Member, Payment" />
    @endunless
    <x-field name="from" label="From" type="date" :value="$filters['from'] ?? null" />
    <x-field name="to" label="To" type="date" :value="$filters['to'] ?? null" />
    <button class="btn secondary" type="submit">Filter</button>
</form>
<p class="small muted">Times are shown in Lagos time. The CSV export contains the officer-action log in UTC.</p>
@if ($events->isEmpty())
    <x-empty title="No entries match these filters"><p>Change the filters to see other entries.</p></x-empty>
@else
<div class="table-wrap" tabindex="0" role="region" aria-label="Table (scrolls sideways on small screens)"><table>
    <caption>{{ $security ? 'Security events' : 'Officer actions' }}</caption>
    <thead><tr>
        <th scope="col">Time</th>
        <th scope="col">{{ $security ? 'Account' : 'Officer' }}</th>
        <th scope="col">{{ $security ? 'Event' : 'Action' }}</th>
        @unless ($security)<th scope="col">Subject</th><th scope="col">Summary</th>@endunless
        <th scope="col">Details</th>
    </tr></thead>
    <tbody>@foreach ($events as $e)
        <tr>
            <td>{{ $F::dateTime($e->created_at) }}</td>
            @if ($security)
                <td>{{ $e->user?->email ?? '—' }}</td>
                <td><code>{{ $e->event }}</code></td>
            @else
                <td>{{ $e->actor?->name ?? 'System' }}</td>
                <td><code>{{ $e->action }}</code></td>
                <td>{{ $e->subject_type ? $e->subject_type.' #'.$e->subject_id : '—' }}</td>
                <td>{{ $e->summary }}</td>
            @endif
            <td>
                @if ($e->context)
                    <details>
                        <summary>Show details</summary>
                        <pre>{{ json_encode($e->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
        </tr>
    @endforeach</tbody>
</table></div>
{{ $events->links() }}
@endif
@endsection
