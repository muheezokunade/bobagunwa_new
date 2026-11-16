# Database schema

The initial D1 schema is in `migrations/0001_initial_schema.sql`; `migrations/0002_rbac_catalog.sql` supplies stable permission and role keys without granting policy-sensitive permissions. The migrations establish association-domain records without coupling them to authentication-package internals.

## Major entities

- `members`: association record, normalised duplicate keys and non-reused membership number.
- `membership_applications`: applicant record, birth date, versioned eligibility screening result, review decision and optional approved-member link.
- `application_events`: immutable applicant-visible status events plus permission-guarded private review notes and actor/timestamp evidence.
- `membership_number_allocations`: one sequence allocation per approved application, preventing number changes and approval replay duplicates.
- `application_notifications`: manual, low-cost applicant notification queue with delivery administrator, timestamp and note.
- `app_users`: application identity mapped to an authentication subject and optionally a member.
- `roles`, `permissions`, `app_user_roles`, `role_permissions`: granular RBAC.
- `accounting_periods`, `fee_categories`, `assessments`, `member_obligations`: amounts owed and their source.
- `payments`, `payment_allocations`, `payment_reversals`: manual receipts, application of funds, and append-only corrections.
- `idempotency_keys`: duplicate mutation protection.
- `announcements`, `events`, `event_responses`, `activities`, `content_pages`: editable content/communications.
- `enquiries`: durable contact submissions.
- `import_jobs`, `import_rows`: safe bulk-import preview and audit trail.
- `audit_events`: security/administrative evidence without unnecessary sensitive payloads.

## Integrity model

- All money is integer minor units with non-negative checks.
- Status values are constrained.
- Active applications are unique by normalised email and, when present, normalised phone. Approved application-to-member links and membership-number allocations are unique.
- Membership numbers, member emails and populated normalised member phones are unique. Approval inserts the member, invitation, event, notification and audit evidence in one D1 batch.
- Foreign keys define ownership and prevent orphaned allocations.
- Payment external references are unique when present.
- Allocation IDs are unique and each allocation targets one obligation.
- Posted financial records are immutable by application policy. Corrections append reversal/adjustment records; direct update/delete is not exposed.
- Balances are derived from posted obligations, allocations, credits, cancellations, and reversals rather than stored as an independent total.
- Multi-row posting uses D1 `batch()` and idempotency records. Service-level tests must prove all-or-nothing behaviour in the target runtime.
- Database triggers reject allocations against unposted payments/cancelled obligations, cross-member or cross-currency allocations, over-allocation of a payment/obligation, and reversals that exceed available unallocated funds.

## Index rationale

Indexes cover member lookup, application queues, sessions-to-domain identity lookup, assessment/obligation reporting, payment history and duplicate detection, publication queries, import status, and chronological audit review. They reduce D1 row reads but incur write/storage cost, so new indexes require query evidence.

## Authentication tables

Better Auth owns its authentication tables and migrations. They will be generated from the pinned, benchmarked package version in a subsequent migration. `app_users.auth_subject` maps the framework user ID to domain permissions and membership; this prevents domain records from depending on authentication-table internals.
