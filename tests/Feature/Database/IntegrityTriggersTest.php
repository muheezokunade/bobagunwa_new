<?php

namespace Tests\Feature\Database;

use App\Support\IntegrityTriggers;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Proves the ledger safeguards hold against direct SQL that bypasses the
 * application entirely.
 */
class IntegrityTriggersTest extends TestCase
{
    use RefreshDatabase;

    private function rejects(string $sql, string $fragment): void
    {
        try {
            DB::statement($sql);
            $this->fail("Expected the database to reject: {$sql}");
        } catch (QueryException $e) {
            $this->assertStringContainsString($fragment, $e->getMessage());
        }
    }

    public function test_every_integrity_trigger_is_installed(): void
    {
        $installed = collect(DB::select('SELECT TRIGGER_NAME AS n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()'))->pluck('n')->all();
        $this->assertEqualsCanonicalizing(IntegrityTriggers::names(), $installed);
        $this->assertSame('11.4', substr(DB::selectOne('SELECT VERSION() AS v')->v, 0, 4), 'Tests must run on MariaDB 11.4');
        $this->assertSame('InnoDB', DB::selectOne("SELECT ENGINE AS e FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'")->e);
    }

    public function test_cross_member_allocation_by_direct_sql_is_rejected(): void
    {
        [$a, $b] = [$this->member(), $this->member()];
        $assessment = $this->assess([$a, $b], '50000');
        $payment = $this->verifiedPayment($a, '20000');
        $obligationB = $this->obligationOf($b, $assessment);
        $officer = $this->officer('treasurer')->id;
        // Claiming member A: obligation belongs to B.
        $this->rejects("INSERT INTO payment_allocations (payment_id, member_obligation_id, member_id, amount_kobo, allocated_by) VALUES ({$payment->id}, {$obligationB->id}, {$a->id}, 100, {$officer})", 'cross-member');
        // Claiming member B: payment belongs to A.
        $this->rejects("INSERT INTO payment_allocations (payment_id, member_obligation_id, member_id, amount_kobo, allocated_by) VALUES ({$payment->id}, {$obligationB->id}, {$b->id}, 100, {$officer})", 'cross-member');
    }

    public function test_over_allocation_and_unverified_allocation_are_rejected(): void
    {
        $m = $this->member();
        $assessment = $this->assess([$m], '50000');
        $obligation = $this->obligationOf($m, $assessment);
        $payment = $this->verifiedPayment($m, '20000');
        $officer = $this->officer('treasurer')->id;
        $insert = fn ($amount, $paymentId = null) => "INSERT INTO payment_allocations (payment_id, member_obligation_id, member_id, amount_kobo, allocated_by) VALUES (".($paymentId ?? $payment->id).", {$obligation->id}, {$m->id}, {$amount}, {$officer})";
        $this->rejects($insert(2_000_001), 'exceeds the available payment');
        DB::statement($insert(1_500_000));
        $this->rejects($insert(500_001), 'exceeds the available payment');
        $this->rejects($insert(0), 'payment_allocations_amount_positive');
        $this->rejects($insert(-5), 'payment_allocations_amount_positive');

        $pending = DB::table('payments')->insertGetId(['reference' => 'P-PENDING', 'member_id' => $m->id, 'amount_kobo' => 9_000_000, 'method' => 'cash', 'received_on' => now()->toDateString(), 'status' => 'pending', 'idempotency_key' => $this->key(), 'recorded_by' => $officer, 'created_at' => now(), 'updated_at' => now()]);
        $this->rejects($insert(100, $pending), 'only verified payments');
        DB::table('payments')->where('id', $pending)->update(['status' => 'verified', 'verified_by' => $this->officer('payment_verifier')->id, 'verified_at' => now()]);
        // Obligation has 3,500,000 remaining; 3,500,001 must fail even though the payment could cover it.
        $this->rejects($insert(3_500_001, $pending), 'exceeds the obligation outstanding');
    }

