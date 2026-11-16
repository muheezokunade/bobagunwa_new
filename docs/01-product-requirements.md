# Product requirements

## Outcome

A credible digital home for **Ẹgbẹ́ Bobagunwa Okunrin Akile Ijebu** that preserves its heritage identity and makes membership and financial administration organised, auditable and safe — on the association's existing Namecheap hosting with **no new recurring subscriptions**.

## Audiences

1. **Public and prospective members** — reliable information; a private, trackable membership application.
2. **Approved members** — own profile, membership number and status, dues/levies, payments, statements, notices, events, documents, account security.
3. **Authorised officers** — secure tools to review applications, keep the register, import existing members, assess dues and levies, record and independently verify payments, allocate, correct and reverse, report and reconcile, publish content and events, handle enquiries, manage access, audit, back up.

Scale: ~100 members at launch; designed and measured for 1,000 without architectural change.

## Functional scope (all implemented — status in `28-release-readiness-report.md`)

* Public website: home, about, history and heritage, leadership, membership, apply, track application, activities, events (+detail), news (+article), gallery (+album), contact, privacy, terms, sitemap.
* Membership: public application without an account; inclusive 1980–1982 birth-date screening with human consideration for exceptions; private status tracking with a separate secret code; reviewer workflow with private notes and applicant messages; single approval creating exactly one member and number.
* Register: permanent non-reused membership numbers; search/filter/export; corrections with history; suspension/reactivation; duplicates and incomplete records surfaced.
* Existing-member CSV import with validation, preview, duplicates, atomic idempotent commit and no automatic accounts.
* Accounts: activation and reset by single-use expiring tokens; officer-assisted delivery with attestation; suspension enforcement; session controls; no public registration.
* Member portal: dashboard, profile, dues and payments, statement (print/CSV), notices, events and RSVP, documents, password and sessions.
* Finance: categories, assessments, manual payments, independent verification, allocations (partial, split, multi-payment), unallocated credit, adjustments, reversals, statements, treasury reports, reconciliation checks, historical/opening-balance import.
* Content: versioned pages, news, member notices, activities, leadership, galleries/media, documents, events, enquiries.
* Administration: role-based access with separation of duties, audit history, settings and launch approvals, encrypted backups.

## Explicitly excluded (not approved)

Online payment collection, automated bank reconciliation, paid SMS/WhatsApp/email services, native apps, public member directory, AI features, multi-tenant use.

## Priorities

Financial correctness → security → functional completeness → hosting compatibility → design quality → maintainability → documentation accuracy.
