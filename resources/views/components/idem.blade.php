{{-- One key per rendered form: replays return the original result instead of duplicating. --}}
<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', \App\Support\Idempotency::newKey()) }}">
