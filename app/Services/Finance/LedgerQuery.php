<?php

namespace App\Services\Finance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read model for balances, statements and treasury reports.
 *
 * Accounting definitions (docs/06-financial-rules.md):
 *   assessed             = Σ posted obligations
 *   adjustments          = Σ signed obligation adjustments
 *   effective obligations= assessed + adjustments
 *   allocated            = Σ active allocations
 *   outstanding          = effective obligations − allocated          (never negative)
 *   verified payments    = Σ payments with status "verified" (reversed payments excluded)
 *   unallocated credit   = verified payments − allocated              (never negative)
 *   net position         = outstanding − unallocated credit
 *                        = effective obligations − verified payments
 *   pending              = Σ payments awaiting verification (not counted anywhere above)
 *
 * Member statements, member summaries and treasury reports all use these
 * same queries, and a dedicated test reconciles them against raw SQL totals.
 */
class LedgerQuery
{
    private const MEMBER_TOTALS = <<<'SQL'
SELECT m.id AS member_id, m.membership_number, m.full_name, m.status,
  COALESCE(o.assessed, 0) AS assessed,
  COALESCE(adj.adjusted, 0) AS adjustments,
  COALESCE(al.allocated, 0) AS allocated,
  COALESCE(p.verified, 0) AS verified,
  COALESCE(p.pending, 0) AS pending
FROM members m
LEFT JOIN (SELECT member_id, SUM(amount_kobo) AS assessed FROM member_obligations GROUP BY member_id) o ON o.member_id = m.id
LEFT JOIN (SELECT member_id, SUM(amount_kobo) AS adjusted FROM obligation_adjustments GROUP BY member_id) adj ON adj.member_id = m.id
LEFT JOIN (SELECT member_id, SUM(amount_kobo) AS allocated FROM payment_allocations WHERE status = 'active' GROUP BY member_id) al ON al.member_id = m.id
LEFT JOIN (SELECT member_id, SUM(IF(status = 'verified', amount_kobo, 0)) AS verified, SUM(IF(status = 'pending', amount_kobo, 0)) AS pending FROM payments GROUP BY member_id) p ON p.member_id = m.id
SQL;

    /** @return array<string, int> */
    public function memberSummary(int $memberId): array
    {
        $row = DB::selectOne(self::MEMBER_TOTALS.' WHERE m.id = ?', [$memberId]);

        return $this->derive($row);
    }

