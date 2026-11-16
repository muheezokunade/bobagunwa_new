<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Services\BackupService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(): View
    {
        return view('admin.backups', [
            'runs' => BackupRun::with('creator:id,name')->latest('id')->paginate(30),
            'configured' => (string) config('boba.backup.public_key') !== '',
            'path' => config('boba.backup.path'),
        ]);
    }

    public function store(Request $request, BackupService $backups): RedirectResponse
    {
        $run = $backups->create($request->user()->id);
        Audit::record('backup.created', "Encrypted backup created: {$run->file_name}", $run);

        return redirect()->route('admin.backups.index')->with('status', 'Encrypted backup created. Download it and transfer it to the approved off-site custodian.');
    }

    /** Only the encrypted file is downloadable; it is unreadable without the custodian's key. */
    public function download(BackupRun $run): BinaryFileResponse
    {
        abort_unless($run->kind === 'backup' && $run->status === 'succeeded' && $run->file_name, 404);
        $path = rtrim(config('boba.backup.path'), '/').'/'.basename($run->file_name);
        abort_unless(is_file($path), 404, 'This backup file is no longer on the server.');
        Audit::record('backup.downloaded', "Encrypted backup downloaded: {$run->file_name}", $run);

        return response()->download($path, $run->file_name, ['Content-Type' => 'application/octet-stream']);
    }
}
