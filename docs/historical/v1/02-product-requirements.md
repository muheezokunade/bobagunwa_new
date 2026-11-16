# Product requirements

## Product outcome

Create one digital home for the association: a public heritage website and a private system for membership, dues, levies, manual payments, announcements, events, reporting, and accountable handover between administrations.

## Launch users

- Public visitor
- Prospective member
- Member
- Secretary / membership administrator
- Treasurer
- Executive / president
- System administrator

## Launch scope

1. Preserve and refine the six public routes.
2. Store contact enquiries with a reference number and visible delivery state.
3. Provide invitation/activation-based member access.
4. Provide a public, no-invitation membership application with human eligibility review, secure status tracking and post-approval account activation; also support safe import of existing members.
5. Enforce granular server-side permissions.
6. Configure fee categories and post member assessments.
7. Record, verify, allocate, reverse, and report manual payments.
8. Provide member statements and authorised CSV exports.
9. Publish events, announcements, activities, and selected page content.
10. Record security-sensitive administrative actions.
11. Provide tested export, backup, restore, and handover procedures.

## Explicitly excluded from the initial release

Online payments, automated bank reconciliation, SMS subscriptions, automated WhatsApp messaging, native mobile apps, payroll, procurement, NIN/biometric verification, AI scoring/chatbots, social networking, and multi-tenant SaaS functionality.

## Core acceptance rules

- Every private request authenticates the session and checks permission server-side.
- A member can access only their own private record and statement.
- Posted financial records are never edited or deleted; corrections are linked reversals/adjustments.
- Repeated requests with one idempotency key cannot create duplicate postings.
- Financial totals reconcile to their underlying obligations, allocations, credits, and reversals.
- Public forms report success only after a durable write or confirmed delivery.
- The inclusive birth-date screening range is 1 January 1980 through 31 December 1982. A failed screening criterion is flagged for authorised human review and never automatically admitted.
- Only `members.approve` may approve or reject an application. Every decision records the named administrator, time and review note.
- Approval creates exactly one immutable member record and membership number; only approved applicants may activate a private account.
- Application data is never published. Status requires the application reference plus its secret verification token.
- Production contains no synthetic members or synthetic financial transactions.
- Public content with unconfirmed facts remains unpublished or visibly pending editorial approval.

## Required business decisions

The confirmed eligibility and application policy is recorded in `docs/membership-eligibility-criteria.md`. The association must still approve named approvers, the visible membership-number format, the operational notification channel, fee categories/amounts, allocation order, overpayment policy, payment verifiers/reversal authority, report visibility, public-directory policy, verified contacts, retention periods, and production cutover. These decisions are tracked in `docs/decisions.md`.
