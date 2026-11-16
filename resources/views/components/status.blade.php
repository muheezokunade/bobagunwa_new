@props(['value'])
@php
    $map = [
        'ok' => ['valid', 'active', 'approved', 'verified', 'published', 'posted', 'eligible', 'committed', 'resolved', 'succeeded', 'delivered_manually', 'accepted_by_mail_server', 'attending', 'issued'],
        'warn' => ['pending', 'submitted', 'under_review', 'changes_requested', 'flagged', 'draft', 'previewed', 'pending_activation', 'in_progress', 'new', 'maybe'],
        'danger' => ['suspended', 'rejected', 'reversed', 'cancelled', 'failed', 'invalid', 'duplicate', 'disabled', 'closed_spam', 'not_attending'],
    ];
    $tone = collect($map)->search(fn ($values) => in_array($value, $values, true)) ?: '';
    $labels = ['submitted' => 'Received', 'under_review' => 'Under review', 'changes_requested' => 'Information requested', 'pending_activation' => 'Awaiting activation', 'accepted_by_mail_server' => 'Sent by email', 'delivered_manually' => 'Delivered', 'closed_spam' => 'Closed (spam)', 'not_attending' => 'Not attending'];
@endphp
<span class="badge {{ $tone }}">{{ $labels[$value] ?? ucfirst(str_replace('_', ' ', (string) $value)) }}</span>
