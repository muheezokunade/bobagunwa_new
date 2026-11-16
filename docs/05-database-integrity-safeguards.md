# Database integrity safeguards

The ledger, register and audit history are protected **in MariaDB itself**, not only in Laravel code, so that a defect, a mistaken script or direct SQL cannot silently corrupt them. Definitions live in `app/Support/IntegrityTriggers.php` and are installed by migration `2026_11_16_000005`. `php artisan boba:ledger:verify` (scheduled daily at 01:30) and `php artisan boba:preflight` confirm all 30 triggers are present; a restore is rejected if any is missing.

## Layers

| Layer | Mechanism | Examples |
|---|---|---|
| Structure | Foreign keys (all `RESTRICT`), unique keys, `NOT NULL` | `payments.idempotency_key`, `members.membership_number` → `membership_numbers.number` |
| Structural impossibility | Composite foreign keys `(id, member_id)` | An allocation's `(payment_id, member_id)` must match a payment of that member **and** `(member_obligation_id, member_id)` an obligation of the same member |
| Values | `CHECK` constraints | amounts `> 0`; adjustment `≠ 0`; currency `= 'NGN'`; reversal kind/shape |
| Uniqueness of live state | Persistent generated columns + unique index | one open application per email and per phone; one committed import per file; one payment reversal per payment |
| Behaviour | `BEFORE`/`AFTER` triggers raising `SQLSTATE 45000` with `LEDGER:`/`REGISTER:` messages | see table below |
| Concurrency | Trigger `SELECT … FOR UPDATE` on the parent payment/obligation row; service-level row locks; idempotency keys | concurrent allocations against one payment serialise |
| Detection | `LedgerQuery::exceptions()` — nine reconciliation queries that must return zero rows | shown on Reports → Reconciliation checks, and after every restore |

## Invariants and where they are enforced and tested

| # | Invariant | Database safeguard | Executed test |
|---|---|---|---|
| L1 | Obligations exist only under a **posted** assessment, and native obligations equal the assessment amount | `boba_member_obligations_bi` | `IntegrityTriggersTest`, `LedgerServiceTest::test_assessment_posts_exactly_once…` |
| L2 | Posted/discarded assessments and all obligations are immutable and undeletable | `boba_assessments_bu`, `boba_assessments_block_delete`, `boba_member_obligations_block_update/delete` | `IntegrityTriggersTest::test_posted_records_are_immutable_and_undeletable` |
| L3 | A new native payment is always `pending`; only historical imports may insert `verified` | `boba_payments_bi` | `IntegrityTriggersTest::test_self_verification_is_rejected…` |
| L4 | Payment amount, member, date, method, references and recorder never change; processed payments cannot be edited | `boba_payments_bu` | `IntegrityTriggersTest::test_posted_records…` |
| L5 | Status transitions only pending→verified, pending→rejected, verified→reversed (the last only with a reversal record) | `boba_payments_bu` | same |
| L6 | When the two-officer rule applied at recording time, verifier ≠ recorder | `boba_payments_bu` (policy snapshotted per payment in `requires_distinct_verifier`) | `IntegrityTriggersTest`, `LedgerServiceTest::test_recorded_payment_counts_only_after…` |
| L7 | Payments are never deleted | `boba_payments_block_delete` | `IntegrityTriggersTest` |
| L8 | Allocations only from **verified** payments | `boba_payment_allocations_bi` | `IntegrityTriggersTest::test_over_allocation_and_unverified…` |
| L9 | No cross-member allocation | composite FKs + `boba_payment_allocations_bi` | `IntegrityTriggersTest::test_cross_member_allocation_by_direct_sql_is_rejected` |
| L10 | Σ active allocations of a payment ≤ payment amount | `boba_payment_allocations_bi` (locks payment row) | `IntegrityTriggersTest`, `ConcurrencyTest::test_two_requests_cannot_exhaust…`, `…direct_sql_racers…` |
| L11 | Σ active allocations of an obligation ≤ obligation + adjustments | `boba_payment_allocations_bi` (locks obligation row) | `IntegrityTriggersTest` |
| L12 | Allocations are immutable; the only change is active→reversed with a matching reversal record | `boba_payment_allocations_bu`, `…_block_delete` | `IntegrityTriggersTest::test_reversal_rules_hold…` |
| L13 | Adjustments never make an obligation negative or less than what is already allocated; sign must match kind | `boba_obligation_adjustments_bi` | `IntegrityTriggersTest::test_adjustments_cannot_go_negative…` |
| L14 | A payment can be reversed only once, in full, after its allocations are reversed | `boba_financial_reversals_bi`, unique `financial_reversals_one_per_payment` | `IntegrityTriggersTest`, `LedgerServiceTest::test_payment_reversal…` |
| L15 | Inserting a reversal record performs the linked status change in the same statement (no half-reversed ledger) | `boba_financial_reversals_ai` | `IntegrityTriggersTest::test_reversal_rules_hold…` |
| L16 | Reversals and adjustments are append-only | `…_block_update/delete` triggers | `IntegrityTriggersTest` |
| R1 | Membership numbers are unique, never changed, never deleted, never reused | unique key, FK from members, `boba_membership_numbers_block_*`, `boba_members_bu` | `IntegrityTriggersTest::test_membership_numbers_are_never…`, `ConcurrencyTest::test_concurrent_number_issuance…` |
| R2 | Members and users are never hard-deleted | `boba_members_block_delete`, `boba_users_block_delete` | `IntegrityTriggersTest` |
| R3 | Audit, security and application history are append-only | `boba_audit_events_block_*`, `boba_security_events_block_*`, `boba_application_events_block_*` | `IntegrityTriggersTest` |
| R4 | One open application per email and per phone | generated `active_email_key`/`active_phone_key` + unique | `ApplicationWorkflowTest::test_validation_duplicates…` |
| T1 | A failed multi-record operation leaves no partial records | InnoDB transactions | `IntegrityTriggersTest::test_failed_multi_record_transaction_rolls_back_completely` |

