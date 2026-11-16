@extends('layouts.app', ['area' => 'admin', 'title' => 'Assessments'])
@section('content')
<x-page-head title="Assessments" subtitle="Dues and levies charged to members.">
    @if (auth()->user()->hasPermission('finance.assessments.manage'))<a class="btn" href="{{ route('admin.finance.assessments.create') }}">New assessment</a>@endif
</x-page-head>
<p class="actions">Show:
    <a href="{{ route('admin.finance.assessments.index') }}" @if (! $status) aria-current="page" @endif>All</a> ·
    <a href="{{ route('admin.finance.assessments.index', ['status' => 'draft']) }}" @if ($status === 'draft') aria-current="page" @endif>Drafts</a> ·
    <a href="{{ route('admin.finance.assessments.index', ['status' => 'posted']) }}" @if ($status === 'posted') aria-current="page" @endif>Posted</a> ·
    <a href="{{ route('admin.finance.assessments.index', ['status' => 'discarded']) }}" @if ($status === 'discarded') aria-current="page" @endif>Discarded</a>
</p>
@if ($assessments->isEmpty())
    <x-empty title="No assessments"><p>Create an assessment to charge dues or a levy to members.</p></x-empty>
@else
<div class="table-wrap"><table>
    <thead><tr><th scope="col">Reference</th><th scope="col">Title</th><th scope="col">Category</th><th scope="col">Effective</th><th scope="col">Origin</th><th scope="col">Status</th><th scope="col" class="num">Per member</th><th scope="col" class="num">Members</th><th scope="col" class="num">Total</th></tr></thead>
    <tbody>@foreach ($assessments as $a)
        <tr><td><a href="{{ route('admin.finance.assessments.show', $a) }}">{{ $a->reference }}</a></td><td>{{ $a->title }}</td><td>{{ $a->category->name }}</td><td>{{ \App\Support\Format::date($a->effective_on) }}</td><td>{{ \App\Support\Format::label($a->origin) }}</td><td><x-status :value="$a->status" /></td>
            <td class="num">{{ $a->origin === 'native' ? \App\Support\Money::format($a->amount_kobo) : 'Varies' }}</td><td class="num">{{ $a->status === 'posted' ? $a->target_count : '—' }}</td><td class="num">{{ $a->status === 'posted' ? \App\Support\Money::format($a->total_kobo) : '—' }}</td></tr>
    @endforeach</tbody>
</table></div>
{{ $assessments->links() }}
@endif
@endsection
