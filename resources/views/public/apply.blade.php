@extends('layouts.public', ['title' => 'Apply for membership'])
@section('content')
<x-public-hero title="Apply for" em="membership" crumb="Apply" lead="Your application is reviewed by the association’s officers. Applying does not by itself make you a member." />
<section class="section">
    <div class="narrow">
        @include('partials.flash')
        @if (request()->boolean('too_large'))
            <div class="alert error" role="alert" tabindex="-1">The documents you chose were too large to send together. Please use smaller files (a phone photo or a compressed PDF of each certificate is enough) and try again.</div>
        @endif
        @if (! $open)
            <x-empty title="Applications are paused">
                <p>The association is not accepting applications at the moment. Please <a href="{{ route('contact') }}">contact the secretariat</a>.</p>
            </x-empty>
        @else
        <form method="post" action="{{ route('membership.apply.store') }}" enctype="multipart/form-data" novalidate>
            @csrf
            <input type="hidden" name="form_stamp" value="{{ $stamp }}">
            <div class="hp-field" aria-hidden="true"><label for="website">Leave this field empty</label><input id="website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
            <p class="muted">Egbe Bobagunwa Okunrin Akile Ijebu member profile. Fields marked <span class="req">*</span> are required.</p>
            <fieldset>
                <legend>About you</legend>
                <div class="form-grid">
                    <x-field name="first_name" label="First name" required autocomplete="given-name" />
                    <x-field name="other_names" label="Middle name" required autocomplete="additional-name" />
                    <x-field name="last_name" label="Last name (surname)" required autocomplete="family-name" class="full" />
                    <x-date-parts name="date_of_birth" label="Date of birth" required hint="Membership is for those born 1 Jan 1980 – 31 Dec 1982. Other dates are considered individually by an officer." autocomplete="bday" class="full" />
                    <x-field name="occupation" label="Profession / trade" required autocomplete="organization-title" />
                    <x-field name="ijebu_town" label="Town of origin" required hint="For example Ijebu Ode, Ijebu Igbo or Ago Iwoye." />
                </div>
            </fieldset>
            <fieldset>
                <legend>How we can reach you</legend>
                <div class="form-grid">
                    <x-field name="phone" label="Mobile number" type="tel" required autocomplete="tel" hint="Include the country code if outside Nigeria." />
                    <x-field name="email" label="Email address" type="email" required autocomplete="email" />
                    <x-field name="city" label="City of residence" required autocomplete="address-level2" />
                    <x-field name="country" label="Country of residence" value="Nigeria" required autocomplete="country-name" />
                    <x-field name="address" label="Current address" autocomplete="street-address" class="full" />
                </div>
            </fieldset>
            <fieldset>
                <legend>More about you (optional)</legend>
                <x-select name="marital_status" label="Marital status" :options="$maritalStatuses" placeholder="Choose (optional)" />
                <x-textarea name="profile_summary" label="Profile summary" hint="A high-level summary: professional, personal or educational highlights." rows="4" />
            </fieldset>
            <fieldset>
                <legend>Supporting documents</legend>
                <p class="small">PDF, JPEG or PNG, each smaller than {{ $maxMb }} MB. A clear phone photo of a certificate is fine. Documents are stored privately and seen only by the officers who review applications.@unless ($requireDocuments) <strong>Documents are optional at the moment.</strong>@endunless</p>
                @foreach (\App\Models\ApplicationAttachment::KINDS as $kind => [$label, $isRequired, $multiple, $imagesOnly])
                    @php
                        $inputName = $multiple ? "attachments[{$kind}][]" : "attachments[{$kind}]";
                        $id = 'f-attachments-'.$kind;
                        $error = $errors->first("attachments.{$kind}") ?: $errors->first("attachments.{$kind}.*");
                        $mandatory = $isRequired && $requireDocuments;
                        $hint = $multiple ? 'You can choose up to '.\App\Models\ApplicationAttachment::MAX_MULTIPLE.' files.' : null;
                    @endphp
                    <div class="field {{ $error ? 'has-error' : '' }}">
                        <label for="{{ $id }}">{{ $label }} @if ($mandatory)<span class="req" aria-hidden="true">*</span>@endif</label>
                        @if ($hint)<span class="hint" id="{{ $id }}-hint">{{ $hint }}</span>@endif
                        <input id="{{ $id }}" name="{{ $inputName }}" type="file" accept="{{ $imagesOnly ? 'image/jpeg,image/png' : 'application/pdf,image/jpeg,image/png' }}" @if ($multiple) multiple @endif @if ($mandatory) required aria-required="true" @endif @if ($error) aria-invalid="true" @endif @if ($hint || $error) aria-describedby="{{ trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : '')) }}" @endif>
                        @if ($error)<span class="error-text" id="{{ $id }}-error">{{ $error }}</span>@endif
                    </div>
                @endforeach
                @if ($errors->any())<p class="small"><strong>If the form shows an error, choose your documents again before resubmitting:</strong> browsers do not keep selected files after a page reload.</p>@endif
            </fieldset>
            <fieldset>
                <legend>Declaration and data privacy</legend>
                <x-check name="declaration" label="I declare that I am an Ijebu man, that the information I have given is true, and I understand that applying does not guarantee membership." />
                <div class="notice-box">
                    <p><strong>Data privacy notice</strong></p>
                    <p>We are collecting your personal data to ensure Egbe Bobagunwa has the necessary data to provide you with optimal service and experience. Data collected will never be shared with any third party without your consent.</p>
                    <p>By providing your information, you consent to your data being processed, stored and used by Egbe Bobagunwa Akile Ijebu. The full <a href="{{ route('privacy') }}" target="_blank" rel="noopener">privacy notice</a> (opens in a new tab) explains how your details and documents are used and kept.</p>
                </div>
                <x-check name="privacy" label="I have read the data privacy notice and consent to my data being processed, stored and used by Egbe Bobagunwa Akile Ijebu." />
            </fieldset>
            <button type="submit" class="btn">Submit application <span class="arrow" aria-hidden="true">→</span></button>
        </form>
        @endif
    </div>
</section>
@endsection
