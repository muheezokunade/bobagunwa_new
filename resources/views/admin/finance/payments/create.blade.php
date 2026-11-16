@extends('layouts.app', ['area' => 'admin', 'title' => 'Record a payment'])
@section('content')
<x-page-head title="Record a payment" subtitle="Record money received. Another officer verifies it against the bank statement or cash book before it counts." />
<form method="post" action="{{ route('admin.finance.payments.store') }}" enctype="multipart/form-data" class="panel">
    @csrf
    <x-idem />
    <div class="form-grid">
        <x-select name="member_id" label="Member" required placeholder="Choose the member" class="full" :value="$selected" :options="$members->mapWithKeys(fn ($m) => [$m->id => $m->membership_number.' · '.$m->full_name.($m->status !== 'active' ? ' ('.$m->status.')' : '')])->all()" />
        <x-field name="amount" label="Amount received (₦)" required hint="For example 20000 or 20,000.00" />
        <x-field name="received_on" label="Date received" type="date" required :value="now(config('boba.display_timezone'))->toDateString()" />
        <x-select name="method" label="Method" required :options="['bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'pos' => 'POS / card terminal', 'other' => 'Other']" />
        <x-field name="external_reference" label="Bank or receipt reference" hint="From the bank statement or receipt book. Each reference can be recorded once." />
        <x-field name="payer_name" label="Name on the payment (if different)" />
        <x-field name="narrative" label="Note" />
        <x-field name="evidence" label="Evidence (optional)" type="file" accept=".pdf,.jpg,.jpeg,.png" hint="Teller, transfer receipt or cash receipt. PDF, JPEG or PNG." class="full" />
    </div>
    <x-check name="confirm_possible_duplicate" label="This is a separate payment, even if the same amount was received from this member on the same day." />
    <button class="btn" type="submit">Record payment</button>
</form>
@endsection
