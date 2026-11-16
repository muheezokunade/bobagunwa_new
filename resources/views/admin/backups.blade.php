@extends('layouts.app', ['area' => 'admin', 'title' => 'Backups'])
@section('content')
@php $F = \App\Support\Format::class; @endphp
<x-page-head title="Encrypted backups" subtitle="Database backups that only the association’s off-site custodian can decrypt." />

<div class="grid-2">
    <section class="panel">
        <h2>How backups work</h2>
        <ul>
            <li>The server holds only the custodian’s <strong>public</strong> key. It can create backups but cannot decrypt them.</li>
            <li>Each backup is encrypted with a fresh file key using XChaCha20-Poly1305 (libsodium secretstream). The file key is sealed to the custodian’s public key with a libsodium sealed box.</li>
            <li>After creating a backup, download the file and transfer it to the approved off-site custodian. Record the SHA-256 fingerprint so the custodian can confirm the file arrived intact.</li>
            <li>Restores use the custodian’s key pair and run into an empty, isolated database with <code>php artisan boba:backup:restore</code>, never into the live database. The restore checks row counts, integrity triggers, orphaned records and finance totals against the backup, and the result is listed below as a restore verification.</li>
        </ul>
    </section>
    <section class="panel">
        <h2>Configuration</h2>
        <dl class="details">
            <dt>Custodian public key</dt><dd>@if ($configured)<x-status value="active" /> Configured @else<x-status value="failed" /> Not configured @endif</dd>
            <dt>Storage folder</dt><dd><code class="small">{{ $path }}</code></dd>
        </dl>
        <form method="post" action="{{ route('admin.backups.store') }}" data-confirm="Create an encrypted backup now? This may take a moment.">
            @csrf
            <button class="btn" type="submit" @disabled(! $configured)>Create encrypted backup</button>
        </form>
        @unless ($configured)
            <p class="small">Backups cannot be created until the custodian’s public key is set in the server configuration (<code>BOBA_BACKUP_PUBLIC_KEY</code>). Ask the technical administrator to configure it.</p>
        @endunless
    </section>
</div>

<h2>Backup and restore history</h2>
@if ($runs->isEmpty())
    <x-empty title="No backups yet"><p>Created backups and restore verifications will appear here.</p></x-empty>
@else
<div class="table-wrap" tabindex="0" role="region" aria-label="Table (scrolls sideways on small screens)"><table>
    <thead><tr><th scope="col">Time</th><th scope="col">Type</th><th scope="col">Result</th><th scope="col">File</th><th scope="col" class="num">Size</th><th scope="col">SHA-256</th><th scope="col">Details</th><th scope="col">Created by</th><th scope="col"><span class="sr-only">Download</span></th></tr></thead>
    <tbody>@foreach ($runs as $run)
        @php $d = is_array($run->details) ? $run->details : []; $fin = is_array($d['finance'] ?? null) ? $d['finance'] : []; @endphp
        <tr>
            <td>{{ $F::dateTime($run->created_at) }}</td>
            <td>{{ $run->kind === 'backup' ? 'Backup' : 'Restore verification' }}</td>
            <td><x-status :value="$run->status" /></td>
            <td>{{ $run->file_name ?: '—' }}</td>
            <td class="num">{{ $run->size_bytes ? number_format($run->size_bytes / 1048576, 2).' MB' : '—' }}</td>
            <td>@if ($run->sha256)<code class="small" title="{{ $run->sha256 }}">{{ substr($run->sha256, 0, 12) }}…</code>@else — @endif</td>
            <td class="small">
                @if ($run->error)<span class="error-text">{{ $run->error }}</span><br>@endif
                @if ($run->kind === 'restore_verification' && array_key_exists('ok', $d))
                    {{ $d['ok'] ? 'All checks passed.' : 'Problems found:' }}
                    @if (! empty($d['problems']) && is_array($d['problems']))<ul>@foreach ($d['problems'] as $problem)<li>{{ $problem }}</li>@endforeach</ul>@endif
                @endif
                @isset($d['rows']){{ number_format((int) $d['rows']) }} rows @endisset
                @isset($d['tables']) in {{ $d['tables'] }} tables @endisset
                @isset($d['triggers'])· {{ $d['triggers'] }} integrity triggers @endisset
                @if (isset($fin['verified_payments']) || isset($fin['outstanding']))
                    <br>Verified payments <x-money :kobo="$fin['verified_payments'] ?? 0" /> · outstanding <x-money :kobo="$fin['outstanding'] ?? 0" />
                @endif
            </td>
            <td>{{ $run->creator?->name ?? ($run->kind === 'restore_verification' ? 'Command line' : '—') }}</td>
            <td>@if ($run->kind === 'backup' && $run->status === 'succeeded' && $run->file_name && is_file(rtrim(config('boba.backup.path'), '/').'/'.basename($run->file_name)))<a class="text-link" href="{{ route('admin.backups.download', $run) }}">Download <span class="sr-only">{{ $run->file_name }}</span></a>@endif</td>
        </tr>
    @endforeach</tbody>
</table></div>
{{ $runs->links() }}
@endif
@endsection
