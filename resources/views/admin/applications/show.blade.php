@extends('layouts.app', ['area' => 'admin', 'title' => 'Application '.$application->reference])
@section('content')
@php
    $F = \App\Support\Format::class;
    $allowed = $application->stepsFor(auth()->user());
    $open = \App\Models\MembershipApplication::TRANSITIONS[$application->status] ?? [];
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
            <dt>Middle name</dt><dd>{{ $application->other_names ?: '—' }}</dd>
            <dt>Last name (surname)</dt><dd>{{ $application->last_name }}</dd>
            <dt>Date of birth</dt><dd>{{ $F::date($application->date_of_birth) }}</dd>
            <dt>Mobile number</dt><dd>{{ $application->phone }}</dd>
            <dt>Email</dt><dd>{{ $application->email }}</dd>
            <dt>Profession / trade</dt><dd>{{ $application->occupation }}</dd>
            <dt>Town of origin</dt><dd>{{ $application->ijebu_town }}</dd>
            <dt>City of residence</dt><dd>{{ $application->city }}</dd>
            <dt>Country of residence</dt><dd>{{ $application->country ?: '—' }}</dd>
            <dt>Current address</dt><dd>{{ $application->address ?: '—' }}</dd>
            <dt>Marital status</dt><dd>{{ \App\Http\Controllers\Public\ApplicationController::MARITAL_STATUSES[$application->marital_status] ?? '—' }}</dd>
            <dt>Profile summary</dt><dd class="pre-line">{{ $application->profile_summary ?: '—' }}</dd>
            @if ($application->reason)<dt>Reason for applying</dt><dd class="pre-line">{{ $application->reason }}</dd>@endif
            <dt>Declaration accepted</dt><dd>{{ $F::dateTime($application->declaration_accepted_at) }}</dd>
            <dt>Privacy notice accepted</dt><dd>{{ $F::dateTime($application->privacy_accepted_at) }} <span class="muted small">(version {{ $application->privacy_notice_version }})</span></dd>
            <dt>Screening rules</dt><dd>{{ $application->screening_version }}</dd>
        </dl>
        <h3>Supporting documents</h3>
        @php $byKind = $application->attachments->groupBy('kind'); @endphp
        <ul class="plain">
            @foreach (\App\Models\ApplicationAttachment::KINDS as $kind => [$label, $isRequired])
                @foreach ($byKind->get($kind, collect()) as $i => $attachment)
                    <li>{{ $label }}@if ($byKind->get($kind)->count() > 1) ({{ $i + 1 }})@endif:
                        <a href="{{ route('admin.applications.attachments.show', [$application, $attachment]) }}">{{ $attachment->file->original_name }}</a>
                        <span class="muted small">{{ strtoupper(pathinfo($attachment->file->path, PATHINFO_EXTENSION)) }}, {{ number_format($attachment->file->size_bytes / 1024) }} KB</span></li>
                @endforeach
                @if ($isRequired && ! $byKind->has($kind))<li>{{ $label }}: <span class="muted">not provided</span></li>@endif
            @endforeach
        </ul>
        <p class="small muted">Downloads are recorded in the audit log. Do not forward these documents outside the platform.</p>
    </section>

    <div>
        @if ($application->recommendation && in_array($application->status, ['under_review'], true))
            <section class="panel">
                <h2>Recommendation</h2>
                <p><strong>{{ $application->recommendation === 'approve' ? 'Recommended for approval' : 'Recommended for rejection' }}</strong> by {{ $application->recommender?->name ?? 'an officer' }}, {{ $F::dateTime($application->recommended_at) }}.</p>
                @if (! auth()->user()->hasPermission('applications.decide'))
                    <p class="muted small">Waiting for the final approver (the chair of the screening committee).</p>
                @elseif ((int) $application->recommended_by === auth()->id())
                    <p class="muted small">You made this recommendation, so a different officer must give the final decision.</p>
                @endif
            </section>
        @endif
        @if ($allowed)
            <form class="panel" method="post" action="{{ route('admin.applications.decide', $application) }}" data-confirm="Record this decision on application {{ $application->reference }}? Approval and rejection are final.">
                @csrf
                <h2>Record a decision</h2>
                <x-select name="decision" label="Next step" :options="$decisionOptions" required placeholder="Choose…" />
                <x-textarea name="private_note" label="Review note (officers only)" required rows="4" hint="Explain the reason for this step. The applicant does not see this note." />
                <x-textarea name="applicant_message" label="Message to the applicant" rows="4" hint="Required when requesting more information or when not approving. The applicant sees this when they check their status, and a message is prepared for delivery." />
                <p class="small muted">@if (\App\Support\Settings::bool('membership.two_step_approval'))Two-step approval: a reviewer (normally the General Secretary) recommends; a different officer with final-decision rights approves or rejects. @endif Approving creates the member record with a permanent membership number and issues a one-time activation link.</p>
                <button class="btn" type="submit">Record decision</button>
            </form>
        @elseif ($open)
            <section class="panel">
                <h2>Decision</h2>
                <p class="muted">There is nothing for you to record at this stage.</p>
            </section>
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
