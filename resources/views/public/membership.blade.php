@extends('layouts.public', ['title' => 'Membership', 'description' => 'Eligibility and the application process for membership of Ẹgbẹ́ Bobagunwa Okunrin Akile Ijebu.'])
@section('content')
<x-public-hero title="Membership" lead="Who may join, how to apply, and what happens after you apply." />
<section class="section">
    <div class="wrap split wide-left">
        <div>
            <p class="kicker">Eligibility</p>
            <h2>Who may apply</h2>
            <p class="lead">Membership is for Ijebu men born between <strong>1 January 1980</strong> and <strong>31 December 1982</strong>, inclusive.</p>
            <p>If your date of birth falls outside this range you may still apply. Your application will be passed to the officers for individual consideration; it is neither automatically accepted nor automatically refused.</p>

            <h2>How it works</h2>
            <ol class="rule-list">
                <li><span class="num">01</span><div><strong>Apply online</strong>No account is needed. You will receive an application reference and a private tracking code.</div></li>
                <li><span class="num">02</span><div><strong>Review by officers</strong>An authorised membership officer reviews every application and may ask you for more information.</div></li>
                <li><span class="num">03</span><div><strong>Decision</strong>If approved, you receive a permanent membership number.</div></li>
                <li><span class="num">04</span><div><strong>Activate your member account</strong>You receive a private, one-time link to set your password and use the member portal.</div></li>
            </ol>
            <div class="actions">
                <a class="btn" href="{{ route('membership.apply') }}">Apply for membership <span class="arrow" aria-hidden="true">→</span></a>
                <a class="btn secondary" href="{{ route('membership.status') }}">Track an application</a>
            </div>
            @if ($page)
                <h2>{{ $page->title }}</h2>
                <div class="prose">{{ \App\Support\Content::markdown($page->body) }}</div>
            @endif
        </div>
        <aside class="panel">
            <h3>Already a member?</h3>
            <p>Members recorded before the website launched do not need to apply. The secretariat will confirm your details and send you a private link to activate your account.</p>
            <p><a class="text-link" href="{{ route('login') }}">Member sign in</a></p>
            <p><a class="text-link" href="{{ route('contact') }}">Contact the secretariat</a></p>
        </aside>
    </div>
</section>
@endsection
