<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeeCategory;
use App\Models\ImportBatch;
use App\Services\Finance\FinanceImportService;
use App\Services\MemberImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class FinanceImportController extends Controller
{
    public function __construct(private FinanceImportService $imports)
    {
    }

    public function index(): View
    {
        return view('admin.finance.imports', [
            'batches' => ImportBatch::with('creator', 'committer')->whereIn('kind', ['finance_transactions', 'opening_balances'])->latest()->paginate(20),
            'categories' => FeeCategory::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function template(string $kind): Response
    {
        abort_unless(in_array($kind, ['finance_transactions', 'opening_balances'], true), 404);

        return response("\xEF\xBB\xBF".$this->imports->template($kind), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$kind}-template.csv\""]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:finance_transactions,opening_balances'],
            'file' => ['required', 'file', 'max:'.config('boba.uploads.import_max_kb'), 'mimes:csv,txt'],
            'source_approval_reference' => ['required', 'string', 'max:190'],
            'cutoff_date' => ['nullable', 'date_format:Y-m-d'],
            'fee_category_id' => ['nullable', 'integer'],
        ]);
        $batch = $this->imports->preview($data['kind'], $request->file('file'), $data, $request->user());

        return redirect()->route('admin.finance.imports.show', $batch);
    }

    public function show(ImportBatch $batch): View
    {
        abort_unless(in_array($batch->kind, ['finance_transactions', 'opening_balances'], true), 404);

        return view('admin.imports.batch', ['batch' => $batch->load('creator', 'committer'), 'rows' => $batch->rows()->paginate(100), 'commitRoute' => 'admin.finance.imports.commit', 'cancelRoute' => 'admin.finance.imports.cancel', 'backRoute' => 'admin.finance.imports.index']);
    }

    public function commit(Request $request, ImportBatch $batch): RedirectResponse
    {
        abort_unless(in_array($batch->kind, ['finance_transactions', 'opening_balances'], true), 404);
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Confirm that the source records are approved and the totals have been reconciled.']);
        $result = $this->imports->commit($batch, $request->user());

        return redirect()->route('admin.finance.imports.show', $batch)->with('status', $result['replayed'] ? 'This import was already completed; nothing was duplicated.' : 'Financial records imported.');
    }

    public function cancel(Request $request, ImportBatch $batch, MemberImportService $members): RedirectResponse
    {
        abort_unless(in_array($batch->kind, ['finance_transactions', 'opening_balances'], true), 404);
        $members->cancel($batch, $request->user());

        return redirect()->route('admin.finance.imports.index')->with('status', 'Preview cancelled.');
    }
}
