@extends('layouts.public', ['title' => 'Application received', 'noindex' => true])
@section('content')
<x-public-hero title="Application" em="received" crumb="Application received" />
<section class="section">
    <div class="narrow">
        <div class="alert success" role="status">Thank you. Your application has been received and will be reviewed by the association’s officers.</div>
        <section class="secret-box" aria-labelledby="keep-title">
            <h2 id="keep-title">Keep these details safe</h2>
            <p>You need <strong>both</strong> to check your application. The tracking code is shown only on this page and cannot be recovered, so write it down or save it now.</p>
            <dl class="details">
                <dt>Application reference</dt><dd><code id="ref">{{ $receipt['reference'] }}</code></dd>
                <dt>Private tracking code</dt><dd><code id="code">{{ $receipt['token'] }}</code> <button type="button" class="btn small secondary" data-copy="code" hidden>Copy code</button></dd>
            </dl>
        </section>
        @if ($receipt['flagged'])
            <p>Your application will be considered individually by the officers before a decision is made.</p>
        @endif
        <p>What happens next: an officer will review your application. If more information is needed, you will see the request when you <a href="{{ route('membership.status') }}">check your application</a>, and the secretariat may also contact you directly.</p>
        <p class="no-print"><a class="btn secondary" href="{{ route('membership.status') }}">Check application status</a></p>
    </div>
</section>
@endsection
