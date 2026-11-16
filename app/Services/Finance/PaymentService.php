<?php

namespace App\Services\Finance;

use App\Exceptions\DomainRuleException;
use App\Models\FinancialReversal;
use App\Models\Member;
use App\Models\MemberObligation;
use App\Models\ObligationAdjustment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\Audit;
use App\Support\Idempotency;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Manual payments, verification, allocation, adjustments and reversals.
 *
 * A recorded payment is "pending" and counts for nothing until a permitted
 * officer (by default a different officer) verifies it. Only verified funds
 * can be allocated. Corrections never edit or delete posted records.
 */
class PaymentService
{
    /** @param array<string, mixed> $data */
    public function record(array $data, User $officer, ?string $idempotencyKey, ?int $evidenceFileId = null): array
    {
        Ledger::authorise($officer, 'finance.payments.record', 'record payments');
        $member = Member::findOrFail($data['member_id']);
        $amount = Money::parse($data['amount']);
        $external = isset($data['external_reference']) && trim((string) $data['external_reference']) !== '' ? strtoupper(trim($data['external_reference'])) : null;
        if ($data['received_on'] > now()->toDateString()) {
            throw new DomainRuleException('The date received cannot be in the future.');
        }

        // Duplicate checks run inside the idempotent unit of work, so a replayed
        // submission returns the original record instead of an error.
        return Ledger::guard(fn () => Idempotency::run('payment.record', $idempotencyKey, [$member->id, $amount, $data['received_on'], $external, $data['method']], function () use ($data, $member, $amount, $external, $officer, $evidenceFileId) {
            if ($external && Payment::where('external_reference', $external)->exists()) {
                throw new DomainRuleException("A payment with bank/receipt reference {$external} is already recorded.");
            }
            $possibleDuplicate = Payment::where('member_id', $member->id)->where('amount_kobo', $amount)
                ->where('received_on', $data['received_on'])->whereIn('status', ['pending', 'verified'])->exists();
            if ($possibleDuplicate && empty($data['confirm_possible_duplicate'])) {
                throw new DomainRuleException('A payment with the same member, amount and date is already recorded. Check it, then tick “This is a separate payment” if it is genuinely different.');
            }
            $payment = Payment::create([
                'reference' => Ledger::reference('PAY'),
                'member_id' => $member->id,
                'amount_kobo' => $amount,
                'currency' => 'NGN',
                'method' => $data['method'],
                'received_on' => $data['received_on'],
                'external_reference' => $external,
                'payer_name' => $data['payer_name'] ?? null,
                'narrative' => $data['narrative'] ?? null,
                'origin' => 'native',
                'status' => 'pending',
                'requires_distinct_verifier' => Settings::bool('finance.require_distinct_verifier'),
                'evidence_file_id' => $evidenceFileId,
                'idempotency_key' => Idempotency::newKey(),
                'recorded_by' => $officer->id,
            ]);
            Audit::record('payment.recorded', "Payment {$payment->reference} of ".Money::format($amount)." recorded for {$member->membership_number}", $payment, ['amount_kobo' => $amount, 'method' => $data['method']]);

            return $payment;
        }, Payment::class));
    }

    public function verify(Payment $payment, User $officer, ?string $note): Payment
    {
        Ledger::authorise($officer, 'finance.payments.verify', 'verify payments');

        return Ledger::guard(fn () => DB::transaction(function () use ($payment, $officer, $note) {
            $locked = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($locked->status !== 'pending') {
                throw new DomainRuleException('This payment has already been '.$locked->status.'.');
            }
            if ($locked->requires_distinct_verifier && $locked->recorded_by === $officer->id) {
                throw new DomainRuleException('A different officer must verify a payment you recorded.');
            }
            $locked->update(['status' => 'verified', 'verified_by' => $officer->id, 'verified_at' => now(), 'verification_note' => $note]);
            Audit::record('payment.verified', "Payment {$locked->reference} verified (".Money::format($locked->amount_kobo).')', $locked, ['note' => $note]);

            return $locked;
        }));
    }

    public function reject(Payment $payment, User $officer, string $reason): Payment
    {
        Ledger::authorise($officer, 'finance.payments.verify', 'reject payments');
        if (trim($reason) === '') {
            throw new DomainRuleException('Record why the payment is being rejected.');
        }

        return Ledger::guard(fn () => DB::transaction(function () use ($payment, $officer, $reason) {
            $locked = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($locked->status !== 'pending') {
                throw new DomainRuleException('Only pending payments can be rejected.');
            }
            $locked->update(['status' => 'rejected', 'rejected_by' => $officer->id, 'rejected_at' => now(), 'rejection_reason' => $reason]);
            Audit::record('payment.rejected', "Payment {$locked->reference} rejected", $locked, ['reason' => $reason]);

            return $locked;
        }));
    }

