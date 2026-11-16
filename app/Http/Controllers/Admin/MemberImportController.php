<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Services\MemberImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MemberImportController extends Controller
{
    public function __construct(private MemberImportService $imports) {}

    public function index(): View
    {
        return view('admin.imports.members', ['batches' => ImportBatch::with('creator', 'committer')->where('kind', 'members')->latest()->paginate(20)]);
    }

    public function template(): Response
    {
        return response("\xEF\xBB\xBF".$this->imports->template(), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="member-import-template.csv"']);
    }

    public function preview(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:'.config('boba.uploads.import_max_kb'), 'mimes:csv,txt']]);
        $batch = $this->imports->preview($request->file('file'), $request->user());

        return redirect()->route('admin.imports.members.show', $batch);
    }

    public function show(ImportBatch $batch): View
    {
        abort_unless($batch->kind === 'members', 404);

        return view('admin.imports.batch', ['batch' => $batch->load('creator', 'committer'), 'rows' => $batch->rows()->paginate(100), 'commitRoute' => 'admin.imports.members.commit', 'cancelRoute' => 'admin.imports.members.cancel', 'backRoute' => 'admin.imports.members.index']);
    }

    public function commit(Request $request, ImportBatch $batch): RedirectResponse
    {
        abort_unless($batch->kind === 'members', 404);
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Confirm that the source list has been approved for import.']);
        $result = $this->imports->commit($batch, $request->user());

        return redirect()->route('admin.imports.members.show', $batch)->with('status', $result['replayed'] ? 'This import was already completed; no records were changed.' : "Import completed: {$result['batch']->row_count} members added. No login accounts were created.");
    }

    public function cancel(Request $request, ImportBatch $batch): RedirectResponse
    {
        abort_unless($batch->kind === 'members', 404);
        $this->imports->cancel($batch, $request->user());

        return redirect()->route('admin.imports.members.index')->with('status', 'Preview cancelled. No records were changed.');
    }
}
