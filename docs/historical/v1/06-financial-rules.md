# Financial rules

## Source of truth

1. A posted assessment creates member obligations.
2. A posted/verified payment creates available member funds.
3. Allocations apply those funds to obligations.
4. Unallocated posted funds remain member credit.
5. Reversals are linked correcting records; originals remain visible.
6. A cancelled obligation contributes no payable amount but remains auditable.

All NGN amounts are stored as integer kobo. APIs accept a validated integer minor-unit amount; formatted naira input is converted at the boundary using decimal-string parsing, never binary floating-point arithmetic.

## Calculations

- `active obligations = sum(original obligation + authorised adjustments, excluding cancelled)`
- `valid allocated = sum(active allocations not negated by a reversal)`
- `outstanding = max(active obligations - valid allocated, 0)`
- `net posted payments = posted payments - payment reversals`
- `unallocated credit = max(net posted payments - valid allocated, 0)`
- `net position = outstanding - unallocated credit`

Dashboard totals must be computed from the same query/model as member statements and independently reconciled in tests.

## Posting rules

- Draft records do not affect balances.
- Posting requires an authorised actor, idempotency key, correlation ID, and atomic D1 batch.
- An assessment preview shows target count, amount per target, total, exclusions, and duplicate warnings.
- The database unique key `(assessment_id, member_id)` prevents duplicate obligations.
- An allocation cannot be negative, exceed the target obligation remainder, or make total allocations exceed net posted payment funds.
- An external payment reference may be unique when supplied, but equal amount/date values only generate a warning.
- Reversal requires reason, authorising actor, original record, timestamp, and a new reference.
- Closed accounting periods reject ordinary mutations; any approved correction is posted in an open period and references the prior record.

## Historical controls

- Native, verified historical-transaction and approved opening-balance records retain distinct `origin` values.
- Historical payments require a source reference; historical obligations require an active category.
- An opening-balance batch creates obligations under a clearly labelled assessment at its approved cut-off date. It never creates fictional payments.
- Preview rows and reconciliation totals persist before commit. A batch with any invalid row cannot commit, and a repeated completed file/job is rejected or returns its prior receipt.
- Import commits, row receipts and audit evidence are atomic. Post-commit corrections use adjustments/reversals; material batch recovery follows the protected-backup procedure.

## Decisions still required

Allocation priority, partial allocation approval, overpayment/refund handling, advance payments, suspension effects, archived-member treatment, assessment cancellation authority, posting separation of duties, reversal limits, accounting period closure, and official statement wording require association approval before production use.