    /**
     * Per-member balances for reports. Bounded by member count (≤ a few
     * thousand) and executed as one grouped query.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function memberBalances(?string $search = null, string $filter = 'all'): Collection
    {
        $sql = self::MEMBER_TOTALS;
        $bindings = [];
        if ($search) {
            $sql .= ' WHERE (m.membership_number LIKE ? OR m.full_name LIKE ?)';
            $bindings = ["%{$search}%", "%{$search}%"];
        }
        $sql .= ' ORDER BY m.membership_number';

        return collect(DB::select($sql, $bindings))
            ->map(fn ($row) => ['member_id' => $row->member_id, 'membership_number' => $row->membership_number, 'full_name' => $row->full_name, 'status' => $row->status] + $this->derive($row))
            ->when($filter === 'outstanding', fn ($c) => $c->where('outstanding', '>', 0))
            ->when($filter === 'credit', fn ($c) => $c->where('unallocated_credit', '>', 0))
            ->values();
    }

    /** @return array<string, int> */
    public function organisationTotals(): array
    {
        $row = DB::selectOne('SELECT
            (SELECT COALESCE(SUM(amount_kobo), 0) FROM member_obligations) AS assessed,
            (SELECT COALESCE(SUM(amount_kobo), 0) FROM obligation_adjustments) AS adjustments,
            (SELECT COALESCE(SUM(amount_kobo), 0) FROM payment_allocations WHERE status = \'active\') AS allocated,
            (SELECT COALESCE(SUM(amount_kobo), 0) FROM payments WHERE status = \'verified\') AS verified,
            (SELECT COALESCE(SUM(amount_kobo), 0) FROM payments WHERE status = \'pending\') AS pending');

        return $this->derive($row) + [
            'pending_count' => (int) DB::table('payments')->where('status', 'pending')->count(),
            'reversed_total' => (int) DB::table('financial_reversals')->where('kind', 'payment')->sum('amount_kobo'),
        ];
    }

    /** @return Collection<int, object> */
    public function openObligations(int $memberId): Collection
    {
        return collect(DB::select('SELECT o.id, o.effective_on, o.amount_kobo, a.reference, a.title, a.due_on, c.name AS category,
              COALESCE((SELECT SUM(x.amount_kobo) FROM obligation_adjustments x WHERE x.member_obligation_id = o.id), 0) AS adjustments,
              COALESCE((SELECT SUM(p.amount_kobo) FROM payment_allocations p WHERE p.member_obligation_id = o.id AND p.status = \'active\'), 0) AS allocated
            FROM member_obligations o
            JOIN assessments a ON a.id = o.assessment_id
            JOIN fee_categories c ON c.id = a.fee_category_id
            WHERE o.member_id = ?
            ORDER BY o.effective_on, o.id', [$memberId]))
            ->map(function ($o) {
                $o->effective = (int) $o->amount_kobo + (int) $o->adjustments;
                $o->outstanding = $o->effective - (int) $o->allocated;

                return $o;
            });
    }

    /**
     * Member statement for a period. Lines are dated by their accounting date:
     * obligations and adjustments by effective date, verified payments by the
     * date received, payment reversals by the date recorded. Pending and
     * rejected payments do not appear in the running position.
     *
     * @return array{opening: int, closing: int, lines: list<array<string, mixed>>, totals: array<string, int>, as_at: array<string, int>, pending: Collection}
     */
    public function statement(int $memberId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $events = collect();
        foreach (DB::select('SELECT o.id, o.effective_on AS d, o.amount_kobo, a.reference, a.title, c.name AS category FROM member_obligations o JOIN assessments a ON a.id = o.assessment_id JOIN fee_categories c ON c.id = a.fee_category_id WHERE o.member_id = ?', [$memberId]) as $r) {
            $events->push(['date' => $r->d, 'order' => 1, 'type' => 'Assessment', 'reference' => $r->reference, 'description' => "{$r->title} ({$r->category})", 'debit' => (int) $r->amount_kobo, 'credit' => 0]);
        }
        foreach (DB::select('SELECT reference, effective_on AS d, amount_kobo, kind, reason FROM obligation_adjustments WHERE member_id = ?', [$memberId]) as $r) {
            $amount = (int) $r->amount_kobo;
            $events->push(['date' => $r->d, 'order' => 2, 'type' => 'Adjustment ('.$r->kind.')', 'reference' => $r->reference, 'description' => $r->reason, 'debit' => max($amount, 0), 'credit' => max(-$amount, 0)]);
        }
        foreach (DB::select("SELECT reference, received_on AS d, amount_kobo, method, external_reference FROM payments WHERE member_id = ? AND status IN ('verified', 'reversed')", [$memberId]) as $r) {
            $events->push(['date' => $r->d, 'order' => 3, 'type' => 'Payment received', 'reference' => $r->reference, 'description' => str_replace('_', ' ', $r->method).($r->external_reference ? " · {$r->external_reference}" : ''), 'debit' => 0, 'credit' => (int) $r->amount_kobo]);
        }
        foreach (DB::select("SELECT r.reference, DATE(r.created_at) AS d, r.amount_kobo, r.reason, p.reference AS payment_reference FROM financial_reversals r JOIN payments p ON p.id = r.payment_id WHERE p.member_id = ? AND r.kind = 'payment'", [$memberId]) as $r) {
            $events->push(['date' => $r->d, 'order' => 4, 'type' => 'Payment reversed', 'reference' => $r->reference, 'description' => "Reversal of {$r->payment_reference}: {$r->reason}", 'debit' => (int) $r->amount_kobo, 'credit' => 0]);
        }
        $events = $events->sortBy([['date', 'asc'], ['order', 'asc'], ['reference', 'asc']])->values();

        $fromS = $from->toDateString();
        $toS = $to->toDateString();
        $opening = $events->filter(fn ($e) => $e['date'] < $fromS)->sum(fn ($e) => $e['debit'] - $e['credit']);
        $running = $opening;
        $lines = [];
        foreach ($events->filter(fn ($e) => $e['date'] >= $fromS && $e['date'] <= $toS) as $e) {
            $running += $e['debit'] - $e['credit'];
            $lines[] = $e + ['balance' => $running];
        }

        $endOfDay = $to->endOfDay()->utc()->toDateTimeString();
        $asAt = DB::selectOne('SELECT
            (SELECT COALESCE(SUM(amount_kobo), 0) FROM member_obligations WHERE member_id = ? AND effective_on <= ?)
              + (SELECT COALESCE(SUM(amount_kobo), 0) FROM obligation_adjustments WHERE member_id = ? AND effective_on <= ?) AS effective,
            (SELECT COALESCE(SUM(a.amount_kobo), 0) FROM payment_allocations a LEFT JOIN financial_reversals r ON r.id = a.reversal_id
               WHERE a.member_id = ? AND a.created_at <= ? AND (a.reversal_id IS NULL OR r.created_at > ?)) AS allocated', [$memberId, $toS, $memberId, $toS, $memberId, $endOfDay, $endOfDay]);
        $closing = $running;
        $outstanding = (int) $asAt->effective - (int) $asAt->allocated;

        return [
            'opening' => (int) $opening,
            'closing' => (int) $closing,
            'lines' => $lines,
            'totals' => [
                'debits' => (int) collect($lines)->sum('debit'),
                'credits' => (int) collect($lines)->sum('credit'),
            ],
            'as_at' => [
                'outstanding' => $outstanding,
                'unallocated_credit' => $outstanding - (int) $closing,
            ],
            'pending' => collect(DB::select("SELECT reference, received_on, amount_kobo FROM payments WHERE member_id = ? AND status = 'pending' ORDER BY received_on", [$memberId])),
        ];
    }

    /** @return Collection<int, object> */
    public function memberLedgerDetail(int $memberId): Collection
    {
        return collect(DB::select("SELECT p.id, p.reference, p.received_on, p.amount_kobo, p.status, p.method, p.external_reference,
              COALESCE((SELECT SUM(a.amount_kobo) FROM payment_allocations a WHERE a.payment_id = p.id AND a.status = 'active'), 0) AS allocated
            FROM payments p WHERE p.member_id = ? ORDER BY p.received_on DESC, p.id DESC", [$memberId]));
    }

    /**
     * Period report by category. Assessed by obligation effective date;
     * collected by verified payment received date.
     *
     * @return array<string, mixed>
     */
    public function periodReport(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $f = $from->toDateString();
        $t = $to->toDateString();
        $byCategory = collect(DB::select('SELECT c.code, c.name, c.kind,
              COUNT(o.id) AS obligations,
              COALESCE(SUM(o.amount_kobo), 0) AS assessed,
              COALESCE(SUM((SELECT COALESCE(SUM(x.amount_kobo), 0) FROM obligation_adjustments x WHERE x.member_obligation_id = o.id)), 0) AS adjustments,
              COALESCE(SUM((SELECT COALESCE(SUM(p.amount_kobo), 0) FROM payment_allocations p WHERE p.member_obligation_id = o.id AND p.status = \'active\')), 0) AS allocated
            FROM fee_categories c
            JOIN assessments a ON a.fee_category_id = c.id
            JOIN member_obligations o ON o.assessment_id = a.id
            WHERE o.effective_on BETWEEN ? AND ?
            GROUP BY c.id, c.code, c.name, c.kind ORDER BY c.name', [$f, $t]))
            ->map(function ($r) {
                $r->effective = (int) $r->assessed + (int) $r->adjustments;
                $r->outstanding = $r->effective - (int) $r->allocated;
                $r->collection_rate = $r->effective > 0 ? round((int) $r->allocated * 100 / $r->effective, 1) : null;

                return $r;
            });

        $collections = DB::selectOne("SELECT COUNT(*) AS n, COALESCE(SUM(amount_kobo), 0) AS total FROM payments WHERE status IN ('verified', 'reversed') AND received_on BETWEEN ? AND ?", [$f, $t]);
        $reversed = DB::selectOne("SELECT COUNT(*) AS n, COALESCE(SUM(r.amount_kobo), 0) AS total FROM financial_reversals r WHERE r.kind = 'payment' AND DATE(r.created_at) BETWEEN ? AND ?", [$f, $t]);
        $byMethod = DB::select("SELECT method, COUNT(*) AS n, SUM(amount_kobo) AS total FROM payments WHERE status IN ('verified', 'reversed') AND received_on BETWEEN ? AND ? GROUP BY method ORDER BY method", [$f, $t]);
        $adjustments = DB::select('SELECT kind, COUNT(*) AS n, SUM(amount_kobo) AS total FROM obligation_adjustments WHERE effective_on BETWEEN ? AND ? GROUP BY kind ORDER BY kind', [$f, $t]);

        return [
            'by_category' => $byCategory,
            'collections' => ['count' => (int) $collections->n, 'total' => (int) $collections->total],
            'reversals' => ['count' => (int) $reversed->n, 'total' => (int) $reversed->total],
            'by_method' => $byMethod,
            'adjustments' => $adjustments,
        ];
    }

    /**
     * Detective control: every query must return zero rows. Used by reports,
     * the scheduled `boba:ledger:verify` command and post-restore checks.
     *
     * @return array<string, list<object>>
     */
    public function exceptions(): array
    {
        return [
            'Payments allocated beyond their amount' => DB::select("SELECT p.reference, p.amount_kobo, SUM(a.amount_kobo) AS allocated FROM payments p JOIN payment_allocations a ON a.payment_id = p.id AND a.status = 'active' GROUP BY p.id, p.reference, p.amount_kobo HAVING SUM(a.amount_kobo) > p.amount_kobo"),
            'Obligations allocated beyond their effective amount' => DB::select("SELECT o.id, o.amount_kobo, (SELECT COALESCE(SUM(x.amount_kobo), 0) FROM obligation_adjustments x WHERE x.member_obligation_id = o.id) AS adjustments, SUM(a.amount_kobo) AS allocated FROM member_obligations o JOIN payment_allocations a ON a.member_obligation_id = o.id AND a.status = 'active' GROUP BY o.id, o.amount_kobo HAVING SUM(a.amount_kobo) > o.amount_kobo + adjustments"),
            'Obligations with negative effective amount' => DB::select('SELECT o.id FROM member_obligations o JOIN obligation_adjustments x ON x.member_obligation_id = o.id GROUP BY o.id, o.amount_kobo HAVING o.amount_kobo + SUM(x.amount_kobo) < 0'),
            'Active allocations on unverified payments' => DB::select("SELECT a.id, p.reference, p.status FROM payment_allocations a JOIN payments p ON p.id = a.payment_id WHERE a.status = 'active' AND p.status <> 'verified'"),
            'Cross-member allocations' => DB::select('SELECT a.id FROM payment_allocations a JOIN payments p ON p.id = a.payment_id JOIN member_obligations o ON o.id = a.member_obligation_id WHERE p.member_id <> a.member_id OR o.member_id <> a.member_id'),
            'Reversed payments without a reversal record' => DB::select("SELECT p.reference FROM payments p WHERE p.status = 'reversed' AND NOT EXISTS (SELECT 1 FROM financial_reversals r WHERE r.payment_id = p.id AND r.kind = 'payment')"),
            'Reversed allocations without a reversal record' => DB::select("SELECT a.id FROM payment_allocations a WHERE a.status = 'reversed' AND NOT EXISTS (SELECT 1 FROM financial_reversals r WHERE r.id = a.reversal_id AND r.kind = 'allocation' AND r.payment_allocation_id = a.id)"),
            'Posted assessment totals that differ from their obligations' => DB::select("SELECT a.reference, a.total_kobo, COALESCE(SUM(o.amount_kobo), 0) AS obligations FROM assessments a LEFT JOIN member_obligations o ON o.assessment_id = a.id WHERE a.status = 'posted' GROUP BY a.id, a.reference, a.total_kobo HAVING a.total_kobo <> COALESCE(SUM(o.amount_kobo), 0)"),
            'Self-verified payments where a second officer was required' => DB::select('SELECT reference FROM payments WHERE requires_distinct_verifier = 1 AND verified_by = recorded_by'),
        ];
    }

    /** @return array<string, int> */
    private function derive(?object $row): array
    {
        $assessed = (int) ($row->assessed ?? 0);
        $adjustments = (int) ($row->adjustments ?? 0);
        $allocated = (int) ($row->allocated ?? 0);
        $verified = (int) ($row->verified ?? 0);
        $effective = $assessed + $adjustments;

        return [
            'assessed' => $assessed,
            'adjustments' => $adjustments,
            'effective_obligations' => $effective,
            'allocated' => $allocated,
            'outstanding' => $effective - $allocated,
            'verified_payments' => $verified,
            'unallocated_credit' => $verified - $allocated,
            'net_position' => $effective - $verified,
            'pending' => (int) ($row->pending ?? 0),
        ];
    }
}
