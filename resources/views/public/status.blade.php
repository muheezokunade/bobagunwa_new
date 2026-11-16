@extends('layouts.public', ['title' => 'Track an application', 'noindex' => true])
@section('content')
<x-public-hero title="Track an" em="application" crumb="Track an application" />
<section class="section">
    <div class="narrow">
        @include('partials.flash')
        @if (! empty($notFound))
            <div class="alert error" role="alert">We could not find an application with that reference and tracking code. Check both and try again; tracking codes expire 90 days after applying.</div>
        @endif
        @if ($application)
            @if (! empty($responded))<div class="alert success" role="status">Thank you. Your reply has been sent to the reviewing officers.</div>@endif
            <dl class="details">
                <dt>Reference</dt><dd>{{ $application->reference }}</dd>
                <dt>Applicant</dt><dd>{{ $application->first_name }} {{ $application->last_name }}</dd>
                <dt>Received</dt><dd>{{ \App\Support\Format::date($application->created_at) }}</dd>
                <dt>Current status</dt><dd><x-status :value="$application->status" /></dd>
            </dl>
            <h2>Updates</h2>
            <ol class="timeline">
                @foreach ($events as $event)
                    <li><time datetime="{{ $event->created_at->toIso8601String() }}">{{ \App\Support\Format::dateTime($event->created_at) }}</time>
                        <strong>{{ $event->type === 'applicant_response' ? 'Your reply' : \App\Models\MembershipApplication::statusLabel($event->to_status ?? $application->status) }}</strong>
                        <p>{{ $event->applicant_message }}</p></li>
                @endforeach
            </ol>
            @if ($application->status === 'changes_requested')
                <form method="post" action="{{ route('membership.status.respond') }}">
                    @csrf
                    <input type="hidden" name="reference" value="{{ $application->reference }}">
                    <input type="hidden" name="code" value="{{ $code }}">
                    <x-textarea name="response" label="Your reply to the officers" required />
                    <button class="btn" type="submit">Send reply</button>
                </form>
            @endif
        @else
            <p>Enter the application reference and the private tracking code you received when you applied.</p>
            <form method="post" action="{{ route('membership.status.lookup') }}">
                @csrf
                <x-field name="reference" label="Application reference" required hint="For example BOB-APP-2026-7K3Q9X" autocomplete="off" />
                <x-field name="code" label="Private tracking code" type="password" required autocomplete="off" />
                <button class="btn" type="submit">Check status</button>
            </form>
        @endif
    </div>
</section>
@endsection
