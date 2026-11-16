@php $link = fn ($route, $label, $pattern = null) => '<a href="'.route($route).'"'.(request()->routeIs($pattern ?? $route) ? ' aria-current="page"' : '').'>'.e($label).'</a>'; @endphp
<h2>My membership</h2>
{!! $link('portal.dashboard', 'Overview') !!}
{!! $link('portal.profile', 'My profile') !!}
{!! $link('portal.finances', 'Dues and payments', 'portal.finances') !!}
{!! $link('portal.statement', 'Statement') !!}
<h2>Association</h2>
{!! $link('portal.notices', 'Notices') !!}
{!! $link('portal.events', 'Events', 'portal.events*') !!}
{!! $link('portal.documents', 'Documents') !!}
<h2>Account</h2>
{!! $link('portal.security', 'Password and sessions') !!}
<a href="{{ route('home') }}">Public website</a>
