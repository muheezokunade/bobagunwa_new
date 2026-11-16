@extends('layouts.app', ['area' => 'admin', 'title' => 'Application '.$application->reference])
@section('content')
@php
    $F = \App\Support\Format::class;
    $allowed = \App\Models\MembershipApplication::TRANSITIONS[$application->status] ?? [];
    $decisionOptions = collect($allowed)->mapWithKeys(fn ($s) => [$s => \App\Models\MembershipApplication::statusLabel($s)])->all();
    $flags = is_array($application->eligibility_flags) ? $application->eligibility_flags : [];
@endphp
<x-page-head :title="'Application '.$application->reference" :subtitle="$application->fullName().' · received '.$F::dateTime($application->created_at)">
    <a class="btn secondary" href="{{ route('admin.applications.index') }}">All applications</a>
</x-page-head>

<dl class="stats">
    <div><dt>Status</dt><dd><x-status :value="$application->status" /></dd></div>
    <div><dt>Eligibility</dt><dd><x-status :value="$application->eligibility_status" /></dd></div>
    <div><dt>Decided</dt><dd>{{ $F::dateTime($application->decided_at) }}</dd></div>
</dl>

@if ($flags)
    <div class="alert warning" role="status">
        <strong>Eligibility screening flagged this application:</strong>
        <ul>@foreach ($flags as $flag)<li>{{ $flag['message'] ?? '' }} @if (! empty($flag['code']))<span class="muted small">({{ $flag['code'] }})</span>@endif</li>@endforeach</ul>
        <p class="small">A flag is not a decision. Check the details with the applicant before deciding.</p>
    </div>
@endif

@if ($matches->isNotEmpty())
    <div class="alert warning" role="status">
        <strong>Possible existing member{{ $matches->count() > 1 ? 's' : '' }} with the same name, email or phone:</strong>
        <ul>@foreach ($matches as $match)
            <li>
                @if (auth()->user()->hasPermission('members.read'))
                    <a href="{{ route('admin.members.show', $match) }}">{{ $match->membership_number }} — {{ $match->full_name }}</a>
                @else
                    {{ $match->membership_number }} — {{ $match->full_name }}
                @endif
                <x-status :value="$match->status" />
            </li>
        @endforeach</ul>
        <p class="small">Resolve any duplicate before approving. A member with the same email or phone cannot be approved again.</p>
    </div>
@endif

@if ($otherApplications->isNotEmpty())
    <div class="alert warning" role="status">
        <strong>Other applications with the same email or phone:</strong>
        <ul>@foreach ($otherApplications as $other)
            <li><a href="{{ route('admin.applications.show', $other) }}">{{ $other->reference }} — {{ $other->fullName() }}</a> <x-status :value="$other->status" /> <span class="muted small">received {{ $F::date($other->created_at) }}</span></li>
        @endforeach</ul>
    </div>
@endif

@if ($application->member)
    <div class="alert success" role="status">
        Approved as member
        @if (auth()->user()->hasPermission('members.read'))
            <a href="{{ route('admin.members.show', $application->member) }}">{{ $application->member->membership_number }} — {{ $application->member->full_name }}</a>.
        @else
            {{ $application->member->membership_number }} — {{ $application->member->full_name }}.
        @endif
        @if ($application->decider) Decided by {{ $application->decider->name }}. @endif
    </div>
@endif

<div class="grid-2">
    <section class="panel">
        <h2>Submitted details</h2>
        <dl class="details">
            <dt>First name</dt><dd>{{ $application->first_name }}</dd>
            <dt>Other names</dt><dd>{{ $application->other_names ?: '—' }}</dd>
            <dt>Last name</dt><dd>{{ $application->last_name }}</dd>
            <dt>Email</dt><dd>{{ $application->email }}</dd>
            <dt>Phone</dt><dd>{{ $application->phone }}</dd>
            <dt>Date of birth</dt><dd>{{ $F::date($application->date_of_birth) }}</dd>
            <dt>Ijebu town</dt><dd>{{ $application->ijebu_town }}</dd>
            <dt>City of residence</dt><dd>{{ $application->city }}</dd>
            <dt>Occupation</dt><dd>{{ $application->occupation }}</dd>
            <dt>Reason for applying</dt><dd>{!! nl2br(e($application->reason)) !!}</dd>
            <dt>Declaration accepted</dt><dd>{{ $F::dateTime($application->declaration_accepted_at) }}</dd>
            <dt>Privacy notice accepted</dt><dd>{{ $F::dateTime($application->privacy_accepted_at) }} <span class="muted small">(version {{ $application->privacy_notice_version }})</span></dd>
            <dt>Screening rules</dt><dd>{{ $application->screening_version }}</dd>
        </dl>
    </section>

    <div>
        @if ($allowed)
            <form class="panel" method="post" action="{{ route('admin.applications.decide', $application) }}" data-confirm="Record this decision on application {{ $application->reference }}? Approval and rejection are final.">
                @csrf
                <h2>Record a decision</h2>
                <x-select name="decision" label="Next step" :options="$decisionOptions" required placeholder="Choose…" />
                <x-textarea name="private_note" label="Review note (officers only)" required rows="4" hint="Explain the reason for this step. The applicant does not see this note." />
                <x-textarea name="applicant_message" label="Message to the applicant" rows="4" hint="Required when requesting more information or when not approving. The applicant sees this when they check their status, and a message is prepared for delivery." />
                <p class="small muted">Approving creates the member record with a permanent membership number and issues a one-time activation link.</p>
                <button class="btn" type="submit">Record decision</button>
            </form>
        @else
            <section class="panel">
                <h2>Decision</h2>
                <p class="muted">This application is {{ strtolower(\App\Models\MembershipApplication::statusLabel($application->status)) }}. No further decisions can be recorded.</p>
            </section>
        @endif

        <section class="panel">
            <h2>Prepared messages</h2>
            @if ($messages->isEmpty())
                <p class="muted">No messages have been prepared for this applicant.</p>
            @else
                <ul>@foreach ($messages as $message)
                    <li><a href="{{ route('admin.messages.show', $message) }}">{{ $message->subject }}</a> <x-status :value="$message->status" /> <span class="muted small">{{ $F::dateTime($message->created_at) }}</span></li>
                @endforeach</ul>
            @endif
        </section>
    </div>
</div>

<section class="panel">
    <h2>Review history</h2>
    @if ($application->events->isEmpty())
        <p class="muted">No history recorded.</p>
    @else
    <ol class="timeline">
        @foreach ($application->events as $event)
            <li>
                <time datetime="{{ $event->created_at?->toIso8601String() }}">{{ $F::dateTime($event->created_at) }}</time>
                <strong>{{ $F::label($event->type) }}</strong>
                @if ($event->from_status || $event->to_status)
                    — {{ $event->from_status ? \App\Models\MembershipApplication::statusLabel($event->from_status) : 'New' }} to {{ $event->to_status ? \App\Models\MembershipApplication::statusLabel($event->to_status) : '—' }}
                @endif
                <span class="muted small">by {{ $event->actor?->name ?? ($event->type === 'applicant_response' || $event->type === 'submitted' ? 'Applicant' : 'System') }}</span>
                @if ($event->private_note)<p><span class="badge">Officers only</span> {!! nl2br(e($event->private_note)) !!}</p>@endif
                @if ($event->applicant_message)<p><span class="badge info">Shown to applicant</span> {!! nl2br(e($event->applicant_message)) !!}</p>@endif
            </li>
        @endforeach
    </ol>
    @endif
</section>
@endsection
