@extends('layouts.public', ['title' => 'Leadership'])
@section('content')
<x-public-hero title="Leadership" lead="Officers who steward the association on behalf of its members." />
<section class="section">
    <div class="wrap">
        @php $groupLabels = ['executive' => 'Executive', 'council' => 'Council', 'chiefs' => 'Chiefs', 'other' => 'Other officers']; @endphp
        @forelse ($groups as $group => $leaders)
            <h2>{{ $groupLabels[$group] ?? ucfirst($group) }}</h2>
            <div class="people">
                @foreach ($leaders as $leader)
                    @include('public.partials.leader', ['leader' => $leader])
                @endforeach
            </div>
            @if (! $loop->last)<hr class="spacer">@endif
        @empty
            <x-empty title="Leadership details are being confirmed">
                <p>Names and titles are published here once the association has approved them.</p>
            </x-empty>
        @endforelse
    </div>
</section>
@endsection
