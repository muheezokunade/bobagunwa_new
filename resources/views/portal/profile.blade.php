@extends('layouts.app', ['area' => 'portal', 'title' => 'My profile'])
@section('content')
<x-page-head title="My profile" subtitle="Keep your contact details up to date." />
<div class="grid-2">
    <section class="panel">
        <h2>Membership record</h2>
        <dl class="details">
            <dt>Membership number</dt><dd>{{ $member->membership_number }}</dd>
            <dt>Full name</dt><dd>{{ $member->full_name }}</dd>
            <dt>Email</dt><dd>{{ $member->email ?: '—' }}</dd>
            <dt>Date of birth</dt><dd>{{ \App\Support\Format::date($member->date_of_birth) }}</dd>
            <dt>Ijebu town</dt><dd>{{ $member->ijebu_town ?: '—' }}</dd>
            <dt>Member since</dt><dd>{{ \App\Support\Format::date($member->joined_on) }}</dd>
        </dl>
        <p class="small muted">To correct your name, email, date of birth or membership details, contact the secretariat. These are verified records.</p>
    </section>
    <form class="panel" method="post" action="{{ route('portal.profile.update') }}">
        @csrf
        <h2>Details you can update</h2>
        <input type="hidden" name="version" value="{{ $member->version }}">
        <x-field name="preferred_name" label="Preferred name" :value="$member->preferred_name" autocomplete="nickname" />
        <x-field name="phone" label="Phone number" type="tel" :value="$member->phone" autocomplete="tel" />
        <x-field name="city" label="City of residence" :value="$member->city" autocomplete="address-level2" />
        <x-field name="occupation" label="Occupation" :value="$member->occupation" />
        <x-field name="address" label="Postal address" :value="$member->address" autocomplete="street-address" />
        <button class="btn" type="submit">Save changes</button>
    </form>
</div>
@endsection
