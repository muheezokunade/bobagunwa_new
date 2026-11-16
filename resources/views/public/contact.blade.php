@extends('layouts.public', ['title' => 'Contact', 'description' => 'Contact the secretariat of Ẹgbẹ́ Bobagunwa Okunrin Akile Ijebu.'])
@section('content')
<x-public-hero title="Contact the" em="secretariat" crumb="Contact" />
<section class="section">
    <div class="wrap split">
        <div>
            <p class="kicker">Secretariat</p>
            <h2>Let’s begin a conversation.</h2>
            <p>For membership, events, partnerships or general enquiries, send a message and the secretariat will reply.</p>
            @if ($org['publish_contact'])
                <dl class="details">
                    @if ($org['email'])<dt>Email</dt><dd><a href="mailto:{{ $org['email'] }}">{{ $org['email'] }}</a></dd>@endif
                    @if ($org['phone'])<dt>Telephone</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $org['phone']) }}">{{ $org['phone'] }}</a></dd>@endif
                    @if ($org['address'])<dt>Address</dt><dd>{{ $org['address'] }}</dd>@endif
                </dl>
            @endif
            <p class="small muted">Looking for your membership application? <a href="{{ route('membership.status') }}">Track it here</a>.</p>
        </div>
        <div>
            @if (session('enquiry_reference'))
                <div class="alert success" role="status">Thank you. Your message has been received. Your reference is <strong>{{ session('enquiry_reference') }}</strong>.</div>
            @endif
            @include('partials.flash')
            <form method="post" action="{{ route('contact.store') }}" class="panel" novalidate>
                @csrf
                <input type="hidden" name="form_stamp" value="{{ $stamp }}">
                <div class="hp-field" aria-hidden="true"><label for="website">Leave this field empty</label><input id="website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
                <div class="form-grid">
                    <x-field name="name" label="Your name" required autocomplete="name" />
                    <x-field name="email" label="Email address" type="email" required autocomplete="email" />
                    <x-field name="phone" label="Phone (optional)" type="tel" autocomplete="tel" />
                    <x-select name="topic" label="Topic" required :options="['general' => 'General enquiry', 'membership' => 'Membership', 'events' => 'Events', 'partnership' => 'Partnership']" />
                    <x-field name="subject" label="Subject" required class="full" />
                    <x-textarea name="message" label="Message" required class="full" />
                </div>
                <x-check name="consent" label="The secretariat may use these details to reply to my message." />
                <p class="small muted">See the <a href="{{ route('privacy') }}">privacy notice</a>.</p>
                <button type="submit" class="btn">Send message</button>
            </form>
        </div>
    </div>
</section>
@endsection
