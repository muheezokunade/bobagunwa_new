@props(['prefix' => '', 'name', 'label', 'value' => null, 'required' => false, 'hint' => null, 'rows' => 6, 'class' => ''])
@php
    $id = 'f-'.$prefix.str_replace(['[', ']', '.'], '-', $name);
    $error = $errors->first($name);
    $described = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp
<div class="field {{ $class }} {{ $error ? 'has-error' : '' }}">
    <label for="{{ $id }}">{{ $label }} @if ($required)<span class="req" aria-hidden="true">*</span>@endif</label>
    @if ($hint)<span class="hint" id="{{ $id }}-hint">{{ $hint }}</span>@endif
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if ($required) required aria-required="true" @endif @if ($error) aria-invalid="true" @endif @if ($described) aria-describedby="{{ $described }}" @endif {{ $attributes }}>{{ old($name, $value) }}</textarea>
    @if ($error)<span class="error-text" id="{{ $id }}-error">{{ $error }}</span>@endif
</div>