## Concurrency model

* Application connections use `READ COMMITTED` (`config/database.php`), so each statement sees all previously committed work.
* Each total-dependent trigger first takes `SELECT … FOR UPDATE` on the parent **payment** and/or **obligation** row. Concurrent writers against the same funds therefore queue behind one another; the second sees the first's committed allocation.
* Services also lock the payment and obligations (in id order, to avoid deadlocks) before validating, and wrap work in an idempotency key inserted in the same transaction.
* **Proven** by `tests/Feature/Concurrency/ConcurrencyTest.php`, which forks real processes with separate connections: two allocations exhausting one payment (8 rounds), three direct-SQL racers under both `READ COMMITTED` and `REPEATABLE READ`, concurrent duplicate payments, concurrent approvals, concurrent number issuance and concurrent assessment posting.

### Residual limitation (documented, mitigated)

MariaDB forbids a locking read of the trigger's **own** table (error 1442). Running totals on `payment_allocations` inside its own trigger are therefore consistent reads taken *after* the parent-row lock is granted. Under `READ COMMITTED` (the application) and under `REPEATABLE READ` for a transaction that has not previously read, this is correct and is tested. A hand-written direct-SQL session that deliberately opens a `REPEATABLE READ` snapshot, reads, waits for a competitor to commit, and only then inserts, could in theory evaluate a stale total. This requires database credentials (which already allow far worse) and is caught by the daily `boba:ledger:verify` detective check. Database credentials are restricted to the application user (see `13-authentication-and-security.md`).

A related, safe behaviour: `INSERT INTO payment_allocations … SELECT … FROM payments` is refused by MariaDB (error 1442) because the trigger locks `payments`. Bulk tools must insert computed values instead.

## Operating rules

* Never disable, drop or edit triggers on a live database. Schema changes go through migrations that reinstall `IntegrityTriggers::definitions()`.
* If `boba:ledger:verify` reports an exception: freeze financial work, keep evidence, escalate to the treasurer and technical maintainer, and correct only through adjustments/reversals after the cause is understood.
* Hosting note: creating triggers requires the `TRIGGER` privilege, which cPanel grants with "ALL PRIVILEGES" on the database. If binary logging is ever enabled on the host, `log_bin_trust_function_creators` may be required; `boba:preflight` creates and drops a probe trigger to verify.
