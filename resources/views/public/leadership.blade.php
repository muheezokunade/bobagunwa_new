@extends('layouts.public', ['title' => 'Giwa-in-Council'])
@section('content')
<x-public-hero title="Giwa-in-Council" lead="Officers who steward the association on behalf of its members. Select a name to read their profile." />
<section class="section">
    <div class="wrap">
        {{-- The Giwa-in-Council is everyone on this page: the Giwa and his two deputies (large), then the chiefs and the executives (compact). --}}
        @php $groupLabels = ['council' => 'Giwa, Otun Giwa and Eta Giwa', 'chiefs' => 'Bobagunwa Chiefs', 'executive' => 'Bobagunwa Executives', 'other' => 'Other officers']; @endphp
        @forelse ($groups as $group => $leaders)
            <h2>{{ $groupLabels[$group] ?? ucfirst($group) }}@if ($group === 'executive' && $leaders->first()?->term_label) <span class="muted">({{ $leaders->first()->term_label }})</span>@endif</h2>
            <div class="people {{ $group === 'council' ? 'people-lead' : 'people-compact' }}">
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
