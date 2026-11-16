<?php

namespace Tests\Feature\Finance;

use App\Exceptions\DomainRuleException;
use App\Models\MemberObligation;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\Finance\AssessmentService;
use App\Services\Finance\LedgerQuery;
use App\Services\Finance\PaymentService;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $payments;

    private LedgerQuery $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payments = app(PaymentService::class);
        $this->ledger = app(LedgerQuery::class);
    }

    public function test_assessment_posts_exactly_once_and_replays_idempotently(): void
    {
        $members = [$this->member(), $this->member(), $this->member()];
        $suspended = $this->member(['status' => 'suspended']);
        $treasurer = $this->officer('treasurer');
        $service = app(AssessmentService::class);
        $draft = $service->createDraft(['fee_category_id' => $this->category()->id, 'title' => 'Synthetic', 'amount' => '50,000', 'effective_on' => now()->toDateString(), 'target_mode' => 'all_active'], $treasurer);
        $preview = $service->preview($draft);
        $this->assertCount(3, $preview['targets']);
        $this->assertTrue($preview['excluded']->contains('id', $suspended->id));
        $this->assertSame(15_000_000, $preview['total_kobo']);

        $key = $this->key();
        [$posted, $replayed] = $service->post($draft, $treasurer, $key, 3);
        $this->assertFalse($replayed);
        [$again, $replayed] = $service->post($draft, $treasurer, $key, 3);
        $this->assertTrue($replayed);
        $this->assertSame($posted->id, $again->id);
        $this->assertSame(3, MemberObligation::where('assessment_id', $posted->id)->count());
        $this->assertSame(15_000_000, (int) MemberObligation::where('assessment_id', $posted->id)->sum('amount_kobo'));

        try {
            $service->post($draft, $treasurer, $this->key(), 3);
            $this->fail('A second posting with a new key must be refused');
        } catch (DomainRuleException $e) {
            $this->assertStringContainsString('already been posted', $e->getMessage());
        }
        $this->assertSame(3, MemberObligation::where('assessment_id', $posted->id)->count());
    }

    public function test_posting_refuses_when_member_list_changed_since_preview(): void
    {
        $this->member();
        $treasurer = $this->officer('treasurer');
        $service = app(AssessmentService::class);
        $draft = $service->createDraft(['fee_category_id' => $this->category()->id, 'title' => 'Synthetic', 'amount' => '100', 'effective_on' => now()->toDateString(), 'target_mode' => 'all_active'], $treasurer);
        $this->member();
        $this->expectException(DomainRuleException::class);
        $service->post($draft, $treasurer, $this->key(), 1);
    }

    public function test_recorded_payment_counts_only_after_independent_verification(): void
    {
        $m = $this->member();
        $treasurer = $this->officer('treasurer');
        $both = $this->officer(['treasurer', 'payment_verifier']);
        [$payment] = $this->payments->record(['member_id' => $m->id, 'amount' => '20000', 'method' => 'bank_transfer', 'received_on' => now()->toDateString(), 'external_reference' => 'trf-001'], $both, $this->key());
        $this->assertSame('pending', $payment->status);
        $this->assertSame(0, $this->ledger->memberSummary($m->id)['verified_payments']);
        $this->assertSame(2_000_000, $this->ledger->memberSummary($m->id)['pending']);

        try {
            $this->payments->verify($payment, $treasurer, null);
            $this->fail('Treasurer lacks verify permission');
        } catch (DomainRuleException) {
        }
        try {
            $this->payments->verify($payment, $both, null);
            $this->fail('Recorder cannot verify own payment');
        } catch (DomainRuleException $e) {
            $this->assertStringContainsString('different officer', $e->getMessage());
        }
        $this->payments->verify($payment, $this->officer('payment_verifier'), 'Checked statement');
        $this->assertSame(2_000_000, $this->ledger->memberSummary($m->id)['verified_payments']);
        $this->assertSame(2_000_000, $this->ledger->memberSummary($m->id)['unallocated_credit']);
    }

    public function test_single_verifier_policy_is_configurable_but_snapshotted_per_payment(): void
    {
        $m = $this->member();
        $both = $this->officer(['treasurer', 'payment_verifier']);
        Settings::set('finance.require_distinct_verifier', false, null);
        [$payment] = $this->payments->record(['member_id' => $m->id, 'amount' => '100', 'method' => 'cash', 'received_on' => now()->toDateString()], $both, $this->key());
        Settings::set('finance.require_distinct_verifier', true, null);
        $this->assertSame('verified', $this->payments->verify($payment, $both, null)->status);
    }

    public function test_duplicate_payment_protection(): void
    {
        $m = $this->member();
        $t = $this->officer('treasurer');
        $data = ['member_id' => $m->id, 'amount' => '20000', 'method' => 'bank_transfer', 'received_on' => now()->toDateString(), 'external_reference' => 'REF-1'];
        $key = $this->key();
        [$first] = $this->payments->record($data, $t, $key);
        [$replay, $replayed] = $this->payments->record($data, $t, $key);
        $this->assertTrue($replayed);
        $this->assertSame($first->id, $replay->id);
        try {
            $this->payments->record($data, $t, $this->key());
            $this->fail('Same bank reference must be refused');
        } catch (DomainRuleException $e) {
            $this->assertStringContainsString('already recorded', $e->getMessage());
        }
        try {
            $this->payments->record(['external_reference' => null] + $data, $t, $this->key());
            $this->fail('Same member/amount/date requires confirmation');
        } catch (DomainRuleException $e) {
            $this->assertStringContainsString('same member, amount and date', $e->getMessage());
        }
        [$second] = $this->payments->record(['external_reference' => null, 'confirm_possible_duplicate' => true] + $data, $t, $this->key());
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, Payment::where('member_id', $m->id)->count());
    }

    public function test_partial_split_and_multi_payment_allocation_reconcile(): void
    {
        $m = $this->member();
        $a1 = $this->assess([$m], '50000');
        $a2 = $this->assess([$m], '10000');
        $o1 = $this->obligationOf($m, $a1);
        $o2 = $this->obligationOf($m, $a2);
        $t = $this->officer('treasurer');
        $p1 = $this->verifiedPayment($m, '20000');
        $p2 = $this->verifiedPayment($m, '45000');

        // One payment across two obligations.
        $this->payments->allocate($p2, [$o1->id => 3_500_000, $o2->id => 1_000_000], $t, $this->key());
        // A second payment against the same obligation.
        $this->payments->allocate($p1, [$o1->id => 1_500_000], $t, $this->key());

        $s = $this->ledger->memberSummary($m->id);
        $this->assertSame(6_000_000, $s['effective_obligations']);
        $this->assertSame(6_000_000, $s['allocated']);
        $this->assertSame(0, $s['outstanding']);
        $this->assertSame(6_500_000, $s['verified_payments']);
        $this->assertSame(500_000, $s['unallocated_credit']);
        $this->assertSame(-500_000, $s['net_position']);

        try {
            $this->payments->allocate($p1, [$o1->id => 1], $t, $this->key());
            $this->fail('Obligation is fully paid');
        } catch (DomainRuleException $e) {
            $this->assertStringContainsString('outstanding', $e->getMessage());
        }
    }

    public function test_allocation_rejections_and_replay(): void
    {
        [$m, $other] = [$this->member(), $this->member()];
        $a = $this->assess([$m, $other], '50000');
        $o = $this->obligationOf($m, $a);
        $oOther = $this->obligationOf($other, $a);
        $t = $this->officer('treasurer');
        $p = $this->verifiedPayment($m, '20000');

        foreach ([
            [[$o->id => 2_000_001], 'exceed the unallocated amount'],
            [[$oOther->id => 100], 'same member'],
            [[$o->id => -100], 'positive'],
            [[], 'at least one amount'],
        ] as [$amounts, $message]) {
            try {
                $this->payments->allocate($p, $amounts, $t, $this->key());
                $this->fail('Expected refusal: '.$message);
            } catch (DomainRuleException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
        $key = $this->key();
        $this->payments->allocate($p, [$o->id => 2_000_000], $t, $key);
        [, $replayed] = $this->payments->allocate($p, [$o->id => 2_000_000], $t, $key);
        $this->assertTrue($replayed);
        $this->assertSame(1, PaymentAllocation::where('payment_id', $p->id)->count());

        [$pending] = $this->payments->record(['member_id' => $m->id, 'amount' => '100', 'method' => 'cash', 'received_on' => now()->toDateString(), 'confirm_possible_duplicate' => true], $t, $this->key());
        $this->expectException(DomainRuleException::class);
        $this->payments->allocate($pending, [$o->id => 100], $t, $this->key());
    }

    public function test_payment_reversal_reverses_dependent_allocations_consistently(): void
    {
        $m = $this->member();
        $a = $this->assess([$m], '50000');
        $o = $this->obligationOf($m, $a);
        $t = $this->officer('treasurer');
        $verifier = $this->officer('payment_verifier');
        $p = $this->verifiedPayment($m, '20000');
        $this->payments->allocate($p, [$o->id => 2_000_000], $t, $this->key());
        $this->assertSame(3_000_000, $this->ledger->memberSummary($m->id)['outstanding']);

        $key = $this->key();
        $this->payments->reversePayment($p, 'Bounced transfer (synthetic)', $verifier, $key);
        [, $replayed] = $this->payments->reversePayment($p, 'Bounced transfer (synthetic)', $verifier, $key);
        $this->assertTrue($replayed);

        $s = $this->ledger->memberSummary($m->id);
        $this->assertSame(5_000_000, $s['outstanding']);
        $this->assertSame(0, $s['verified_payments']);
        $this->assertSame(0, $s['unallocated_credit']);
        $this->assertSame('reversed', $p->fresh()->status);
        $this->assertSame(0, PaymentAllocation::where('payment_id', $p->id)->where('status', 'active')->count());
        $this->assertSame(2, DB::table('financial_reversals')->where('payment_id', $p->id)->count());

        try {
            $this->payments->reversePayment($p->fresh(), 'Again', $verifier, $this->key());
            $this->fail('Second reversal must be refused');
        } catch (DomainRuleException $e) {
            $this->assertStringContainsString('already been reversed', $e->getMessage());
        }
        $this->assertEmpty(array_filter($this->ledger->exceptions()));
    }

    public function test_allocation_reversal_frees_funds_for_reallocation(): void
    {
        $m = $this->member();
        $a1 = $this->assess([$m], '20000');
        $a2 = $this->assess([$m], '20000');
        $t = $this->officer('treasurer');
        $p = $this->verifiedPayment($m, '20000');
        $this->payments->allocate($p, [$this->obligationOf($m, $a1)->id => 2_000_000], $t, $this->key());
        $allocation = PaymentAllocation::where('payment_id', $p->id)->first();
        $this->payments->reverseAllocation($allocation, 'Applied to the wrong levy (synthetic)', $this->officer('payment_verifier'), $this->key());
        $this->payments->allocate($p, [$this->obligationOf($m, $a2)->id => 2_000_000], $t, $this->key());
        $this->assertSame(2_000_000, $this->ledger->memberSummary($m->id)['outstanding']);
    }

    public function test_adjustments_waive_cancel_and_respect_allocations(): void
    {
        $m = $this->member();
        $a = $this->assess([$m], '50000');
        $o = $this->obligationOf($m, $a);
        $t = $this->officer('treasurer');
        $p = $this->verifiedPayment($m, '20000');
        $this->payments->allocate($p, [$o->id => 2_000_000], $t, $this->key());

        $this->payments->adjust($o, 'waiver', '5000', 'Hardship waiver (synthetic)', now()->toDateString(), $t, $this->key());
        $this->assertSame(2_500_000, $this->ledger->memberSummary($m->id)['outstanding']);
        try {
            $this->payments->adjust($o, 'waiver', '25001', 'Too much', now()->toDateString(), $t, $this->key());
            $this->fail('Cannot waive below allocations');
        } catch (DomainRuleException $e) {
            $this->assertStringContainsString('below allocated', $e->getMessage());
        }
        $this->payments->adjust($o, 'cancellation', '', 'Cancel remainder (synthetic)', now()->toDateString(), $t, $this->key());
        $this->assertSame(0, $this->ledger->memberSummary($m->id)['outstanding']);
        $this->assertSame(2_000_000, $this->ledger->memberSummary($m->id)['effective_obligations']);
    }

    public function test_permissions_are_enforced_inside_finance_services(): void
    {
        $m = $this->member();
        $a = $this->assess([$m], '50000');
        $p = $this->verifiedPayment($m, '20000');
        $o = $this->obligationOf($m, $a);
        $cases = [
            'system admin reverses payment' => fn () => $this->payments->reversePayment($p, 'x', $this->officer('system_admin'), $this->key()),
            'membership officer records payment' => fn () => $this->payments->record(['member_id' => $m->id, 'amount' => '1', 'method' => 'cash', 'received_on' => now()->toDateString()], $this->officer('membership_officer'), $this->key()),
            'membership officer posts assessment' => fn () => app(AssessmentService::class)->createDraft(['fee_category_id' => 1, 'title' => 'x', 'amount' => '1', 'effective_on' => now()->toDateString(), 'target_mode' => 'all_active'], $this->officer('membership_officer')),
            'treasurer reverses' => fn () => $this->payments->reversePayment($p, 'x', $this->officer('treasurer'), $this->key()),
            'verifier allocates' => fn () => $this->payments->allocate($p, [$o->id => 1], $this->officer('payment_verifier'), $this->key()),
            'executive adjusts' => fn () => $this->payments->adjust($o, 'waiver', '1', 'x', now()->toDateString(), $this->officer('executive'), $this->key()),
            'secretariat verifies' => fn () => $this->payments->verify($p, $this->officer('secretariat'), null),
        ];
        foreach ($cases as $label => $attempt) {
            try {
                $attempt();
                $this->fail("Should be denied: {$label}");
            } catch (DomainRuleException $e) {
                $this->assertStringContainsString('permission', $e->getMessage(), $label);
            }
        }
        $this->assertSame('verified', $p->fresh()->status);
    }

    public function test_statement_summary_reports_and_raw_sql_reconcile(): void
    {
        $members = [$this->member(), $this->member(), $this->member()];
        $a = $this->assess($members, '50000');
        $t = $this->officer('treasurer');
        foreach ($members as $i => $m) {
            $p = $this->verifiedPayment($m, (string) (10000 * ($i + 1)));
            $this->payments->allocate($p, [$this->obligationOf($m, $a)->id => 1_000_000 * ($i + 1)], $t, $this->key());
        }
        $this->verifiedPayment($members[0], '7000');

        $raw = DB::selectOne("SELECT (SELECT SUM(amount_kobo) FROM member_obligations) o, (SELECT SUM(amount_kobo) FROM payment_allocations WHERE status='active') a, (SELECT SUM(amount_kobo) FROM payments WHERE status='verified') p");
        $org = $this->ledger->organisationTotals();
        $this->assertSame((int) $raw->o - (int) $raw->a, $org['outstanding']);
        $this->assertSame((int) $raw->p - (int) $raw->a, $org['unallocated_credit']);

        $balances = $this->ledger->memberBalances();
        $this->assertSame($org['outstanding'], $balances->sum('outstanding'));
        $this->assertSame($org['unallocated_credit'], $balances->sum('unallocated_credit'));

        foreach ($members as $m) {
            $summary = $this->ledger->memberSummary($m->id);
            $statement = $this->ledger->statement($m->id, CarbonImmutable::now()->subYear(), CarbonImmutable::now());
            $this->assertSame($summary['net_position'], $statement['closing']);
            $this->assertSame($summary['outstanding'], $statement['as_at']['outstanding']);
            $this->assertSame($summary['unallocated_credit'], $statement['as_at']['unallocated_credit']);
        }
        $period = $this->ledger->periodReport(CarbonImmutable::now()->subYear(), CarbonImmutable::now());
        $this->assertSame(15_000_000, (int) $period['by_category']->sum('assessed'));
        $this->assertSame(6_000_000, (int) $period['by_category']->sum('allocated'));
        $this->assertSame(6_700_000, $period['collections']['total']);
        $this->assertEmpty(array_filter($this->ledger->exceptions()));
    }

    public function test_rejected_payment_releases_its_bank_reference_for_correct_re_entry(): void
    {
        $m = $this->member();
        $t = $this->officer('treasurer');
        $data = ['member_id' => $m->id, 'amount' => '2000', 'method' => 'bank_transfer', 'received_on' => now()->toDateString(), 'external_reference' => 'TRF-REUSE-1'];
        [$wrong] = $this->payments->record($data, $t, $this->key());
        $this->payments->reject($wrong, $this->officer('payment_verifier'), 'Wrong amount entered (synthetic)');
        [$right] = $this->payments->record(['amount' => '20000'] + $data, $t, $this->key());
        $this->assertSame('pending', $right->status);
        $this->expectException(DomainRuleException::class);
        $this->payments->record(['amount' => '200'] + $data, $t, $this->key());
    }
}