    /**
     * Allocate verified funds to one or more of the same member's obligations.
     *
     * @param  array<int, int>  $amounts  obligation id => kobo
     * @return array{0: Payment, 1: bool}
     */
    public function allocate(Payment $payment, array $amounts, User $officer, ?string $idempotencyKey): array
    {
        Ledger::authorise($officer, 'finance.allocate', 'allocate payments');
        $amounts = array_filter(array_map('intval', $amounts), fn ($v) => $v !== 0);
        if (! $amounts) {
            throw new DomainRuleException('Enter at least one amount to allocate.');
        }
        foreach ($amounts as $kobo) {
            if ($kobo < 0) {
                throw new DomainRuleException('Allocation amounts must be positive.');
            }
        }
        ksort($amounts);

        return Ledger::guard(fn () => Idempotency::run('payment.allocate', $idempotencyKey, ['payment' => $payment->id, 'amounts' => $amounts], function () use ($payment, $amounts, $officer) {
            $locked = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($locked->status !== 'verified') {
                throw new DomainRuleException('Only verified payments can be allocated.');
            }
            $available = $locked->amount_kobo - (int) PaymentAllocation::where('payment_id', $locked->id)->where('status', 'active')->sum('amount_kobo');
            if (array_sum($amounts) > $available) {
                throw new DomainRuleException('The allocations ('.Money::format(array_sum($amounts)).') exceed the unallocated amount of this payment ('.Money::format($available).').');
            }
            // Lock obligations in id order to avoid deadlocks between officers.
            $obligations = MemberObligation::whereIn('id', array_keys($amounts))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $batch = Idempotency::newKey();
            foreach ($amounts as $obligationId => $kobo) {
                $obligation = $obligations->get($obligationId);
                if (! $obligation || $obligation->member_id !== $locked->member_id) {
                    throw new DomainRuleException('Payments can only be allocated to the same member’s obligations.');
                }
                $outstanding = $this->outstanding($obligation->id);
                if ($kobo > $outstanding) {
                    throw new DomainRuleException('An allocation exceeds the outstanding amount of '.Money::format($outstanding).'.');
                }
                PaymentAllocation::create([
                    'payment_id' => $locked->id, 'member_obligation_id' => $obligation->id, 'member_id' => $locked->member_id,
                    'amount_kobo' => $kobo, 'status' => 'active', 'batch_key' => $batch, 'allocated_by' => $officer->id,
                ]);
            }
            Audit::record('payment.allocated', 'Allocated '.Money::format(array_sum($amounts))." from {$locked->reference}", $locked, ['allocations' => $amounts]);

            return $locked->fresh();
        }, Payment::class));
    }

