@extends('layouts.app', ['area' => 'admin', 'title' => 'Dues and levy categories'])
@section('content')
<x-page-head title="Dues and levy categories" subtitle="Categories label assessments. Amounts are set on each assessment." />
@php $canManage = auth()->user()->hasPermission('finance.categories.manage'); @endphp
@if ($categories->isEmpty())
    <x-empty title="No categories yet"><p>Create a category such as “Annual dues” before creating an assessment. Official amounts require the association’s approval.</p></x-empty>
@else
<div class="table-wrap" tabindex="0" role="region" aria-label="Table (scrolls sideways on small screens)"><table>
    <thead><tr><th scope="col">Code</th><th scope="col">Name</th><th scope="col">Kind</th><th scope="col" class="num">Suggested amount</th><th scope="col">Status</th>@if ($canManage)<th scope="col">Edit</th>@endif</tr></thead>
    <tbody>@foreach ($categories as $category)
        <tr>
            <td>{{ $category->code }}</td><td>{{ $category->name }}<br><span class="muted small">{{ $category->description }}</span></td><td>{{ ucfirst($category->kind) }}</td>
            <td class="num">{{ $category->default_amount_kobo ? \App\Support\Money::format($category->default_amount_kobo) : '—' }}</td>
            <td><x-status :value="$category->is_active ? 'active' : 'disabled'" /></td>
            @if ($canManage)
            <td>
                <details><summary>Edit <span class="sr-only">{{ $category->name }}</span></summary>
                    <form method="post" action="{{ route('admin.finance.categories.update', $category) }}">
                        @csrf
                        <input type="hidden" name="version" value="{{ $category->version }}">
                        <x-field :prefix="'c'.$category->id.'-'" name="code" label="Code" :value="$category->code" required />
                        <x-field :prefix="'c'.$category->id.'-'" name="name" label="Name" :value="$category->name" required />
                        <x-select :prefix="'c'.$category->id.'-'" name="kind" label="Kind" :value="$category->kind" :options="['dues' => 'Dues', 'levy' => 'Levy', 'other' => 'Other']" />
                        <x-field :prefix="'c'.$category->id.'-'" name="description" label="Description" :value="$category->description" />
                        <x-field :prefix="'c'.$category->id.'-'" name="default_amount" label="Suggested amount (₦)" :value="$category->default_amount_kobo ? \App\Support\Money::decimal($category->default_amount_kobo) : ''" />
                        <x-check :prefix="'c'.$category->id.'-'" name="is_active" label="Active (available for new assessments)" :checked="$category->is_active" />
                        <button class="btn small" type="submit">Save</button>
                    </form>
                </details>
            </td>
            @endif
        </tr>
    @endforeach</tbody>
</table></div>
@endif
@if ($canManage)
<form class="panel" method="post" action="{{ route('admin.finance.categories.store') }}">
    @csrf
    <h2>New category</h2>
    <div class="form-grid">
        <x-field name="code" label="Code" required hint="Short code, e.g. DUES or LEVY-HALL" />
        <x-field name="name" label="Name" required />
        <x-select name="kind" label="Kind" required :options="['dues' => 'Dues', 'levy' => 'Levy', 'other' => 'Other']" />
        <x-field name="default_amount" label="Suggested amount (₦, optional)" />
        <x-field name="description" label="Description" class="full" />
    </div>
    <button class="btn" type="submit">Create category</button>
</form>
@endif
@endsection
