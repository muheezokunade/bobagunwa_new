@extends('layouts.app', ['area' => 'portal', 'title' => 'Report a payment'])
@section('content')
<x-page-head title="Report a payment" subtitle="Tell the treasury about a payment you have made and upload the receipt. It counts towards your balance once a payment verifier confirms it against the bank alert.">
    <a class="btn secondary" href="{{ route('portal.finances') }}">Back to dues and payments</a>
</x-page-head>
@include('partials.flash')
<form class="panel narrow-form" method="post" action="{{ route('portal.payments.store') }}" enctype="multipart/form-data" data-confirm="Send this payment for verification?">
    @csrf
    <x-idem />
    @if ($obligations->isNotEmpty())
        <x-select name="obligation_id" label="What is this payment for?" :value="$selected?->id"
            :options="$obligations->mapWithKeys(fn ($o) => [$o->id => $o->title.' — outstanding '.\App\Support\Money::format((int) $o->outstanding)])->all()"
            placeholder="General payment (the treasurer will apply it)" hint="Choose the due or levy. You can pay part of it; the rest stays outstanding." />
    @endif
    <x-field name="amount" label="Amount paid (₦)" required inputmode="decimal" :value="$selected ? number_format(((int) $selected->outstanding) / 100, 2, '.', '') : null" hint="You can pay less than the full amount; the balance remains outstanding." />
    <x-field name="received_on" label="Date you paid" type="date" required :value="now()->toDateString()" />
    <x-select name="method" label="How you paid" :options="['bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'pos' => 'POS / card', 'other' => 'Other']" value="bank_transfer" required />
    <x-field name="external_reference" label="Bank or receipt reference (optional)" hint="The reference on your transfer receipt, if there is one." />
    <x-field name="payer_name" label="Name on the paying account" :value="$member->full_name" hint="Change this if the money came from someone else’s account, so the verifier can match the bank alert." />
    <div class="field {{ $errors->has('receipt') ? 'has-error' : '' }}">
        <label for="f-receipt">Receipt or screenshot of the transfer <span class="req" aria-hidden="true">*</span></label>
        <span class="hint" id="f-receipt-hint">PDF, JPEG or PNG, smaller than {{ intdiv((int) config('boba.uploads.application_file_max_kb'), 1024) }} MB. Stored privately; only the treasury sees it.</span>
        <input id="f-receipt" name="receipt" type="file" accept="application/pdf,image/jpeg,image/png" required aria-required="true" aria-describedby="f-receipt-hint @error('receipt') f-receipt-error @enderror" @error('receipt') aria-invalid="true" @enderror>
        @error('receipt')<span class="error-text" id="f-receipt-error">{{ $message }}</span>@enderror
    </div>
    <x-check name="confirm" label="I confirm these details are correct and the receipt is genuine." />
    <button type="submit" class="btn">Send for verification</button>
</form>
@endsection
