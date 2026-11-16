@props(['prefix' => '', 'name', 'label', 'checked' => false, 'value' => '1'])
@php $id = 'f-'.$prefix.str_replace(['[', ']', '.'], '-', $name).($value !== '1' ? '-'.$value : ''); $error = $errors->first($name); @endphp
<div class="field {{ $error ? 'has-error' : '' }}">
    <label class="check" for="{{ $id }}">
        <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked)) @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif {{ $attributes }}>
        <span>{{ $label }}</span>
    </label>
    @if ($error)<span class="error-text" id="{{ $id }}-error">{{ $error }}</span>@endif
</div>
