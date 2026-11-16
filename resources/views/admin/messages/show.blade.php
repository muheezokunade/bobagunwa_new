@extends('layouts.app', ['area' => 'admin', 'title' => 'Message to '.$message->recipient_name])
@section('content')
@php $F = \App\Support\Format::class; $open = in_array($message->status, ['pending', 'failed'], true); @endphp
<x-page-head :title="'Message to '.$message->recipient_name" :subtitle="$message->subject">
    <a class="btn secondary" href="{{ route('admin.messages.index') }}">All messages</a>
</x-page-head>
<div class="grid-2">
    <section class="panel">
        <h2>Recipient and delivery</h2>
        <dl class="details">
            <dt>Name</dt><dd>{{ $message->recipient_name }}</dd>
            <dt>Email</dt><dd>{{ $message->recipient_email ?: '—' }}</dd>
            <dt>Phone</dt><dd>{{ $message->recipient_phone ?: '—' }}</dd>
            <dt>Purpose</dt><dd>{{ $F::label($message->purpose) }}</dd>
            @if ($message->related_type === 'MembershipApplication' && $message->related_id && auth()->user()->hasPermission('applications.review'))
                <dt>Related to</dt><dd><a href="{{ route('admin.applications.show', $message->related_id) }}">Membership application</a></dd>
            @endif
            <dt>Channel</dt><dd>{{ $message->channel === 'smtp' ? 'Email (mail server)' : 'Manual delivery' }}</dd>
            <dt>Status</dt><dd><x-status :value="$message->status" /></dd>
            <dt>Prepared</dt><dd>{{ $F::dateTime($message->created_at) }}</dd>
            @if ($message->attempted_at)<dt>Email attempted</dt><dd>{{ $F::dateTime($message->attempted_at) }}</dd>@endif
            @if ($message->error)<dt>Delivery error</dt><dd>{{ $message->error }}</dd>@endif
            @if ($message->attested_at)
                <dt>Recorded</dt><dd>{{ $F::dateTime($message->attested_at) }}@if ($message->attester) by {{ $message->attester->name }}@endif</dd>
                <dt>Note</dt><dd>{{ $message->attestation_note ?: '—' }}</dd>
            @endif
        </dl>
    </section>
    <section class="panel">
        <h2>Subject: {{ $message->subject }}</h2>
        <div class="message-body">{!! nl2br(e($message->body)) !!}</div>
    </section>
</div>
@if ($open)
    <div class="alert warning" role="status">Record delivery only after you have actually sent this message to the recipient through the association’s approved private channel. Links in the message are private: do not share them with anyone else.</div>
    <div class="grid-2">
        <form class="panel" method="post" action="{{ route('admin.messages.delivered', $message) }}" data-confirm="Confirm that you have sent this message to {{ $message->recipient_name }}.">
            @csrf
            <h2>Record delivery</h2>
            <x-field prefix="d-" name="note" label="How was it sent?" required hint="For example: “Sent by WhatsApp to the verified number on 16 Nov”." />
            <x-check prefix="d-" name="confirm" label="I have sent this message to the recipient." />
            <button class="btn" type="submit">Record delivery</button>
        </form>
        <form class="panel" method="post" action="{{ route('admin.messages.cancel', $message) }}" data-confirm="Cancel this message? It will no longer appear in the delivery queue.">
            @csrf
            <h2>Cancel message</h2>
            <x-field prefix="c-" name="note" label="Reason for cancelling" required />
            <button class="btn danger" type="submit">Cancel message</button>
        </form>
    </div>
@endif
@endsection