    public function test_posted_records_are_immutable_and_undeletable(): void
    {
        $m = $this->member();
        $assessment = $this->assess([$m], '50000');
        $payment = $this->verifiedPayment($m, '20000');
        $this->rejects("UPDATE payments SET amount_kobo = 1 WHERE id = {$payment->id}", 'immutable');
        $this->rejects("UPDATE payments SET verified_by = recorded_by WHERE id = {$payment->id}", 'verification history is immutable');
        $this->rejects("DELETE FROM payments WHERE id = {$payment->id}", 'never deleted');
        $this->rejects("UPDATE member_obligations SET amount_kobo = 1", 'immutable');
        $this->rejects("DELETE FROM member_obligations", 'immutable');
        $this->rejects("UPDATE assessments SET amount_kobo = 1 WHERE id = {$assessment->id}", 'immutable');
        $this->rejects("DELETE FROM assessments WHERE id = {$assessment->id}", 'never deleted');
        $this->rejects("UPDATE payments SET status = 'reversed' WHERE id = {$payment->id}", 'reversal record');
        $this->rejects("UPDATE payments SET status = 'pending', verified_by = NULL, verified_at = NULL WHERE id = {$payment->id}", 'invalid payment status transition');
        $this->rejects('DELETE FROM audit_events', 'append-only');
        $this->rejects("UPDATE audit_events SET summary = 'x'", 'append-only');
    }

