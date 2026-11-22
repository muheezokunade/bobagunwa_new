{{-- Date as day / month name / year: unambiguous on every device (no mm/dd vs dd/mm). --}}
@props(['name', 'label', 'value' => null, 'required' => false, 'hint' => null, 'class' => '', 'autocomplete' => null])
@php
    $id = 'f-'.str_replace(['[', ']', '.'], '-', $name);
    $error = $errors->first($name);
    $date = $value instanceof \Carbon\CarbonInterface ? $value : (filled($value) ? \Illuminate\Support\Carbon::parse($value) : null);
    $day = (string) old($name.'_day', $date?->day);
    $month = (string) old($name.'_month', $date?->month);
    $year = (string) old($name.'_year', $date?->year);
    $described = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp
<fieldset class="field date-parts {{ $class }} {{ $error ? 'has-error' : '' }}" @if ($described) aria-describedby="{{ $described }}" @endif>
    <legend>{{ $label }} @if ($required)<span class="req" aria-hidden="true">*</span>@endif</legend>
    @if ($hint)<span class="hint" id="{{ $id }}-hint">{{ $hint }}</span>@endif
    <div class="date-parts-row">
        <div>
            <label for="{{ $id }}-day">Day</label>
            <select id="{{ $id }}-day" name="{{ $name }}_day" @if ($required) required aria-required="true" @endif @if ($error) aria-invalid="true" @endif @if ($autocomplete) autocomplete="{{ $autocomplete }}-day" @endif>
                <option value="">Day</option>
                @for ($d = 1; $d <= 31; $d++)<option value="{{ $d }}" @selected($day === (string) $d)>{{ $d }}</option>@endfor
            </select>
        </div>
        <div>
            <label for="{{ $id }}-month">Month</label>
            <select id="{{ $id }}-month" name="{{ $name }}_month" @if ($required) required aria-required="true" @endif @if ($error) aria-invalid="true" @endif @if ($autocomplete) autocomplete="{{ $autocomplete }}-month" @endif>
                <option value="">Month</option>
                @foreach (\App\Support\DateParts::MONTHS as $m => $monthName)<option value="{{ $m }}" @selected($month === (string) $m)>{{ $monthName }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="{{ $id }}-year">Year</label>
            <input id="{{ $id }}-year" name="{{ $name }}_year" type="text" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" size="5" placeholder="e.g. 1981" value="{{ $year }}" @if ($required) required aria-required="true" @endif @if ($error) aria-invalid="true" @endif @if ($autocomplete) autocomplete="{{ $autocomplete }}-year" @endif>
        </div>
    </div>
    @if ($error)<span class="error-text" id="{{ $id }}-error">{{ $error }}</span>@endif
</fieldset>
