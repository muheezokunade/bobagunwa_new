@if (session('status'))
    <div class="alert success" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert error" role="alert" tabindex="-1">
        @if ($errors->has('domain') && $errors->count() === 1)
            {{ $errors->first('domain') }}
        @else
            <strong>Please correct the following:</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif
    </div>
@endif
@if (session('one_time_link'))
    @php $otl = session('one_time_link'); @endphp
    <section class="secret-box" aria-labelledby="otl-title">
        <h2 id="otl-title">{{ $otl['label'] }}</h2>
        <p>This private link is shown <strong>once</strong>. It can be used one time and expires {{ $otl['expires'] }}. Send it only through the association’s approved private channel to the verified person, then record the delivery.</p>
        <code id="otl-url">{{ $otl['url'] }}</code>
        <div class="actions">
            <button type="button" class="btn small secondary" data-copy="otl-url" hidden>Copy link</button>
            @if (! empty($otl['message_id']))
                <a class="btn small" href="{{ route('admin.messages.show', $otl['message_id']) }}">Record delivery</a>
            @endif
        </div>
    </section>
@endif