    public function test_self_verification_is_rejected_when_two_officers_are_required(): void
    {
        $m = $this->member();
        $officer = $this->officer(['treasurer', 'payment_verifier']);
        $id = DB::table('payments')->insertGetId(['reference' => 'P-SELF', 'member_id' => $m->id, 'amount_kobo' => 100, 'method' => 'cash', 'received_on' => now()->toDateString(), 'status' => 'pending', 'requires_distinct_verifier' => 1, 'idempotency_key' => $this->key(), 'recorded_by' => $officer->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->rejects("UPDATE payments SET status = 'verified', verified_by = {$officer->id}, verified_at = NOW() WHERE id = {$id}", 'different officer');
        $this->rejects("INSERT INTO payments (reference, member_id, amount_kobo, method, received_on, status, idempotency_key, recorded_by, verified_by, verified_at) VALUES ('P-DIRECT', {$m->id}, 100, 'cash', CURDATE(), 'verified', 'k-direct-verified-1234', {$officer->id}, {$officer->id}, NOW())", 'recorded as pending');
    }

    public function test_reversal_rules_hold_at_database_level(): void
    {
        $m = $this->member();
        $assessment = $this->assess([$m], '50000');
        $obligation = $this->obligationOf($m, $assessment);
        $payment = $this->verifiedPayment($m, '20000');
        $officer = $this->officer('payment_verifier')->id;
        $allocation = DB::table('payment_allocations')->insertGetId(['payment_id' => $payment->id, 'member_obligation_id' => $obligation->id, 'member_id' => $m->id, 'amount_kobo' => 2_000_000, 'allocated_by' => $officer]);

        $this->rejects("INSERT INTO financial_reversals (reference, kind, payment_id, amount_kobo, reason, created_by) VALUES ('R1', 'payment', {$payment->id}, 2000000, 'x', {$officer})", 'reverse active allocations');
        $this->rejects("UPDATE payment_allocations SET status = 'reversed' WHERE id = {$allocation}", 'reversal record');
        $this->rejects("INSERT INTO financial_reversals (reference, kind, payment_id, payment_allocation_id, amount_kobo, reason, created_by) VALUES ('R2', 'allocation', {$payment->id}, {$allocation}, 5, 'x', {$officer})", 'match the allocation');
        DB::statement("INSERT INTO financial_reversals (reference, kind, payment_id, payment_allocation_id, amount_kobo, reason, created_by) VALUES ('R3', 'allocation', {$payment->id}, {$allocation}, 2000000, 'x', {$officer})");
        $this->assertSame('reversed', DB::table('payment_allocations')->where('id', $allocation)->value('status'));
        $this->rejects("INSERT INTO financial_reversals (reference, kind, payment_id, payment_allocation_id, amount_kobo, reason, created_by) VALUES ('R4', 'allocation', {$payment->id}, {$allocation}, 2000000, 'x', {$officer})", 'only active allocations');
        DB::statement("INSERT INTO financial_reversals (reference, kind, payment_id, amount_kobo, reason, created_by) VALUES ('R5', 'payment', {$payment->id}, 2000000, 'x', {$officer})");
        $this->assertSame('reversed', DB::table('payments')->where('id', $payment->id)->value('status'));
        $this->rejects("INSERT INTO financial_reversals (reference, kind, payment_id, amount_kobo, reason, created_by) VALUES ('R6', 'payment', {$payment->id}, 2000000, 'x', {$officer})", 'only verified, unreversed');
        $this->rejects('DELETE FROM financial_reversals', 'append-only');
    }

    public function test_adjustments_cannot_go_negative_or_below_allocations(): void
    {
        $m = $this->member();
        $assessment = $this->assess([$m], '50000');
        $obligation = $this->obligationOf($m, $assessment);
        $payment = $this->verifiedPayment($m, '20000');
        $officer = $this->officer('treasurer')->id;
        DB::table('payment_allocations')->insert(['payment_id' => $payment->id, 'member_obligation_id' => $obligation->id, 'member_id' => $m->id, 'amount_kobo' => 2_000_000, 'allocated_by' => $officer]);
        $adjust = fn ($amount, $kind, $ref) => "INSERT INTO obligation_adjustments (reference, member_obligation_id, member_id, amount_kobo, kind, reason, effective_on, idempotency_key, created_by) VALUES ('{$ref}', {$obligation->id}, {$m->id}, {$amount}, '{$kind}', 'x', CURDATE(), '{$ref}-key-0000000000', {$officer})";
        $this->rejects($adjust(-3_000_001, 'waiver', 'A1'), 'below allocated');
        $this->rejects($adjust(100, 'waiver', 'A2'), 'sign does not match');
        DB::statement($adjust(-3_000_000, 'waiver', 'A3'));
        $this->rejects($adjust(-1, 'correction', 'A4'), 'below allocated');
        $this->rejects("UPDATE obligation_adjustments SET amount_kobo = 0", 'append-only');
    }

    public function test_membership_numbers_are_never_changed_deleted_or_reused(): void
    {
        $m = $this->member();
        $this->rejects("UPDATE members SET membership_number = 'BOB-999999' WHERE id = {$m->id}", 'cannot be changed');
        $this->rejects("DELETE FROM membership_numbers WHERE number = '{$m->membership_number}'", 'never be deleted');
        $this->rejects("UPDATE membership_numbers SET number = 'X' WHERE number = '{$m->membership_number}'", 'immutable');
        $this->rejects("DELETE FROM members WHERE id = {$m->id}", 'never deleted');
        $this->rejects("INSERT INTO membership_numbers (number, source) VALUES ('{$m->membership_number}', 'provided')", 'Duplicate entry');
        $this->rejects("INSERT INTO members (membership_number, full_name, name_normalized, origin) VALUES ('NOT-ISSUED-1', 'X', 'x', 'manual')", 'foreign key constraint');
    }

    public function test_failed_multi_record_transaction_rolls_back_completely(): void
    {
        $m = $this->member();
        $assessment = $this->assess([$m], '50000');
        $obligation = $this->obligationOf($m, $assessment);
        $payment = $this->verifiedPayment($m, '20000');
        $officer = $this->officer('treasurer')->id;
        $before = DB::table('payment_allocations')->count();
        try {
            DB::transaction(function () use ($payment, $obligation, $m, $officer) {
                DB::table('payment_allocations')->insert(['payment_id' => $payment->id, 'member_obligation_id' => $obligation->id, 'member_id' => $m->id, 'amount_kobo' => 1_000_000, 'allocated_by' => $officer]);
                DB::table('payment_allocations')->insert(['payment_id' => $payment->id, 'member_obligation_id' => $obligation->id, 'member_id' => $m->id, 'amount_kobo' => 1_000_001, 'allocated_by' => $officer]);
            });
            $this->fail('Second insert should have been rejected');
        } catch (QueryException) {
        }
        $this->assertSame($before, DB::table('payment_allocations')->count());
    }
}
