@extends('layouts.app', ['area' => 'admin', 'title' => 'Messages to deliver'])
@section('content')
<x-page-head title="Messages to deliver" subtitle="Prepared notifications that must be sent to the person through an approved private channel." />
<form method="get" action="{{ route('admin.messages.index') }}" class="filters">
    <x-select name="status" label="Status" :value="$status" :options="['open' => 'Awaiting delivery (pending or failed)', 'pending' => 'Pending', 'failed' => 'Failed', 'accepted_by_mail_server' => 'Sent by email', 'delivered_manually' => 'Delivered', 'cancelled' => 'Cancelled', 'all' => 'All messages']" />
    <button class="btn secondary" type="submit">Filter</button>
</form>
@if ($messages->isEmpty())
    <x-empty title="No messages match this filter"><p>{{ $status === 'open' ? 'There is nothing waiting to be delivered.' : 'Choose another status to see other messages.' }}</p></x-empty>
@else
<div class="table-wrap" tabindex="0" role="region" aria-label="Table (scrolls sideways on small screens)"><table>
    <thead><tr><th scope="col">Prepared</th><th scope="col">Recipient</th><th scope="col">Subject</th><th scope="col">Purpose</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>@foreach ($messages as $message)
        <tr>
            <td>{{ \App\Support\Format::dateTime($message->created_at) }}</td>
            <td>{{ $message->recipient_name }}<br><span class="muted small">{{ $message->recipient_email ?: $message->recipient_phone }}</span></td>
            <td>{{ $message->subject }}</td>
            <td>{{ \App\Support\Format::label($message->purpose) }}</td>
            <td><x-status :value="$message->status" />@if ($message->attester)<br><span class="muted small">by {{ $message->attester->name }}</span>@endif</td>
            <td><a class="text-link" href="{{ route('admin.messages.show', $message) }}">Open <span class="sr-only">message to {{ $message->recipient_name }}</span></a></td>
        </tr>
    @endforeach</tbody>
</table></div>
{{ $messages->links() }}
@endif
@endsection
