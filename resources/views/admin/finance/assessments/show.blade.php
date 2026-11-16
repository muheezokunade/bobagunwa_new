@extends('layouts.app', ['area' => 'admin', 'title' => $assessment->reference])
@section('content')
<x-page-head :title="$assessment->title" :subtitle="$assessment->reference.' · '.$assessment->category->name" />
<dl class="details">
    <dt>Status</dt><dd><x-status :value="$assessment->status" /></dd>
    <dt>Origin</dt><dd>{{ \App\Support\Format::label($assessment->origin) }}</dd>
    <dt>Amount per member</dt><dd>{{ $assessment->origin === 'native' ? \App\Support\Money::format($assessment->amount_kobo) : 'Varies by member (imported)' }}</dd>
    <dt>Effective date</dt><dd>{{ \App\Support\Format::date($assessment->effective_on) }}</dd>
    <dt>Due date</dt><dd>{{ \App\Support\Format::date($assessment->due_on) }}</dd>
    @if ($assessment->period_label)<dt>Period</dt><dd>{{ $assessment->period_label }}</dd>@endif
    @if ($assessment->description)<dt>Description</dt><dd>{{ $assessment->description }}</dd>@endif
    <dt>Drafted by</dt><dd>{{ $assessment->creator->name }} · {{ \App\Support\Format::dateTime($assessment->created_at) }}</dd>
    @if ($assessment->poster)<dt>Posted by</dt><dd>{{ $assessment->poster->name }} · {{ \App\Support\Format::dateTime($assessment->posted_at) }}</dd>@endif
</dl>

@if ($preview)
    <h2>Preview before posting</h2>
    <dl class="stats">
        <div><dt>Members to be charged</dt><dd>{{ $preview['targets']->count() }}</dd></div>
        <div><dt>Amount each</dt><dd><x-money :kobo="$assessment->amount_kobo" /></dd></div>
        <div><dt>Total to be assessed</dt><dd class="value wine"><x-money :kobo="$preview['total_kobo']" /></dd></div>
        <div><dt>Excluded (not active)</dt><dd>{{ $preview['excluded']->count() }}</dd></div>
    </dl>
    @if ($preview['possible_duplicates']->isNotEmpty())
        <div class="alert warning">Already charged in this category for period “{{ $assessment->period_label }}”: {{ $preview['possible_duplicates']->implode(', ') }}. Check that this is not a duplicate charge.</div>
    @endif
    @if ($preview['excluded']->isNotEmpty())
        <p class="small muted">Not charged because they are not active: {{ $preview['excluded']->map(fn ($m) => $m->membership_number)->implode(', ') }}</p>
    @endif
    <details><summary>Members to be charged ({{ $preview['targets']->count() }})</summary>
        <div class="table-wrap"><table><thead><tr><th scope="col">Number</th><th scope="col">Name</th></tr></thead>
            <tbody>@foreach ($preview['targets'] as $m)<tr><td>{{ $m->membership_number }}</td><td>{{ $m->full_name }}</td></tr>@endforeach</tbody></table></div>
    </details>
    @if (auth()->user()->hasPermission('finance.assessments.manage'))
        <div class="grid-2 section-gap">
            <form class="panel" method="post" action="{{ route('admin.finance.assessments.post', $assessment) }}" data-confirm="Post this assessment to {{ $preview['targets']->count() }} members? Posted assessments cannot be edited.">
                @csrf
                <x-idem />
                <input type="hidden" name="expected_targets" value="{{ $preview['targets']->count() }}">
                <h3>Post assessment</h3>
                <p>Posting creates one obligation for each member above. It cannot be edited afterwards; corrections are made with recorded adjustments.</p>
                <x-check name="confirm" label="I have checked the member list, amount and total." />
                <button class="btn" type="submit" @disabled($preview['targets']->isEmpty())>Post to {{ $preview['targets']->count() }} members</button>
            </form>
            <form class="panel" method="post" action="{{ route('admin.finance.assessments.discard', $assessment) }}" data-confirm="Discard this draft?">
                @csrf
                <h3>Discard draft</h3>
                <p>The draft is kept for the record but can no longer be posted.</p>
                <button class="btn secondary" type="submit">Discard draft</button>
            </form>
        </div>
    @endif
@endif

@if ($obligations)
    <h2>Obligations created</h2>
    <dl class="stats"><div><dt>Members charged</dt><dd>{{ $assessment->target_count }}</dd></div><div><dt>Total assessed</dt><dd><x-money :kobo="$assessment->total_kobo" /></dd></div></dl>
    <div class="table-wrap"><table>
        <thead><tr><th scope="col">Member</th><th scope="col" class="num">Amount</th><th scope="col">Ledger</th></tr></thead>
        <tbody>@foreach ($obligations as $o)
            <tr><td>{{ $o->member->membership_number }} · {{ $o->member->full_name }}</td><td class="num"><x-money :kobo="$o->amount_kobo" /></td><td><a href="{{ route('admin.finance.members.show', $o->member_id) }}">Member ledger</a></td></tr>
        @endforeach</tbody>
    </table></div>
    {{ $obligations->links() }}
@endif
@endsection
