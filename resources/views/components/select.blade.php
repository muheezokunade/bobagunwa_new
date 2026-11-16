@props(['prefix' => '', 'name', 'label', 'options' => [], 'value' => null, 'required' => false, 'hint' => null, 'placeholder' => null, 'class' => ''])
@php
    $id = 'f-'.$prefix.str_replace(['[', ']', '.'], '-', $name);
    $error = $errors->first($name);
    $current = (string) old($name, $value);
    $described = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp
<div class="field {{ $class }} {{ $error ? 'has-error' : '' }}">
    <label for="{{ $id }}">{{ $label }} @if ($required)<span class="req" aria-hidden="true">*</span>@endif</label>
    @if ($hint)<span class="hint" id="{{ $id }}-hint">{{ $hint }}</span>@endif
    <select id="{{ $id }}" name="{{ $name }}" @if ($required) required aria-required="true" @endif @if ($error) aria-invalid="true" @endif @if ($described) aria-describedby="{{ $described }}" @endif {{ $attributes }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($error)<span class="error-text" id="{{ $id }}-error">{{ $error }}</span>@endif
</div>
