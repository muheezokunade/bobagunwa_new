@extends('layouts.app', ['area' => 'admin', 'title' => 'New assessment'])
@section('content')
<x-page-head title="New assessment" subtitle="Saved as a draft first. You review the member list and total before posting." />
@if ($categories->isEmpty())
    <div class="alert warning">Create an active category first. <a href="{{ route('admin.finance.categories.index') }}">Dues and levy categories</a></div>
@else
<form method="post" action="{{ route('admin.finance.assessments.store') }}" class="panel">
    @csrf
    <div class="form-grid">
        <x-select name="fee_category_id" label="Category" required placeholder="Choose a category" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->name.' ('.$c->code.')'])->all()" />
        <x-field name="title" label="Title" required hint="For example “Annual dues 2027”" />
        <x-field name="amount" label="Amount per member (₦)" required hint="Enter naira, e.g. 50000 or 50,000.00. Official amounts need the association’s approval." />
        <x-field name="period_label" label="Period label (optional)" hint="Used to warn about members already charged for the same period, e.g. 2027" />
        <x-field name="effective_on" label="Effective date" type="date" required :value="now(config('boba.display_timezone'))->toDateString()" />
        <x-field name="due_on" label="Due date (optional)" type="date" />
        <x-textarea name="description" label="Description (optional)" rows="3" class="full" />
    </div>
    <fieldset>
        <legend>Who is charged</legend>
        <label class="check" for="mode-all"><input type="radio" id="mode-all" name="target_mode" value="all_active" @checked(old('target_mode', 'all_active') === 'all_active')><span>All active members ({{ $members->count() }})</span></label>
        <label class="check" for="mode-selected"><input type="radio" id="mode-selected" name="target_mode" value="selected" @checked(old('target_mode') === 'selected')><span>Only the members selected below</span></label>
        <details>
            <summary>Select members</summary>
            <div class="table-wrap" tabindex="0" role="region" aria-label="Table (scrolls sideways on small screens)"><table>
                <thead><tr><th scope="col">Select</th><th scope="col">Number</th><th scope="col">Name</th></tr></thead>
                <tbody>@foreach ($members as $m)
                    <tr><td><input type="checkbox" id="m{{ $m->id }}" name="member_ids[]" value="{{ $m->id }}" @checked(in_array($m->id, old('member_ids', []))) aria-label="Select {{ $m->full_name }}"></td><td><label for="m{{ $m->id }}">{{ $m->membership_number }}</label></td><td>{{ $m->full_name }}</td></tr>
                @endforeach</tbody>
            </table></div>
        </details>
    </fieldset>
    <button class="btn" type="submit">Save draft and preview</button>
</form>
@endif
@endsection
