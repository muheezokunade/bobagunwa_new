@extends('layouts.public', ['title' => 'Apply for membership'])
@section('content')
<x-public-hero title="Apply for" em="membership" crumb="Apply" lead="Your application is reviewed by the association’s officers. Applying does not by itself make you a member." />
<section class="section">
    <div class="narrow">
        @include('partials.flash')
        @if (! $open)
            <x-empty title="Applications are paused">
                <p>The association is not accepting applications at the moment. Please <a href="{{ route('contact') }}">contact the secretariat</a>.</p>
            </x-empty>
        @else
        <form method="post" action="{{ route('membership.apply.store') }}" novalidate>
            @csrf
            <input type="hidden" name="form_stamp" value="{{ $stamp }}">
            <div class="hp-field" aria-hidden="true"><label for="website">Leave this field empty</label><input id="website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
            <p class="muted">Fields marked <span class="req">*</span> are required.</p>
            <fieldset>
                <legend>About you</legend>
                <div class="form-grid">
                    <x-field name="first_name" label="First name" required autocomplete="given-name" />
                    <x-field name="last_name" label="Surname" required autocomplete="family-name" />
                    <x-field name="other_names" label="Other names" class="full" autocomplete="additional-name" />
                    <x-date-parts name="date_of_birth" label="Date of birth" required hint="Membership is for those born 1 January 1980 – 31 December 1982. Other dates are considered individually by an officer." autocomplete="bday" class="full" />
                    <x-field name="ijebu_town" label="Ijebu town of origin" required hint="For example Ijebu Ode, Ijebu Igbo or Ago Iwoye." />
                    <x-field name="occupation" label="Occupation" required autocomplete="organization-title" />
                </div>
            </fieldset>
            <fieldset>
                <legend>How we can reach you</legend>
                <div class="form-grid">
                    <x-field name="email" label="Email address" type="email" required autocomplete="email" />
                    <x-field name="phone" label="Phone number" type="tel" required autocomplete="tel" hint="Include the country code if outside Nigeria." />
                    <x-field name="city" label="City of residence" required autocomplete="address-level2" class="full" />
                </div>
            </fieldset>
            <fieldset>
                <legend>Your application</legend>
                <x-textarea name="reason" label="Why would you like to join the association?" required hint="A few sentences are enough." />
                <x-check name="declaration" label="I declare that I am an Ijebu man, that the information I have given is true, and I understand that applying does not guarantee membership." />
                <x-check name="privacy" label="I have read the privacy notice and agree that the association may use these details to consider my application." />
                <p class="small">By ticking the box above you confirm you have read the <a href="{{ route('privacy') }}" target="_blank" rel="noopener">privacy notice</a> (opens in a new tab), which explains how the association uses the details in your application.</p>
            </fieldset>
            <button type="submit" class="btn">Submit application <span class="arrow" aria-hidden="true">→</span></button>
        </form>
        @endif
    </div>
</section>
@endsection
