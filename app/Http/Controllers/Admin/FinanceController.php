<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberObligation;
use App\Models\Payment;
use App\Services\Finance\LedgerQuery;
use App\Services\Finance\PaymentService;
use App\Support\Audit;
use App\Support\Csv;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function __construct(private LedgerQuery $ledger) {}

    public function index(): View
    {
        return view('admin.finance.index', [
            'totals' => $this->ledger->organisationTotals(),
            'pending' => Payment::with('member:id,membership_number,full_name')->where('status', 'pending')->oldest()->limit(10)->get(),
            'exceptions' => array_filter($this->ledger->exceptions()),
        ]);
    }

    public function member(Request $request, Member $member): View
    {
        [$from, $to] = $this->period($request);

        return view('admin.finance.member', [
            'member' => $member,
            'summary' => $this->ledger->memberSummary($member->id),
            'obligations' => $this->ledger->openObligations($member->id),
            'payments' => $this->ledger->memberLedgerDetail($member->id),
            'statement' => $this->ledger->statement($member->id, $from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function statementCsv(Request $request, Member $member): StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $statement = $this->ledger->statement($member->id, $from, $to);
        Audit::record('statement.exported', "Statement exported for {$member->membership_number}", $member);
        $rows = [['', 'Opening position', '', '', '', Money::decimal($statement['opening'])]];
        foreach ($statement['lines'] as $line) {
            $rows[] = [$line['date'], $line['type'], $line['reference'], Money::decimal($line['debit']), Money::decimal($line['credit']), Money::decimal($line['balance'])];
        }
        $rows[] = ['', 'Closing position', '', '', '', Money::decimal($statement['closing'])];

        return Csv::download("statement-{$member->membership_number}.csv", ['Date', 'Type', 'Reference', 'Charges (NGN)', 'Payments (NGN)', 'Position (NGN)'], $rows);
    }

    public function adjust(Request $request, MemberObligation $obligation, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:waiver,correction,cancellation,increase'],
            'amount' => ['nullable', 'string', 'max:20'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'effective_on' => ['required', 'date_format:Y-m-d'],
            'idempotency_key' => ['required', 'string'],
        ]);
        [, $replayed] = $payments->adjust($obligation, $data['kind'], (string) ($data['amount'] ?? ''), $data['reason'], $data['effective_on'], $request->user(), $data['idempotency_key']);

        return redirect()->route('admin.finance.members.show', $obligation->member_id)->with('status', $replayed ? 'This adjustment was already recorded.' : 'Adjustment recorded.');
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function period(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to']) : CarbonImmutable::now(config('boba.display_timezone'))->startOfDay();

        return [isset($data['from']) ? CarbonImmutable::parse($data['from']) : $to->subYear()->addDay(), $to];
    }
}
