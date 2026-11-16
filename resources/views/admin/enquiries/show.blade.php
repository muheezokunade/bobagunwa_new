@extends('layouts.app', ['area' => 'admin', 'title' => 'Enquiry '.$enquiry->reference])
@section('content')
@php $F = \App\Support\Format::class; @endphp
<x-page-head :title="$enquiry->subject" :subtitle="'Enquiry '.$enquiry->reference.' · received '.$F::dateTime($enquiry->created_at)">
    <a class="btn secondary" href="{{ route('admin.enquiries.index') }}">All enquiries</a>
</x-page-head>
<div class="grid-2">
    <section class="panel">
        <h2>Message</h2>
        <div class="message-body">{!! nl2br(e($enquiry->message)) !!}</div>
    </section>
    <section class="panel">
        <h2>Contact details</h2>
        <dl class="details">
            <dt>Name</dt><dd>{{ $enquiry->name }}</dd>
            <dt>Email</dt><dd><a href="mailto:{{ $enquiry->email }}">{{ $enquiry->email }}</a></dd>
            <dt>Phone</dt><dd>{{ $enquiry->phone ?: '—' }}</dd>
            <dt>Topic</dt><dd>{{ $F::label($enquiry->topic) }}</dd>
            <dt>Reference</dt><dd>{{ $enquiry->reference }}</dd>
            <dt>Status</dt><dd><x-status :value="$enquiry->status" /></dd>
            <dt>Assigned to</dt><dd>{{ $enquiry->assignee?->name ?? 'Unassigned' }}</dd>
            @if ($enquiry->resolved_at)<dt>Resolved</dt><dd>{{ $F::dateTime($enquiry->resolved_at) }}</dd>@endif
            <dt>Consent to contact</dt><dd>{{ $F::dateTime($enquiry->consent_at) }}</dd>
        </dl>
    </section>
</div>
<div class="grid-2">
    <form class="panel" method="post" action="{{ route('admin.enquiries.update', $enquiry) }}">
        @csrf
        <h2>Update</h2>
        <x-select name="status" label="Status" :value="$enquiry->status" required :options="['new' => 'New', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed_spam' => 'Closed as spam']" />
        <x-select name="assigned_to" label="Assigned to" :value="$enquiry->assigned_to" placeholder="Unassigned" :options="$officers->pluck('name', 'id')->all()" />
        <x-textarea name="note" label="Internal note (optional)" rows="4" hint="Officers only. Record what was done, for example how and when you replied." />
        <button class="btn" type="submit">Save</button>
    </form>
    <section class="panel">
        <h2>Notes</h2>
        @if ($enquiry->notes->isEmpty())
            <p class="muted">No notes yet.</p>
        @else
            <ol class="timeline">
                @foreach ($enquiry->notes as $note)
                    <li><time datetime="{{ $note->created_at?->toIso8601String() }}">{{ $F::dateTime($note->created_at) }}</time> <strong>{{ $note->user?->name ?? 'System' }}</strong><p>{!! nl2br(e($note->note)) !!}</p></li>
                @endforeach
            </ol>
        @endif
    </section>
</div>
@endsection
