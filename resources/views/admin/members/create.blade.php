@extends('layouts.app', ['area' => 'admin', 'title' => 'Add a member'])
@section('content')
<x-page-head title="Add an existing member" subtitle="For one verified member at a time. To add many, use Import members. New applicants should apply through the website." />
<form method="post" action="{{ route('admin.members.store') }}" class="panel">
    @csrf
    <x-idem />
    <div class="form-grid">
        <x-field name="full_name" label="Full name" required class="full" />
        <x-field name="preferred_name" label="Preferred name" />
        <x-field name="membership_number" label="Existing membership number (if any)" hint="Leave blank to issue the next number. Numbers are permanent." />
        <x-field name="email" label="Email address" type="email" hint="Needed later to issue an activation link." />
        <x-field name="phone" label="Phone number" type="tel" />
        <x-date-parts name="date_of_birth" label="Date of birth" />
        <x-field name="joined_on" label="Date joined" type="date" />
        <x-field name="ijebu_town" label="Ijebu town of origin" />
        <x-field name="city" label="City of residence" />
        <x-field name="occupation" label="Occupation" />
        <x-field name="address" label="Address" />
        <x-textarea name="admin_notes" label="Officer notes (not visible to the member)" rows="3" class="full" />
        <x-field name="reason" label="Source of these details" required class="full" hint="For example “Verified against the 2015 register by the Secretary”." />
    </div>
    <fieldset>
        <legend>If another member has the same name</legend>
        <p class="small">The system refuses a name that matches an existing member unless you confirm the person is different.</p>
        <x-check name="confirm_distinct_person" label="I have verified this is a different person from the existing member(s) with the same name." />
        <x-textarea name="distinguishing_note" label="How you verified they are different" rows="2" hint="For example different date of birth, town and phone confirmed with the Secretary." />
    </fieldset>
    <button class="btn" type="submit">Add member</button>
</form>
@endsection