    public function outstanding(int $obligationId): int
    {
        $row = DB::selectOne('SELECT o.amount_kobo
            + COALESCE((SELECT SUM(a.amount_kobo) FROM obligation_adjustments a WHERE a.member_obligation_id = o.id), 0)
            - COALESCE((SELECT SUM(p.amount_kobo) FROM payment_allocations p WHERE p.member_obligation_id = o.id AND p.status = \'active\'), 0) AS outstanding
            FROM member_obligations o WHERE o.id = ?', [$obligationId]);

        return (int) ($row->outstanding ?? 0);
    }

    /** @return array{0: ObligationAdjustment, 1: bool} */
    public function adjust(MemberObligation $obligation, string $kind, string $amountInput, string $reason, string $effectiveOn, User $officer, ?string $idempotencyKey): array
    {
        Ledger::authorise($officer, 'finance.adjust', 'adjust obligations');
        if (trim($reason) === '') {
            throw new DomainRuleException('Record the reason for this adjustment.');
        }
        if (! in_array($kind, ['waiver', 'correction', 'cancellation', 'increase'], true)) {
            throw new DomainRuleException('Unknown adjustment type.');
        }
        if ($kind === 'cancellation') {
            $amount = -$this->outstanding($obligation->id);
            if ($amount === 0) {
                throw new DomainRuleException('Nothing remains outstanding to cancel.');
            }
        } else {
            $amount = Money::parse($amountInput, false, $kind === 'correction');
            if ($kind === 'waiver') {
                $amount = -abs($amount);
            } elseif ($kind === 'increase') {
                $amount = abs($amount);
            }
        }

        return Ledger::guard(fn () => Idempotency::run('obligation.adjust', $idempotencyKey, [$obligation->id, $kind, $amount], function () use ($obligation, $kind, $amount, $reason, $effectiveOn, $officer) {
            $adjustment = ObligationAdjustment::create([
                'reference' => Ledger::reference('ADJ'),
                'member_obligation_id' => $obligation->id, 'member_id' => $obligation->member_id,
                'amount_kobo' => $amount, 'kind' => $kind, 'reason' => trim($reason), 'effective_on' => $effectiveOn,
                'idempotency_key' => Idempotency::newKey(), 'created_by' => $officer->id,
            ]);
            Audit::record('obligation.adjusted', ucfirst($kind).' of '.Money::format($amount)." on obligation #{$obligation->id}", $adjustment, ['amount_kobo' => $amount, 'reason' => $reason]);

            return $adjustment;
        }, ObligationAdjustment::class));
    }

    /** @return array{0: FinancialReversal, 1: bool} */
    public function reverseAllocation(PaymentAllocation $allocation, string $reason, User $officer, ?string $idempotencyKey): array
    {
        Ledger::authorise($officer, 'finance.reverse', 'reverse allocations');
        if (trim($reason) === '') {
            throw new DomainRuleException('Record the reason for this reversal.');
        }

        return Ledger::guard(fn () => Idempotency::run('allocation.reverse', $idempotencyKey, [$allocation->id], function () use ($allocation, $reason, $officer) {
            $locked = PaymentAllocation::lockForUpdate()->findOrFail($allocation->id);
            if ($locked->status !== 'active') {
                throw new DomainRuleException('This allocation has already been reversed.');
            }
            $reversal = FinancialReversal::create([
                'reference' => Ledger::reference('REV'), 'kind' => 'allocation', 'payment_id' => $locked->payment_id,
                'payment_allocation_id' => $locked->id, 'amount_kobo' => $locked->amount_kobo, 'reason' => trim($reason),
                'idempotency_key' => Idempotency::newKey(), 'created_by' => $officer->id,
            ]);
            Audit::record('allocation.reversed', 'Allocation of '.Money::format($locked->amount_kobo).' reversed', $reversal, ['reason' => $reason]);

            return $reversal;
        }, FinancialReversal::class));
    }

    /**
     * Reverse a verified payment. Its active allocations are reversed first, in
     * the same transaction, so balances stay consistent.
     *
     * @return array{0: FinancialReversal, 1: bool}
     */
    public function reversePayment(Payment $payment, string $reason, User $officer, ?string $idempotencyKey): array
    {
        Ledger::authorise($officer, 'finance.reverse', 'reverse payments');
        if (trim($reason) === '') {
            throw new DomainRuleException('Record the reason for this reversal.');
        }

        return Ledger::guard(fn () => Idempotency::run('payment.reverse', $idempotencyKey, [$payment->id], function () use ($payment, $reason, $officer) {
            $locked = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($locked->status !== 'verified') {
                throw new DomainRuleException($locked->status === 'reversed' ? 'This payment has already been reversed.' : 'Only verified payments can be reversed.');
            }
            $active = PaymentAllocation::where('payment_id', $locked->id)->where('status', 'active')->orderBy('id')->lockForUpdate()->get();
            foreach ($active as $allocation) {
                FinancialReversal::create([
                    'reference' => Ledger::reference('REV'), 'kind' => 'allocation', 'payment_id' => $locked->id,
                    'payment_allocation_id' => $allocation->id, 'amount_kobo' => $allocation->amount_kobo,
                    'reason' => 'Payment reversal: '.trim($reason), 'created_by' => $officer->id,
                ]);
            }
            $reversal = FinancialReversal::create([
                'reference' => Ledger::reference('REV'), 'kind' => 'payment', 'payment_id' => $locked->id,
                'amount_kobo' => $locked->amount_kobo, 'reason' => trim($reason),
                'idempotency_key' => Idempotency::newKey(), 'created_by' => $officer->id,
            ]);
            Audit::record('payment.reversed', "Payment {$locked->reference} reversed (".Money::format($locked->amount_kobo).", {$active->count()} allocations reversed)", $locked, ['reason' => $reason, 'allocations_reversed' => $active->count()]);

            return $reversal;
        }, FinancialReversal::class));
    }
}
