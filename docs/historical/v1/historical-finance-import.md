# Historical finance import and recovery

## Choose one evidence model

Use **historical transaction import** only when verified source records identify each obligation or payment. Use **approved opening balance import** when detailed transactions are unavailable and the association has approved each member balance at a documented cut-off date. Never create synthetic transactions to explain an opening balance.

## Templates

Historical transactions use:

```csv
record_type,membership_number,date,amount_ngn,category_code,external_reference,description
```

`record_type` is `obligation` or `payment`. Obligations require an existing active category code. Payments require a unique external reference.

Opening balances use:

```csv
membership_number,amount_ngn,description
```

The administrator separately supplies the approved cut-off date. Money is parsed from decimal text to integer kobo.

## Control sequence

1. Take and verify a protected backup.
2. Upload only an approved CSV to the matching import method.
3. Review every invalid row and the obligation/payment totals. A preview containing any invalid row cannot commit.
4. Obtain the required finance permissions and approval under the association's separation-of-duties policy.
5. Commit once. The D1 batch creates all records, row receipts, job status and audit event atomically; repeating a completed job returns a replay receipt.
6. Export the aggregate report and reconcile imported totals, member counts and sampled statements to the signed source evidence.
7. Preserve the source file checksum, approval, reconciliation and job identifier outside the application under the retention policy.

## Failure and rollback

A database error rolls back the entire D1 batch, leaving no partially imported ledger. After a successful import, do not delete posted financial evidence. Correct individual obligations with authorised adjustments and payments with linked reversals. If the source batch itself was materially wrong, freeze further writes, preserve the audit evidence, restore the pre-import protected backup into an isolated database, compare both states and obtain explicit recovery approval before any production cutover. This repository does not provide a one-click destructive production rollback.
