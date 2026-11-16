{{-- Not dismissible: stays until the alert is resolved after a passing check. --}}
@php $integrityAlerts = app(\App\Services\IntegrityMonitor::class)->bannerFor(auth()->user()); @endphp
@if ($integrityAlerts->isNotEmpty())
    <section class="alert {{ $integrityAlerts->contains('severity', 'critical') ? 'error' : 'warning' }}" role="alert" aria-labelledby="integrity-banner-title">
        <strong id="integrity-banner-title">{{ $integrityAlerts->count() }} unresolved integrity {{ \Illuminate\Support\Str::plural('alert', $integrityAlerts->count()) }}</strong>
        <ul>
            @foreach ($integrityAlerts->take(3) as $a)
                <li><a href="{{ route('admin.alerts.show', $a) }}">#{{ $a->id }} {{ $a->title }}</a> — {{ $a->status === 'acknowledged' ? 'acknowledged, still unresolved' : 'needs attention' }}@if ($a->condition_cleared_at) · condition cleared, awaiting resolution @endif</li>
            @endforeach
        </ul>
        <p class="small">Do not correct financial records until the cause is understood. See <a href="{{ route('admin.alerts.index') }}">Integrity alerts</a>.</p>
    </section>
@endif
