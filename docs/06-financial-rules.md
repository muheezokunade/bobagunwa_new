# Financial rules and accounting definitions

All amounts are **integer kobo** (₦1 = 100 kobo) in signed `BIGINT` columns. Naira input such as `50,000` or `₦20,000.50` is parsed as text by `App\Support\Money::parse` — never through floating point — and amounts above ₦1,000,000,000 per entry are refused. Currency is fixed to NGN by a `CHECK` constraint.

Live online payment collection is **not** implemented (not approved). Manual record-keeping works from launch.

## Source of truth

Balances are never stored. Every figure is computed from these records:

| Concept | Record | Counts when |
|---|---|---|
| Obligation (amount owed) | `member_obligations` | created by posting an assessment |
| Adjustment | `obligation_adjustments` (signed) | always (append-only) |
| Receipt | `payments` | recorded as `pending` — counts for nothing yet |
| Verification | `payments.status = verified`, `verified_by`, `verified_at` | a permitted officer verifies (by default not the recorder) |
| Allocation | `payment_allocations` (`active`) | a verified payment is applied to the same member's obligation |
| Reversal | `financial_reversals` | appended; allocation or payment becomes `reversed` |
| Unallocated credit | derived | verified funds not yet allocated |

## Definitions (identical in statements, portal, reports and CSV)

Implemented once in `App\Services\Finance\LedgerQuery`:

| Term | Definition |
|---|---|
| Assessed | Σ posted obligations |
| Adjustments | Σ signed adjustments (negative reduces) |
| Effective obligations | Assessed + Adjustments |
| Allocated | Σ active allocations |
| **Outstanding** | Effective obligations − Allocated (never negative — enforced) |
| Verified payments | Σ payments with status `verified` (reversed payments excluded) |
| **Unallocated credit** | Verified payments − Allocated (never negative — enforced) |
| **Net position** | Outstanding − Unallocated credit = Effective obligations − Verified payments |
| Pending | Σ payments awaiting verification — **excluded** from every balance and shown separately |
| Received (period report) | Σ verified or later-reversed payments by date received in the period |
| Reversed (period report) | Σ payment reversals recorded in the period |
| Collection rate | Allocated ÷ Effective obligations for obligations effective in the period |

A positive position is owed to the association; a negative position is credit in the member's favour.

## Workflow rules

1. **Assessment**: draft → preview (target count, total, members excluded because not active, warning if a member was already charged in the same category and period label) → post with an idempotency key and the previewed target count. If the active-member list changed since preview, posting is refused. Posting is exactly-once: replay with the same key returns the original; a new key is refused. Posted assessments cannot be edited — use adjustments.
2. **Recording a payment**: member, amount, method (bank transfer, cash, POS, other), date received (not in the future), optional bank/receipt reference (unique), payer, note, optional evidence file. Same member + amount + date requires explicit confirmation. Replays return the original record.
3. **Verification**: requires `finance.payments.verify`. When "A different officer must verify" is on (default; policy pending, decision D-007) the recorder cannot verify — enforced in the service and by trigger using the policy captured at recording time. Rejection requires a reason.
4. **Allocation**: only verified payments; only to the same member's obligations; per-obligation amount ≤ outstanding; total ≤ unallocated amount of the payment. One payment may cover several obligations; several payments may cover one obligation; partial payments are normal. **No automatic allocation priority is applied** (decision D-009 pending); the "Suggest oldest first" button only pre-fills the form for the officer to review.
5. **Adjustments** (`finance.adjust`): waiver (reduces), correction (±), increase, cancellation (removes whatever remains outstanding). Never below the amount already allocated, never negative. Reason and effective date required.
6. **Reversals** (`finance.reverse`, default role Payment Verifier): reversing an allocation returns the funds to the payment's unallocated amount; reversing a payment first reverses its active allocations in the same transaction, then the payment, keeping all history. Each can happen once.
7. **Overpayment**: retained as unallocated credit. No refund workflow exists (policy D-010 pending).

## Statements

`LedgerQuery::statement()` and `resources/views/partials/statement.blade.php`:

* Lines dated by accounting date: obligations and adjustments by effective date; verified (and later reversed) payments by date received; payment reversals by date recorded. Pending and rejected payments never appear in the running position; pending ones are listed beneath it.
* Opening position = Σ(charges − payments) before the period. Closing = opening + period charges − period payments.
* "Position at end date" splits the closing position into outstanding obligations and unallocated credit using allocations active at the end date.
* Tested: `LedgerServiceTest::test_statement_summary_reports_and_raw_sql_reconcile` and workflow 6 of `MandatoryWorkflowsTest` (₦50,000 − ₦20,000 = ₦30,000 across statement, portal, report, CSV and raw SQL).

## Historical records

See `12-imports-and-reconciliation.md`. Never invent opening balances, payments, dates or references. Opening balances are labelled `opening_balance`; historical records `historical`; credit opening balances are refused (support them with real payment records).

## Pending association decisions

Official dues and levy amounts (D-006), verification separation and limits (D-007), reversal authority (D-008), allocation priority (D-009), refund/overpayment policy (D-010), report visibility (D-011). See `23-client-decisions.md`. Until decided, the safe defaults above apply.
