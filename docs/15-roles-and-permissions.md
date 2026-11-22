# Roles and permissions

Source of truth: `app/Support/PermissionCatalog.php`, synchronised into the database by the reference-data migration. Roles are **templates**; every route checks a named permission (`permission:` middleware) and every financial or membership decision re-checks it inside the service. Hidden menu items are never relied upon. **Final assignments require association approval (decisions D-005, D-007, D-008).**

## Permissions (26)

| Group | Permission | Allows |
|---|---|---|
| Membership | `applications.review` | Review applications, request information, record a recommendation |
| | `applications.decide` | Final decision (approve/reject) on an application recommended by **another** officer |
| | `members.read` | View the register |
| | `members.manage` | Correct permitted fields; suspend/reactivate |
| | `members.import` | Preview and commit existing-member imports |
| | `members.export` | Export the register |
| | `accounts.issue` | Issue member activation/reset links |
| Finance | `finance.read` | View assessments, payments, balances, reports |
| | `finance.categories.manage` | Manage dues/levy categories |
| | `finance.assessments.manage` | Create, preview, post assessments |
| | `finance.payments.record` | Record payments |
| | `finance.payments.verify` | Verify/reject payments |
| | `finance.allocate` | Allocate verified payments |
| | `finance.adjust` | Waivers, corrections, cancellations |
| | `finance.reverse` | Reverse allocations and payments |
| | `finance.import` | Import approved historical records |
| | `finance.reports.export` | Export financial reports and statements |
| Content | `content.manage` | Edit pages, news, notices, activities, leadership, media, documents |
| | `content.publish` | Publish, unpublish, archive |
| | `events.manage` | Manage events and RSVPs |
| | `enquiries.manage` | Handle enquiries |
| Administration | `access.manage` | Officer accounts, roles, account recovery |
| | `audit.read` | Audit and security history |
| | `settings.manage` | Organisation settings and launch approvals |
| | `backups.manage` | Create and download encrypted backups |
| | `integrity.alerts` | See integrity alerts (counts and check outcomes only — no member, amount or reference details), run the check, acknowledge and resolve |

## Role templates

| Permission | Member | System Administrator | Membership Initiator | Membership Officer | Secretariat | Treasurer | Payment Verifier | Executive |
|---|---|---|---|---|---|---|---|---|
| applications.review | | | ✓ | ✓ | | | | |
| applications.decide | | | | ✓ | | | | |
| members.read | | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| members.manage | | | | ✓ | | | | |
| members.import | | | | | ✓ | | | |
| members.export | | | | | ✓ | | | |
| accounts.issue | | | | ✓ | | | | |
| finance.read | | | | | | ✓ | ✓ | ✓ |
| finance.categories.manage | | | | | | ✓ | | |
| finance.assessments.manage | | | | | | ✓ | | |
| finance.payments.record | | | | | | ✓ | | |
| finance.payments.verify | | | | | | | ✓ | |
| finance.allocate | | | | | | ✓ | | |
| finance.adjust | | | | | | ✓ | | |
| finance.reverse | | | | | | | ✓ | |
| finance.import | | | | | | ✓ | | |
| finance.reports.export | | | | | | ✓ | | ✓ |
| content.manage / publish | | | | | ✓ | | | |
| events.manage, enquiries.manage | | | | | ✓ | | | |
| access.manage, settings.manage, backups.manage | | ✓ | | | | | | |
| integrity.alerts | | ✓ | | | | ✓ | ✓ | ✓ |
| audit.read | | ✓ | | | | | | ✓ |

Members have no permissions; their portal access is scoped to the member record linked to their own account (resolved from the session, never from a URL parameter).

## Critical separations (tested)

* **System Administrator has no treasury or approval powers** — cannot record, verify, allocate, reverse or post (`AuthorisationMatrixTest::test_system_administrator_has_no_treasury_or_approval_powers`, `LedgerServiceTest::test_permissions_are_enforced_inside_finance_services`).
* **Two-step membership approval** (22 Nov 2026): the Membership Initiator (the General Secretary) reviews and recommends; the Membership Officer (chair of the screening committee) gives the final decision, and never on his own recommendation. Asking the applicant for more information clears the recommendation. Setting `membership.two_step_approval` (default on).
* **Membership Officer sees no financial details** (finance summary hidden and finance routes forbidden).
* **Recording vs verifying vs reversing** are different permissions held by different default roles; the two-officer rule additionally stops one person who holds both from verifying their own entry.
* **No self-escalation**: officers cannot change their own roles or account status.
* **Last administrator**: a change that would leave no active account holding `access.manage` is refused; access changes are serialised with a lock.
* Role changes end the affected officer's sessions so they take effect immediately.
* `AuthorisationMatrixTest::test_every_role_reaches_exactly_the_admin_pages_it_is_permitted` requests every parameter-free administration page as every role (8 × 25) and asserts 200 only where the permission is held.

## Integrity alerts and the System Administrator

`integrity.alerts` is granted to the System Administrator so that a technical failure (missing trigger, check error, overdue check) is never invisible to the person who maintains the system. It gives **no treasury access**: the alert pages show alert type, severity, counts of exceptions and timestamps only, and every finance route still returns 403 for the System Administrator (`IntegrityAlertTest::test_missing_trigger_raises_one_critical_alert_and_cannot_be_resolved_until_fixed`, `AdminMutationMatrixTest`). Alert emails carry no details at all.

## Two-factor enforcement

Two-factor sign-in applies to **administrator accounts** (`TwoFactorService::ADMINISTRATIVE`: `access.manage`, `settings.manage`, `backups.manage`), as agreed at the 22 Nov 2026 demo (D-024); members are never asked for it. The setting `security.two_factor_include_finance` extends it to every `finance.*` write permission. Enforcement itself stays off until the administrators have enrolled (the switch refuses otherwise). See [32-two-factor-authentication.md](32-two-factor-authentication.md).
