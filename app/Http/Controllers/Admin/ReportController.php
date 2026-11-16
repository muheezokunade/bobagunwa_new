<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialReversal;
use App\Models\ObligationAdjustment;
use App\Models\Payment;
use App\Services\Finance\LedgerQuery;
use App\Support\Audit;
use App\Support\Csv;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private LedgerQuery $ledger) {}

    public function index(Request $request): View
    {
        [$from, $to] = $this->period($request);
        $balances = $this->ledger->memberBalances($request->query('q'), $request->query('filter', 'all'));

        return view('admin.finance.reports', [
            'from' => $from, 'to' => $to,
            'totals' => $this->ledger->organisationTotals(),
            'period' => $this->ledger->periodReport($from, $to),
            'balances' => $balances,
            'pending' => Payment::with('member:id,membership_number,full_name')->where('status', 'pending')->oldest('received_on')->get(),
            'corrections' => [
                'adjustments' => ObligationAdjustment::with('creator:id,name', 'obligation.member:id,membership_number')->whereBetween('effective_on', [$from->toDateString(), $to->toDateString()])->latest('id')->limit(100)->get(),
                'reversals' => FinancialReversal::with('creator:id,name', 'payment.member:id,membership_number')->whereBetween('created_at', [$from->startOfDay()->utc(), $to->endOfDay()->utc()])->latest('id')->limit(100)->get(),
            ],
            'exceptions' => $this->ledger->exceptions(),
            'filter' => $request->query('filter', 'all'),
        ]);
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        [$from, $to] = $this->period($request);
        Audit::record('report.exported', "Financial report exported: {$report}", null, ['from' => $from->toDateString(), 'to' => $to->toDateString()]);

        return match ($report) {
            'balances' => Csv::download('member-balances-'.now()->format('Ymd').'.csv',
                ['Membership number', 'Name', 'Status', 'Assessed', 'Adjustments', 'Allocated', 'Outstanding', 'Verified payments', 'Unallocated credit', 'Net position', 'Pending verification'],
                $this->ledger->memberBalances()->map(fn ($b) => [$b['membership_number'], $b['full_name'], $b['status'], Money::decimal($b['assessed']), Money::decimal($b['adjustments']), Money::decimal($b['allocated']), Money::decimal($b['outstanding']), Money::decimal($b['verified_payments']), Money::decimal($b['unallocated_credit']), Money::decimal($b['net_position']), Money::decimal($b['pending'])])),
            'collections' => Csv::download("collections-{$from->toDateString()}-{$to->toDateString()}.csv",
                ['Reference', 'Membership number', 'Received', 'Method', 'Bank/receipt reference', 'Amount', 'Status', 'Verified by'],
                Payment::with('member:id,membership_number', 'verifier:id,name')->whereIn('status', ['verified', 'reversed'])->whereBetween('received_on', [$from->toDateString(), $to->toDateString()])->orderBy('received_on')->cursor()
                    ->map(fn ($p) => [$p->reference, $p->member->membership_number, $p->received_on->toDateString(), $p->method, $p->external_reference, Money::decimal($p->amount_kobo), $p->status, $p->verifier?->name])),
            'categories' => Csv::download("category-performance-{$from->toDateString()}-{$to->toDateString()}.csv",
                ['Code', 'Category', 'Kind', 'Obligations', 'Assessed', 'Adjustments', 'Effective', 'Allocated', 'Outstanding', 'Collection rate %'],
                $this->ledger->periodReport($from, $to)['by_category']->map(fn ($r) => [$r->code, $r->name, $r->kind, $r->obligations, Money::decimal((int) $r->assessed), Money::decimal((int) $r->adjustments), Money::decimal($r->effective), Money::decimal((int) $r->allocated), Money::decimal($r->outstanding), $r->collection_rate])),
            default => abort(404),
        };
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function period(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to']) : CarbonImmutable::now(config('boba.display_timezone'))->startOfDay();

        return [isset($data['from']) ? CarbonImmutable::parse($data['from']) : $to->startOfYear(), $to];
    }
}
