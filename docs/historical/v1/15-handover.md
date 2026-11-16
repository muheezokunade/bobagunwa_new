# Handover status

## Completed and verified

- Repository/live-site audit and migration direction.
- Product, architecture, schema, permissions, finance, design, security, deployment, cost, backup, and decision foundations.
- D1/SQLite-compatible domain migration with executable validation.
- Integer-kobo ledger rules, assessment/payment/allocation/reversal/adjustment APIs and expanded unit/live tests.
- All 54 public/auth/member/admin page files and route registry entries.
- Better Auth on D1, scoped portal/admin access, public writes, application approval/activation, member import/status, administrator-assisted recovery and role assignment with live local tests.
- Separate historical-transaction/opening-balance imports, reconciled reports and permissioned CSV export.
- Authorised draft/edit/publish/archive for articles, announcements, events, activities and versioned website content.
- Guarded versioned AES-256-GCM/scrypt D1 protected-export/isolated-restore commands and a passing 48-table authenticated restore, tamper-rejection and ledger-reconciliation drill.
- Turnstile on public enquiry, application and recovery forms with mandatory server verification.
- Cloudflare/Vinext production build and current public route checks verified.

## Implemented but pending external configuration

- Local D1 binding, migrations, safe test variables and synthetic fixtures. Staging/production D1, Turnstile widgets and secrets do not exist in this repository.

## Pending association approval

See `docs/decisions.md`, `docs/content-verification-checklist.md` and `docs/pre-deployment-checklist.md`, especially membership/finance authority, official data, administrator identity verification/link delivery, legal text, paid-plan fallback, and production cutover.

## Deferred phases

The deployed authentication benchmark is complete and rules out Workers Free for the current secure password work. Full 54-route responsive/accessibility/security testing, association-owned staging, remote backup custody/restore, production launch and final administrator training remain deferred. Enquiry assignment/resolution and typed settings administration now pass locally.

## Known risks

See `docs/14-known-issues.md`. The local platform is functional with synthetic records, but it is not yet approved to hold real member or financial data.
