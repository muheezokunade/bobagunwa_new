@extends('layouts.app', ['area' => 'admin', 'title' => 'Enquiries'])
@section('content')
@php
    $mine = (bool) request()->query('mine');
    $tabs = ['' => 'Open (new and in progress)', 'new' => 'New', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed_spam' => 'Closed as spam'];
    $current = in_array($status, \App\Services\EnquiryService::STATUSES, true) ? $status : '';
@endphp
<x-page-head title="Enquiries" subtitle="Messages sent through the public contact form." />
<nav aria-label="Filter enquiries" class="actions">
    @foreach ($tabs as $key => $label)
        <a class="btn small {{ $current === $key ? '' : 'secondary' }}" href="{{ route('admin.enquiries.index', array_filter(['status' => $key, 'mine' => $mine ? 1 : null])) }}" @if ($current === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    @if ($mine)
        <a class="btn small" href="{{ route('admin.enquiries.index', array_filter(['status' => $current])) }}" aria-current="page">Assigned to me (show everyone’s)</a>
    @else
        <a class="btn small secondary" href="{{ route('admin.enquiries.index', array_filter(['status' => $current, 'mine' => 1])) }}">Assigned to me</a>
    @endif
</nav>
@if ($enquiries->isEmpty())
    <x-empty title="No enquiries here"><p>There are no enquiries matching this view.</p></x-empty>
@else
<div class="table-wrap"><table>
    <thead><tr><th scope="col">Reference</th><th scope="col">Received</th><th scope="col">From</th><th scope="col">Topic</th><th scope="col">Subject</th><th scope="col">Status</th><th scope="col">Assigned to</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>@foreach ($enquiries as $enquiry)
        <tr>
            <td>{{ $enquiry->reference }}</td>
            <td>{{ \App\Support\Format::dateTime($enquiry->created_at) }}</td>
            <td>{{ $enquiry->name }}</td>
            <td>{{ \App\Support\Format::label($enquiry->topic) }}</td>
            <td>{{ $enquiry->subject }}</td>
            <td><x-status :value="$enquiry->status" /></td>
            <td>{{ $enquiry->assignee?->name ?? 'Unassigned' }}</td>
            <td><a class="text-link" href="{{ route('admin.enquiries.show', $enquiry) }}">Open <span class="sr-only">enquiry {{ $enquiry->reference }}</span></a></td>
        </tr>
    @endforeach</tbody>
</table></div>
{{ $enquiries->links() }}
@endif
@endsection
