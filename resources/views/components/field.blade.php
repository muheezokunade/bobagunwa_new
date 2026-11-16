{{-- Accessible input: label, hint and error are programmatically associated. --}}
@props(['prefix' => '', 'name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null, 'autocomplete' => null, 'class' => ''])
@php
    $id = 'f-'.$prefix.str_replace(['[', ']', '.'], '-', $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $error = $errors->first($key);
    $described = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp
<div class="field {{ $class }} {{ $error ? 'has-error' : '' }}">
    <label for="{{ $id }}">{{ $label }} @if ($required)<span class="req" aria-hidden="true">*</span>@endif</label>
    @if ($hint)<span class="hint" id="{{ $id }}-hint">{{ $hint }}</span>@endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
        @if ($type !== 'password' && $type !== 'file') value="{{ old($key, $value) }}" @endif
        @if ($required) required aria-required="true" @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($error) aria-invalid="true" @endif
        @if ($described) aria-describedby="{{ $described }}" @endif
        {{ $attributes }}>
    @if ($error)<span class="error-text" id="{{ $id }}-error">{{ $error }}</span>@endif
</div>
