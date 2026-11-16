@php
    $u = auth()->user();
    $link = fn ($route, $label, $pattern) => '<a href="'.route($route).'"'.(request()->routeIs($pattern) ? ' aria-current="page"' : '').'>'.e($label).'</a>';
@endphp
{!! $link('admin.dashboard', 'Overview', 'admin.dashboard') !!}
@if ($u->hasAnyPermission('applications.review', 'members.read', 'members.import'))
    <h2>Membership</h2>
    @if ($u->hasPermission('applications.review')) {!! $link('admin.applications.index', 'Applications', 'admin.applications.*') !!} @endif
    @if ($u->hasPermission('members.read')) {!! $link('admin.members.index', 'Member register', 'admin.members.*') !!} @endif
    @if ($u->hasPermission('members.import')) {!! $link('admin.imports.members.index', 'Import members', 'admin.imports.members.*') !!} @endif
@endif
@if ($u->hasAnyPermission('applications.review', 'accounts.issue', 'access.manage'))
    {!! $link('admin.messages.index', 'Messages to deliver', 'admin.messages.*') !!}
@endif
@if ($u->hasPermission('finance.read'))
    <h2>Finance</h2>
    {!! $link('admin.finance.index', 'Finance overview', 'admin.finance.index') !!}
    {!! $link('admin.finance.payments.index', 'Payments', 'admin.finance.payments.*') !!}
    {!! $link('admin.finance.assessments.index', 'Assessments', 'admin.finance.assessments.*') !!}
    {!! $link('admin.finance.categories.index', 'Dues and levy categories', 'admin.finance.categories.*') !!}
    {!! $link('admin.finance.reports.index', 'Reports and reconciliation', 'admin.finance.reports.*') !!}
    @if ($u->hasPermission('finance.import')) {!! $link('admin.finance.imports.index', 'Historical records', 'admin.finance.imports.*') !!} @endif
@endif
@if ($u->hasAnyPermission('content.manage', 'content.publish', 'events.manage', 'enquiries.manage'))
    <h2>Website and communication</h2>
    @if ($u->hasAnyPermission('content.manage', 'content.publish')) {!! $link('admin.content.index', 'Content', 'admin.content.*') !!} @endif
    @if ($u->hasPermission('events.manage')) {!! $link('admin.events.index', 'Events', 'admin.events.*') !!} @endif
    @if ($u->hasPermission('enquiries.manage')) {!! $link('admin.enquiries.index', 'Enquiries', 'admin.enquiries.*') !!} @endif
@endif
@if ($u->hasAnyPermission('access.manage', 'audit.read', 'settings.manage', 'backups.manage', 'integrity.alerts'))
    <h2>System</h2>
    @if ($u->hasPermission('access.manage')) {!! $link('admin.access.index', 'Officers and access', 'admin.access.*') !!} @endif
    @if ($u->hasPermission('audit.read')) {!! $link('admin.audit.index', 'Audit history', 'admin.audit.*') !!} @endif
    @if ($u->hasPermission('integrity.alerts')) {!! $link('admin.alerts.index', 'Integrity alerts', 'admin.alerts.*') !!} @endif
    @if ($u->hasPermission('settings.manage')) {!! $link('admin.settings.index', 'Settings', 'admin.settings.*') !!} @endif
    @if ($u->hasPermission('backups.manage')) {!! $link('admin.backups.index', 'Backups', 'admin.backups.*') !!} @endif
@endif
<h2>My account</h2>
{!! $link('admin.account', 'Password and sessions', 'admin.account*') !!}
