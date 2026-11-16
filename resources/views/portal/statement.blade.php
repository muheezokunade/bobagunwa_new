@extends('layouts.app', ['area' => 'portal', 'title' => 'Statement'])
@section('content')
<x-page-head title="Financial statement" :subtitle="$member->full_name.' · '.$member->membership_number">
    
    <a class="btn secondary" href="{{ route('portal.statement.csv', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Download CSV</a>
</x-page-head>
@include('partials.statement', ['statement' => $statement, 'from' => $from, 'to' => $to, 'action' => route('portal.statement')])
@endsection
