@extends('layouts.app', ['area' => 'admin', 'title' => 'Settings'])
@section('content')
@php $F = \App\Support\Format::class; @endphp
<x-page-head title="Settings" subtitle="Organisation settings. Secrets such as passwords and keys are never stored here." />

@if ($environment === 'production')
    <div class="alert warning" role="status">This is the production environment. Actions protected by a launch approval are refused until the association’s approval is recorded below.</div>
@else
    <div class="alert warning" role="status">This is the <strong>{{ $environment }}</strong> environment. Launch approvals are recorded here but only block actions in production, so protected actions are allowed in this environment for testing.</div>
@endif

<div class="grid-2">
    <form class="panel" method="post" action="{{ route('admin.settings.update') }}">
        @csrf
        <h2>Organisation settings</h2>
        @foreach ($definitions as $group => $items)
            @php $items = collect($items)->reject(fn ($d) => in_array($d['type'], ['approval', 'internal'], true)); @endphp
            @continue($items->isEmpty())
            <fieldset>
                <legend>{{ $group }}</legend>
                @foreach ($items as $key => $def)
                    @php $field = str_replace('.', '__', $key); $value = $values[$key] ?? null; @endphp
                    @if ($def['type'] === 'bool')
                        <x-check :name="$field" :label="$def['label']" :checked="(bool) $value" />
                        @if (! empty($def['help']))<p class="small muted">{{ $def['help'] }}</p>@endif
                    @elseif ($def['type'] === 'int')
                        <x-field :name="$field" :label="$def['label']" type="number" min="3" max="10" required :value="$value" :hint="$def['help'] ?? 'Between 3 and 10.'" />
                    @else
                        <x-field :name="$field" :label="$def['label']" :type="$def['type'] === 'email' ? 'email' : 'text'" :value="$value" :hint="$def['help'] ?? null" />
                    @endif
                    @if ($key === 'membership.number_width')
                        <p class="small">The next membership number issued will be <strong>{{ $nextNumber }}</strong>.</p>
                    @endif
                @endforeach
            </fieldset>
        @endforeach
        <button class="btn" type="submit">Save settings</button>
    </form>

    <section class="panel">
        <h2>Launch approvals</h2>
        <p class="small muted">Each approval records that the association has formally approved a step, with a reference such as minutes or a signed decision. In production, the related action stays disabled until the approval is recorded.</p>
        @foreach (collect(\App\Support\Settings::definitions())->where('type', 'approval') as $key => $def)
            @php
                $approval = $values[$key] ?? null;
                $approved = is_array($approval) && ! empty($approval['approved']);
                $prefix = str_replace('.', '-', $key).'-';
            @endphp
            <div>
                <h3>{{ $def['label'] }}</h3>
                @if ($approved)
                    <p><x-status value="approved" /> Reference: {{ $approval['reference'] ?? '—' }}<br>
                        <span class="muted small">Recorded {{ ! empty($approval['recorded_at']) ? $F::dateTime(\Carbon\CarbonImmutable::parse($approval['recorded_at'])) : '—' }}</span></p>
                    <form method="post" action="{{ route('admin.settings.approval', $key) }}" data-confirm="Withdraw this approval? In production the related action will be disabled again.">
                        @csrf
                        <input type="hidden" name="approved" value="0">
                        <button class="btn small danger" type="submit">Withdraw approval</button>
                    </form>
                @else
                    <p><x-status value="pending" /> Not yet approved.</p>
                    <form method="post" action="{{ route('admin.settings.approval', $key) }}" data-confirm="Record the association’s approval for: {{ $def['label'] }}?">
                        @csrf
                        <input type="hidden" name="approved" value="1">
                        <x-field :prefix="$prefix" name="reference" label="Approval reference" required hint="For example: “Executive meeting minutes, 12 Oct 2026, item 4”." />
                        <button class="btn small" type="submit">Record approval</button>
                    </form>
                @endif
            </div>
        @endforeach
    </section>
</div>
@endsection
