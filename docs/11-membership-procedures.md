# Membership procedures

Policy version **2026-10-11-v1** (confirmed in V1 records, `historical/v1/membership-eligibility-criteria.md`).

## Eligibility

* Ijebu men born **1 January 1980 to 31 December 1982 inclusive**.
* A date outside the range is accepted and **flagged** for human consideration — never automatically approved or discarded (`ApplicationService::screen`; tested at all four boundary dates).
* A match against an existing member's email/phone is flagged privately for the reviewer, without disclosure to the applicant.

## Application states

`submitted → under_review → changes_requested ⇄ under_review → approved | rejected` (approved and rejected are final). Every transition needs a private review note; information requests and rejections need an applicant-facing message. When the applicant replies through the status page, the application returns to `under_review` automatically.

## Approval

One transaction locks the application, re-checks duplicates against the register, issues the next membership number, creates the member (origin `application`), links the application, records the event and audit, creates a pending account with the member role, and prepares the decision message. Then a single-use 7-day activation link is issued and shown **once** to the approving officer. Repeat or concurrent approvals are refused (tested with three racing processes).

## Membership numbers

* Format configurable: prefix (default `BOB-`) + zero-padded width (default 6) — **requires approval (D-004)**. In production, issuing numbers is blocked until the approval is recorded under Settings → Launch approvals.
* Issued from a locked sequence; numbers already present (for example legacy numbers supplied in an import) are skipped.
* `membership_numbers` is immutable and undeletable; members reference it by foreign key; the visible number is separate from the internal database ID.

## Register maintenance

* Search by number, name, email, phone; filter by status, origin, account state and incomplete records.
* Corrections use optimistic locking (version) and require a reason; each change records old and new values in the audit history.
* Status: active, suspended, inactive, each with a reason; non-active status ends the member's sessions and blocks login.
* Possible duplicates (same normalised name) are listed on the member page.
* A single existing member can be added by hand (**Members → Add a member**). Email/phone duplicates are refused. A same-name match requires the officer's confirmation and a distinguishing note of at least 10 characters, recorded in admin notes and the audit history (decision D-026). The same confirmation applies to **Name match** rows in an import.
* Members are never deleted.

## Account delivery

No paid email service is required. Default: the officer sees the one-time link, sends it through an approved private channel after verifying identity, then records delivery under **Messages to deliver** (attestation with officer, time and note — recorded separately from token issuance). If SMTP is configured and enabled, the link is also emailed and the record shows **"Sent by email"** (mail-server acceptance), never "delivered". The production notification channel requires approval (D-013).
