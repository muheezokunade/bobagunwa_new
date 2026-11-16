# Imports and reconciliation

## Existing-member import (`members.import`)

1. Download the template (`full_name` required; optional `email, phone, date_of_birth, membership_number, joined_on, ijebu_town, city, occupation, address`). Excel CSV is accepted: UTF-8 or Windows-1252, BOM, comma or semicolon, header aliases (`name`, `name_as_provided`, …), dates `YYYY-MM-DD` or `DD/MM/YYYY`.
2. Upload → preview. Each row is validated and checked for duplicates within the file (email, phone, membership number, normalised name) and against the register (email, phone, issued numbers, names). Up to 2,000 rows per file, 2 MB.
3. A preview with **any** invalid or duplicate row cannot be committed; correct the file and upload again. Accepted records are never touched by a failed preview.
4. Commit is one transaction, re-validates against the register as it is at that moment, issues numbers, creates members with origin `import` and **no login accounts**. Repeating the commit returns the original receipt; the same file can never be committed twice (unique committed file hash).
5. Production gate: `approval.member_import` and `approval.membership_number_format` must be recorded.

Measured (local MariaDB 11.4): 2 / 86 / 100 / 500 / 1,000 rows each preview and commit in under 3 seconds with ~75 MB peak memory (`MemberImportTest`, `docs/evidence/test-run-2026-11-16.txt`).

**The 86-name provisional client roster is not in this repository** and has not been imported. Its rows lack email, phone and dates of birth; they will import (names only) once the association confirms the roster and the number convention.

## Historical financial import (`finance.import`)

Two evidence models — choose one per batch:

| Model | Template | Creates |
|---|---|---|
| Historical transactions | `record_type, membership_number, date, amount_ngn, category_code, external_reference, description` | obligations (grouped into labelled historical assessments) and **verified historical payments** (each with its bank/receipt reference) |
| Opening balances | `membership_number, amount_ngn, description` + approved cut-off date + category | obligations labelled `opening_balance`; credit balances refused |

Controls: an approved source reference is mandatory; a different officer must confirm the commit when the two-officer rule is on; preview totals (obligations, payments, members) are stored; commit is atomic and idempotent; imported payments are left unallocated for an officer to allocate (no approved allocation priority). Production gate: `approval.historical_finance`.

## Reconciliation

* **Reports → Reconciliation checks** runs nine detective queries (over-allocated payments/obligations, negative obligations, active allocations on unverified payments, cross-member allocations, reversals without records, assessment totals differing from obligations, self-verified payments where two officers were required). All must show "No exceptions". The same checks run daily (`boba:ledger:verify`) and after every restore.
* Organisation totals, member balances, statements and CSV exports use the same `LedgerQuery`, and tests reconcile them against raw SQL.
* Monthly routine: compare *Collections in period* against bank statements and the cash book; investigate pending payments older than a week; confirm reconciliation checks are clean; export and file the balances CSV.
