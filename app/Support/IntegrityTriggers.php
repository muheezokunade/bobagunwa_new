<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * MariaDB trigger definitions that enforce ledger, register and audit
 * invariants at the database level.
 *
 * Every invariant that depends on a running total first takes an exclusive
 * row lock on the parent payment and/or obligation (SELECT ... FOR UPDATE), so
 * concurrent writers against the same funds serialise. Totals are then read
 * after the lock is held. MariaDB forbids locking reads of the trigger's own
 * table (error 1442), so those totals are statement-level consistent reads;
 * the application uses READ COMMITTED, where each such read sees every
 * transaction committed before the lock was granted. Totals on other tables
 * use locking reads. See docs/05-database-integrity-safeguards.md for the
 * residual direct-SQL limitation and the `boba:ledger:verify` detective check.
 *
 * Error messages start with "LEDGER:" or "REGISTER:" so the application can
 * translate them into clear user-facing errors.
 */
final class IntegrityTriggers
{
    /** @return array<string, string> */
    public static function definitions(): array
    {
        $block = fn (string $table, string $event, string $message) => sprintf(
            "CREATE TRIGGER boba_%s_block_%s BEFORE %s ON %s FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '%s'",
            $table, strtolower($event), $event, $table, $message
        );

        return [
            // ---------------------------------------------------------------
            // Append-only evidence tables.
            // ---------------------------------------------------------------
            'boba_audit_events_block_update' => $block('audit_events', 'UPDATE', 'REGISTER: audit events are append-only'),
            'boba_audit_events_block_delete' => $block('audit_events', 'DELETE', 'REGISTER: audit events are append-only'),
            'boba_security_events_block_update' => $block('security_events', 'UPDATE', 'REGISTER: security events are append-only'),
            'boba_security_events_block_delete' => $block('security_events', 'DELETE', 'REGISTER: security events are append-only'),
            'boba_application_events_block_update' => $block('application_events', 'UPDATE', 'REGISTER: application history is append-only'),
            'boba_application_events_block_delete' => $block('application_events', 'DELETE', 'REGISTER: application history is append-only'),
            'boba_users_block_delete' => $block('users', 'DELETE', 'REGISTER: user accounts are disabled, never deleted'),
            'boba_integrity_check_runs_block_update' => $block('integrity_check_runs', 'UPDATE', 'REGISTER: integrity check history is append-only'),
            'boba_integrity_check_runs_block_delete' => $block('integrity_check_runs', 'DELETE', 'REGISTER: integrity check history is append-only'),
            'boba_system_alerts_block_delete' => $block('system_alerts', 'DELETE', 'REGISTER: alerts are resolved, never deleted'),
            'boba_alert_notifications_block_update' => $block('alert_notifications', 'UPDATE', 'REGISTER: alert delivery records are append-only'),
            'boba_alert_notifications_block_delete' => $block('alert_notifications', 'DELETE', 'REGISTER: alert delivery records are append-only'),

            // ---------------------------------------------------------------
            // Membership numbers: issued once, never changed, never reused.
            // ---------------------------------------------------------------
            'boba_membership_numbers_block_update' => $block('membership_numbers', 'UPDATE', 'REGISTER: issued membership numbers are immutable'),
            'boba_membership_numbers_block_delete' => $block('membership_numbers', 'DELETE', 'REGISTER: issued membership numbers can never be deleted or reused'),
            'boba_members_block_delete' => $block('members', 'DELETE', 'REGISTER: member records are never deleted'),
            'boba_members_bu' => <<<'SQL'
CREATE TRIGGER boba_members_bu BEFORE UPDATE ON members FOR EACH ROW
BEGIN
  IF NOT (NEW.membership_number <=> OLD.membership_number) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'REGISTER: membership numbers cannot be changed';
  END IF;
  IF NOT (NEW.membership_application_id <=> OLD.membership_application_id) OR NOT (NEW.origin <=> OLD.origin) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'REGISTER: member origin cannot be changed';
  END IF;
END
SQL,

            // ---------------------------------------------------------------
            // Assessments: draft -> posted | discarded; posted is immutable.
            // ---------------------------------------------------------------
            'boba_assessments_bi' => <<<'SQL'
CREATE TRIGGER boba_assessments_bi BEFORE INSERT ON assessments FOR EACH ROW
BEGIN
  IF NEW.status <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: assessments must be created as drafts';
  END IF;
END
SQL,
            'boba_assessments_bu' => <<<'SQL'
CREATE TRIGGER boba_assessments_bu BEFORE UPDATE ON assessments FOR EACH ROW
BEGIN
  IF OLD.status IN ('posted', 'discarded') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: posted or discarded assessments are immutable';
  END IF;
  IF NEW.status = 'posted' AND (NEW.posted_by IS NULL OR NEW.posted_at IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: posting requires an authorising officer';
  END IF;
END
SQL,
            'boba_assessments_block_delete' => $block('assessments', 'DELETE', 'LEDGER: assessments are discarded, never deleted'),

            // ---------------------------------------------------------------
            // Obligations: only created under a posted assessment; immutable.
            // ---------------------------------------------------------------
            'boba_member_obligations_bi' => <<<'SQL'
CREATE TRIGGER boba_member_obligations_bi BEFORE INSERT ON member_obligations FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(20) DEFAULT NULL;
  DECLARE v_origin VARCHAR(20) DEFAULT NULL;
  DECLARE v_amount BIGINT DEFAULT NULL;
  SELECT status, origin, amount_kobo INTO v_status, v_origin, v_amount FROM assessments WHERE id = NEW.assessment_id FOR UPDATE;
  IF v_status IS NULL OR v_status <> 'posted' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: obligations require a posted assessment';
  END IF;
  IF v_origin = 'native' AND NEW.amount_kobo <> v_amount THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: obligation amount must match the assessment';
  END IF;
END
SQL,
            'boba_member_obligations_block_update' => $block('member_obligations', 'UPDATE', 'LEDGER: posted obligations are immutable; use an adjustment'),
            'boba_member_obligations_block_delete' => $block('member_obligations', 'DELETE', 'LEDGER: posted obligations are immutable; use an adjustment'),

            // ---------------------------------------------------------------
            // Adjustments: never below zero or below allocated funds.
            // ---------------------------------------------------------------
            'boba_obligation_adjustments_bi' => <<<'SQL'
CREATE TRIGGER boba_obligation_adjustments_bi BEFORE INSERT ON obligation_adjustments FOR EACH ROW
BEGIN
  DECLARE v_amount BIGINT DEFAULT NULL;
  DECLARE v_member BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_adjusted BIGINT DEFAULT 0;
  DECLARE v_allocated BIGINT DEFAULT 0;
  SELECT amount_kobo, member_id INTO v_amount, v_member FROM member_obligations WHERE id = NEW.member_obligation_id FOR UPDATE;
  IF v_amount IS NULL OR v_member <> NEW.member_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: adjustment must reference the member obligation';
  END IF;
  IF (NEW.kind IN ('waiver', 'cancellation') AND NEW.amount_kobo >= 0) OR (NEW.kind = 'increase' AND NEW.amount_kobo <= 0) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: adjustment sign does not match its kind';
  END IF;
  SELECT COALESCE(SUM(amount_kobo), 0) INTO v_adjusted FROM obligation_adjustments WHERE member_obligation_id = NEW.member_obligation_id;
  SELECT COALESCE(SUM(amount_kobo), 0) INTO v_allocated FROM payment_allocations WHERE member_obligation_id = NEW.member_obligation_id AND status = 'active' FOR UPDATE;
  IF v_amount + v_adjusted + NEW.amount_kobo < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: adjustment would make the obligation negative';
  END IF;
  IF v_amount + v_adjusted + NEW.amount_kobo < v_allocated THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: adjustment would reduce the obligation below allocated payments';
  END IF;
END
SQL,
            'boba_obligation_adjustments_block_update' => $block('obligation_adjustments', 'UPDATE', 'LEDGER: adjustments are append-only'),
            'boba_obligation_adjustments_block_delete' => $block('obligation_adjustments', 'DELETE', 'LEDGER: adjustments are append-only'),

            // ---------------------------------------------------------------
            // Payments: two-step verification, immutable financial fields,
            // reversal only through a linked reversal record.
            // ---------------------------------------------------------------
            'boba_payments_bi' => <<<'SQL'
CREATE TRIGGER boba_payments_bi BEFORE INSERT ON payments FOR EACH ROW
BEGIN
  IF NEW.status = 'pending' THEN
    IF NEW.verified_by IS NOT NULL OR NEW.verified_at IS NOT NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: a pending payment cannot carry verification';
    END IF;
  ELSEIF NEW.status = 'verified' THEN
    IF NEW.origin <> 'historical' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: new payments must be recorded as pending and verified separately';
    END IF;
    IF NEW.verified_by IS NULL OR NEW.verified_at IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: verified payment requires a verifier';
    END IF;
  ELSE
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: payments start as pending';
  END IF;
END
SQL,
            'boba_payments_bu' => <<<'SQL'
CREATE TRIGGER boba_payments_bu BEFORE UPDATE ON payments FOR EACH ROW
BEGIN
  IF NOT (NEW.member_id <=> OLD.member_id AND NEW.amount_kobo <=> OLD.amount_kobo AND NEW.currency <=> OLD.currency
      AND NEW.reference <=> OLD.reference AND NEW.method <=> OLD.method AND NEW.received_on <=> OLD.received_on
      AND NEW.external_reference <=> OLD.external_reference AND NEW.origin <=> OLD.origin
      AND NEW.recorded_by <=> OLD.recorded_by AND NEW.idempotency_key <=> OLD.idempotency_key
      AND NEW.requires_distinct_verifier <=> OLD.requires_distinct_verifier AND NEW.import_batch_id <=> OLD.import_batch_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: payment financial details are immutable';
  END IF;
  IF OLD.status <> 'pending' AND NOT (NEW.payer_name <=> OLD.payer_name AND NEW.narrative <=> OLD.narrative AND NEW.evidence_file_id <=> OLD.evidence_file_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: a processed payment cannot be edited';
  END IF;
  IF NOT (NEW.status <=> OLD.status) THEN
    IF OLD.status = 'pending' AND NEW.status = 'verified' THEN
      IF NEW.verified_by IS NULL OR NEW.verified_at IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: verification requires a verifier';
      END IF;
      IF NEW.requires_distinct_verifier = 1 AND NEW.verified_by = NEW.recorded_by THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: a different officer must verify this payment';
      END IF;
    ELSEIF OLD.status = 'pending' AND NEW.status = 'rejected' THEN
      IF NEW.rejected_by IS NULL OR NEW.rejected_at IS NULL OR NEW.rejection_reason IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: rejection requires an officer and reason';
      END IF;
    ELSEIF OLD.status = 'verified' AND NEW.status = 'reversed' THEN
      IF NOT EXISTS (SELECT 1 FROM financial_reversals WHERE payment_id = OLD.id AND kind = 'payment') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: payment reversal requires a reversal record';
      END IF;
    ELSE
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: invalid payment status transition';
    END IF;
  ELSEIF OLD.status <> 'pending' AND NOT (NEW.verified_by <=> OLD.verified_by AND NEW.verified_at <=> OLD.verified_at
      AND NEW.verification_note <=> OLD.verification_note AND NEW.rejected_by <=> OLD.rejected_by
      AND NEW.rejected_at <=> OLD.rejected_at AND NEW.rejection_reason <=> OLD.rejection_reason) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: verification history is immutable';
  END IF;
END
SQL,
            'boba_payments_block_delete' => $block('payments', 'DELETE', 'LEDGER: payments are never deleted; reject or reverse instead'),

            // ---------------------------------------------------------------
            // Allocations: verified funds only, same member, never exceeding
            // either the payment or the obligation outstanding amount.
            // ---------------------------------------------------------------
            'boba_payment_allocations_bi' => <<<'SQL'
CREATE TRIGGER boba_payment_allocations_bi BEFORE INSERT ON payment_allocations FOR EACH ROW
BEGIN
  DECLARE v_pay_status VARCHAR(20) DEFAULT NULL;
  DECLARE v_pay_member BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_pay_amount BIGINT DEFAULT 0;
  DECLARE v_obl_member BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_obl_amount BIGINT DEFAULT 0;
  DECLARE v_obl_adjusted BIGINT DEFAULT 0;
  DECLARE v_pay_allocated BIGINT DEFAULT 0;
  DECLARE v_obl_allocated BIGINT DEFAULT 0;
  IF NEW.status <> 'active' OR NEW.reversal_id IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: allocations must be created active';
  END IF;
  SELECT status, member_id, amount_kobo INTO v_pay_status, v_pay_member, v_pay_amount FROM payments WHERE id = NEW.payment_id FOR UPDATE;
  IF v_pay_status IS NULL OR v_pay_status <> 'verified' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: only verified payments can be allocated';
  END IF;
  SELECT member_id, amount_kobo INTO v_obl_member, v_obl_amount FROM member_obligations WHERE id = NEW.member_obligation_id FOR UPDATE;
  IF v_obl_member IS NULL OR v_pay_member <> NEW.member_id OR v_obl_member <> NEW.member_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: cross-member allocation is not permitted';
  END IF;
  SELECT COALESCE(SUM(amount_kobo), 0) INTO v_pay_allocated FROM payment_allocations WHERE payment_id = NEW.payment_id AND status = 'active';
  IF v_pay_allocated + NEW.amount_kobo > v_pay_amount THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: allocation exceeds the available payment amount';
  END IF;
  SELECT COALESCE(SUM(amount_kobo), 0) INTO v_obl_adjusted FROM obligation_adjustments WHERE member_obligation_id = NEW.member_obligation_id FOR UPDATE;
  SELECT COALESCE(SUM(amount_kobo), 0) INTO v_obl_allocated FROM payment_allocations WHERE member_obligation_id = NEW.member_obligation_id AND status = 'active';
  IF v_obl_allocated + NEW.amount_kobo > v_obl_amount + v_obl_adjusted THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: allocation exceeds the obligation outstanding amount';
  END IF;
END
SQL,
            'boba_payment_allocations_bu' => <<<'SQL'
CREATE TRIGGER boba_payment_allocations_bu BEFORE UPDATE ON payment_allocations FOR EACH ROW
BEGIN
  IF NOT (NEW.payment_id <=> OLD.payment_id AND NEW.member_obligation_id <=> OLD.member_obligation_id
      AND NEW.member_id <=> OLD.member_id AND NEW.amount_kobo <=> OLD.amount_kobo
      AND NEW.allocated_by <=> OLD.allocated_by AND NEW.batch_key <=> OLD.batch_key AND NEW.created_at <=> OLD.created_at) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: allocations are immutable';
  END IF;
  IF NOT (OLD.status = 'active' AND NEW.status = 'reversed' AND OLD.reversal_id IS NULL AND NEW.reversal_id IS NOT NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: allocations can only be reversed through a reversal record';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM financial_reversals WHERE id = NEW.reversal_id AND kind = 'allocation' AND payment_allocation_id = OLD.id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: allocation reversal record not found';
  END IF;
END
SQL,
            'boba_payment_allocations_block_delete' => $block('payment_allocations', 'DELETE', 'LEDGER: allocations are never deleted; reverse instead'),

            // ---------------------------------------------------------------
            // Reversals: inserting the record performs the linked status change
            // in the same statement, so the ledger can never be half-reversed.
            // ---------------------------------------------------------------
            'boba_financial_reversals_bi' => <<<'SQL'
CREATE TRIGGER boba_financial_reversals_bi BEFORE INSERT ON financial_reversals FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(20) DEFAULT NULL;
  DECLARE v_amount BIGINT DEFAULT 0;
  DECLARE v_payment BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_active INT DEFAULT 0;
  IF NEW.kind = 'allocation' THEN
    SELECT status, amount_kobo, payment_id INTO v_status, v_amount, v_payment FROM payment_allocations WHERE id = NEW.payment_allocation_id FOR UPDATE;
    IF v_status IS NULL OR v_status <> 'active' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: only active allocations can be reversed';
    END IF;
    IF v_amount <> NEW.amount_kobo OR v_payment <> NEW.payment_id THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: reversal must match the allocation exactly';
    END IF;
  ELSE
    SELECT status, amount_kobo INTO v_status, v_amount FROM payments WHERE id = NEW.payment_id FOR UPDATE;
    IF v_status IS NULL OR v_status <> 'verified' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: only verified, unreversed payments can be reversed';
    END IF;
    IF v_amount <> NEW.amount_kobo THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: payment reversal must equal the payment amount';
    END IF;
    SELECT COUNT(*) INTO v_active FROM payment_allocations WHERE payment_id = NEW.payment_id AND status = 'active' FOR UPDATE;
    IF v_active > 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: reverse active allocations before reversing the payment';
    END IF;
  END IF;
END
SQL,
            'boba_financial_reversals_ai' => <<<'SQL'
CREATE TRIGGER boba_financial_reversals_ai AFTER INSERT ON financial_reversals FOR EACH ROW
BEGIN
  IF NEW.kind = 'allocation' THEN
    UPDATE payment_allocations SET status = 'reversed', reversal_id = NEW.id, updated_at = NOW(6) WHERE id = NEW.payment_allocation_id;
  ELSE
    UPDATE payments SET status = 'reversed', updated_at = NOW() WHERE id = NEW.payment_id;
  END IF;
END
SQL,
            'boba_financial_reversals_block_update' => $block('financial_reversals', 'UPDATE', 'LEDGER: reversals are append-only'),
            'boba_financial_reversals_block_delete' => $block('financial_reversals', 'DELETE', 'LEDGER: reversals are append-only'),
        ];
    }

    /**
     * Definitions whose table already exists — used by the original trigger
     * migration so a fresh install can run migrations in order.
     *
     * @return array<string, string>
     */
    public static function installable(): array
    {
        return array_filter(self::definitions(), function (string $sql) {
            preg_match('/ ON ([a-z_]+) FOR EACH ROW/', $sql, $m);

            return Schema::hasTable($m[1]);
        });
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::definitions());
    }
}
