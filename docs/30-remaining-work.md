# Remaining work — prioritised

Review of the project against `03-requirements-traceability.md` and the original brief. First written 16 November 2026 after the code was preserved in `muheezokunade/bobagunwa_new` (commit `4a75865`); **updated after the hardening phase the same day** — completed engineering items are listed under “Resolved in the hardening phase”. Priority: **P0** blocks staging · **P1** blocks production · **P2** strongly recommended before or soon after launch · **P3** improvement.

## A. Client approvals (owner: association — see `29-approval-packet.md`)

| P | Item | Ref |
|---|---|---|
| ~~P0~~ | ~~Written approval to deploy the full application to protected staging~~ — **received 16 Nov 2026** | D-017 |
| ~~P0~~ | ~~Valid SSL for `v2-staging.bobagunwa.com`~~ — **installed and verified 18 Nov 2026** | `37` §1a |
| P0 | Upgrade staging from the unknown commit `9e805f3` to a release from `main`, keeping the existing database and `staging.env`, after the read-only compatibility check (server access now available from the maintainer's computer) | `37` §0a |
| ~~P0~~ | ~~Authorise a request to Namecheap support for the free SSL certificate~~ — done; certificate 36817201 issued and installed | — |
| P0 | Name the first administrator | D-021 |
| P0 | Grant hosting access (cPanel + SSH) to the named deployer for staging only | — |
| P1 | Membership-number format; named approvers; officer role assignments | D-004, D-005, D-022 |
| P1 | Dues/levy categories and amounts; recording vs verification separation and limits; reversal authority | D-006, D-007, D-008 |
| P1 | Account-link delivery channel and identity-verification procedure | D-013 |
| P1 | Two backup custodians and an off-site location | D-014 |
| P1 | Legal review of privacy notice and terms; retention periods | D-016 |
| P1 | Public content: about, history, leadership (with consent), contact details, Yoruba orthography | D-015 |
| P1 | Production cutover approval (domain route, WordPress coexistence, date) | D-018 |
| P2 | Verified member roster (86 provisional names) and identity fields | D-019 |
| P2 | Allocation priority, overpayment/refund policy, executive report access | D-009, D-010, D-011 |
| P2 | Historical financial records and cut-off date, if wanted | D-020 |
| P2 | Require two-factor sign-in for privileged officers (after staging rehearsal) | D-024 |
| P2 | Integrity-alert email via the existing mailbox, and alert recipients | D-025 |
| P2 | Procedure for distinct members sharing a full name | D-026 |

## B. Staging (owner: technical maintainer; only after A-P0)

| P | Item |
|---|---|
| P0 | Follow `36-staging-installation-plan.md`: gates G1–G5, then `php artisan boba:preflight` on the host: PHP 8.3 for **web and CLI**, extensions, MariaDB version, `CREATE TRIGGER` privilege, writable private paths |
| ~~P0~~ | ~~Obtain a **valid** certificate~~ — done 18 Nov 2026 (Namecheap SSL plugin; renew before 4 Jun 2027) |
| P0 | Deploy with `09-deployment-runbook.md`; keep the staging 403/IP protection; synthetic data only |
| P0 | Verify ModSecurity does not block form posts (application, enquiry, content editor, imports) |
| P0 | Install cron `schedule:run`; confirm the daily ledger check and backup run |
| P1 | Staging restore drill into an isolated database with the custodian key |
| P1 | Officer acceptance on staging using `20/21/22` guides, including a deliberate integrity alert (acknowledge → re-check → resolve) and a printed statement; record results in `19-test-evidence.md` |
| P1 | Confirm web requests run PHP 8.3 (Settings page shows PHP version and SAPI) |
| P1 | Test optional SMTP via the existing mailbox only if the association wants email delivery |

## C. Security and quality engineering (owner: developer)

| P | Item | Why |
|---|---|---|
| P2 | Independent security review / penetration test on staging (OWASP ASVS L1) | No external review has been done |
| P2 | Manual accessibility: NVDA/VoiceOver journeys, low-end Android, Firefox/Safari, 200 % zoom, statements printed on paper | Only automated axe/keyboard checks and Chromium print emulation so far |
| P2 | Lighthouse on the staging origin (performance, SEO, best practice) | Not measurable without hosting access |
| P2 | Rehearse two-factor enrolment and lost-device reset with real officers on staging before D-024 | Enforcement must not lock anyone out |
| P3 | QR-code rendering for two-factor enrolment (pure-PHP, no service) if officers find manual key entry difficult | Usability only |
| P3 | Optional: replace the session-flash hand-off of officer-facing one-time links with a one-shot server-side store | Residual risk accepted in `33-one-time-link-assessment.md` |

## D. Documentation (owner: developer)

| P | Item |
|---|---|
| P2 | Record staging preflight output, deployment log and acceptance results in `19-test-evidence.md`; move items to "Staging verified" in `28-release-readiness-report.md` |
| P3 | Screenshots for the officer guides once staging content exists |

## E. Operations (owner: association + maintainer)

| P | Item |
|---|---|
| P1 | Backup key ceremony (`boba:backup:keygen` on a trusted computer), configure the public key on the server, first off-site copy, quarterly restore drills |
| P1 | Monthly maintenance routine: `composer audit`, `npm audit`, patch updates, full test suite, rebuilt release |
| P2 | Weekly reconciliation check and monthly bank/cash comparison by the treasurer (`21-treasurer-guide.md`) |
| P2 | Quarterly review of officer roles; offboarding at each change of executive |

## F. Functional gaps (owner: developer, after policy decisions)

| P | Item | Depends on |
|---|---|---|
| P3 | Refunds / partial reversals | D-010 |
| P3 | Accounting-period closing | treasury policy |
| P3 | Automatic allocation by approved priority | D-009 |

## Resolved in the hardening phase (16 Nov 2026)

| Former item | Resolution | Evidence |
|---|---|---|
| HTTP-level permission tests for admin POST routes | All 56 admin mutation routes posted by 7 roles + anonymous, and without CSRF (419); route-specific behaviour tests for all 56; generated coverage matrix | `AdminMutationMatrixTest`, `AdminMutationBehaviourTest`, `RemainingRoutesTest`, `34-admin-endpoint-coverage.md` |
| Alert on integrity failure | Non-dismissible banner, history, receipts, acknowledgement, resolution only after a passing check, overdue detection, daily reminders, optional verified email with honest statuses, no treasury data for the sysadmin | `IntegrityAlertTest`, `31-integrity-alerting.md` |
| Two-factor authentication | TOTP with replay prevention, recovery codes, admin reset, lockout-guarded enforcement (off) | `TwoFactorTest`, `32-two-factor-authentication.md` |
| One-time links in URLs/session | Assessed; clean-URL hand-off, `no-referrer`, `no-store`; residual risks documented | `AccountLifecycleTest`, `33-one-time-link-assessment.md` |
| Per-route documentation | Generated catalogue for all 158 routes (purpose, roles, validation, data, states, security, acceptance, tests, status) kept in sync by a test | `35-route-catalogue.md`, `RouteCatalogueSyncTest` |
| Add a single member manually; same-name override | `Members → Add a member`; `Name match` confirmation in imports | `ManualMemberTest` |
| Evidence files left by refused payments | Removed on refusal; daily orphan prune; rejected-payment evidence kept deliberately | `AdminMutationBehaviourTest::test_refused_payment_does_not_leave_its_evidence_file` |
| Lagos boundaries for reversals in period | `CONVERT_TZ` to +01:00 and Lagos day bounds in reports and statements | `LedgerServiceTest::test_reversal_period_boundaries_follow_lagos_calendar_days` |
| Print button on statements | **Print statement** with print header | browser audit print check |
| Demo password reuse | Random per-run demo password; retired values hash-denylisted; preflight fails on demo accounts outside local | `AccountLifecycleTest`, `boba:preflight` |

## Not to be done without separate authorisation

Deploying to Namecheap, changing WordPress, DNS, SSL, email or production files, importing real member or financial data, and production cutover.
