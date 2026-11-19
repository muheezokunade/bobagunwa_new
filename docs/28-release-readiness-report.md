# Release-readiness report (authoritative)

**Date:** 16 November 2026 (updated after the hardening phase) · **Revision:** see `git log -1` / `RELEASE.json` in the package; the verified revision is recorded under “Verification of the pushed revision” below

## Overall assessment

| Stage | Status |
|---|---|
| Complete and locally verified | **Yes** — every required capability is implemented and covered by executed tests on PHP 8.3 + MariaDB 11.4 (151 tests / 3,288 assertions, seven workflows, all 56 admin mutation routes × 7 roles over HTTP, 225-view browser audit) |
| Ready for protected staging deployment | **Yes** — approval D-017 received 16 Nov 2026; release `19c6a66` verified; deploy/rollback scripts, preflight, smoke check and the HTTPS acceptance script verified end to end in a simulated cPanel account (`37-staging-deployment-evidence.md`). (B1) valid certificate for the staging host **installed and verified 18 Nov 2026**. (B2) server access **available from the maintainer's computer**. **Next:** upgrade the existing staging deployment (`9e805f3` = `19c6a66` + InnoDB setting) to `bobagunwa-8db1405f54af.zip`; compatibility check passed and upgrade rehearsed (`37` §0b–§0c) |
| Verified on protected staging | **Partly (19 Nov 2026)** — release `ae87555` from `main` deployed on Namecheap with the guarded script (preflight 44/0/1, smoke 13/13), valid HTTPS, password protection answering 401, web permissions set by the script, and the account holder signed in through the browser (pages, CSS/JS, images and fonts served; PHP 8.3 on the web). **Acceptance run on Namecheap 19 Nov 2026: 19/19 PASS** (seven workflows, role restrictions, HSTS and Secure cookies, ModSecurity not blocking forms, responsive). **Restore drill and rollback rehearsal on Namecheap: passed** (19 Nov). **Not yet:** overnight scheduled jobs, officers' checklist (`37-staging-deployment-evidence.md` §6a–§6b) |
| Ready for production acceptance | **No** — requires staging acceptance, the approvals in `29-approval-packet.md`, backup custody and production cutover approval |
| Live | **No.** Nothing was deployed; the existing website, WordPress, DNS, SSL and email were not touched |

## Capability matrix

States: **Implemented** · **Locally tested** · **Staging verified** · **Awaiting client approval** · **Blocked** · **Production accepted**.

| # | Capability (definition of done §32) | State | Evidence |
|---|---|---|---|
| 1 | Cohesive, responsive, heritage design | Locally tested | Browser audit; screenshots |
| 2 | All public pages and links work | Locally tested | `PublicSiteTest`, browser audit |
| 3 | Official content separated from drafts | Locally tested; content **awaiting client approval** (D-015) | `ContentAndUploadSecurityTest`, `PublicSiteTest::test_no_fabricated_content…` |
| 4 | Membership application end to end | Locally tested | `ApplicationWorkflowTest`, MW-2 |
| 5 | Application review and approval | Locally tested; approvers awaiting approval (D-005) | same, `ConcurrencyTest` |
| 6 | Existing-member CSV import | Locally tested; roster awaiting approval (D-019) | `MemberImportTest`, MW-1 |
| 7 | Membership numbers protected against duplication/reuse | Locally tested; format awaiting approval (D-004) | `IntegrityTriggersTest`, `ConcurrencyTest` |
| 8 | Approved account activation | Locally tested; delivery channel awaiting approval (D-013) | `AccountLifecycleTest`, MW-3 |
| 9 | Login, reset, suspension, sessions | Locally tested | `AccountLifecycleTest` |
| 10 | Server-side role permissions | Locally tested over HTTP for every admin mutation route; assignments awaiting approval (D-022) | `AuthorisationMatrixTest`, `AdminMutationMatrixTest`, `AdminMutationBehaviourTest`, `LedgerServiceTest`, `34-admin-endpoint-coverage.md` |
| 11 | Member portal with real data | Locally tested | MW-3, MW-6, browser audit |
| 12 | Dues and levies assessed | Locally tested; amounts awaiting approval (D-006) | `LedgerServiceTest`, MW-4 |
| 13 | Manual payments recorded and verified | Locally tested; separation policy awaiting approval (D-007) | `LedgerServiceTest`, MW-5 |
| 14 | Allocations and partial payments reconcile | Locally tested; priority awaiting approval (D-009) | `LedgerServiceTest` |
| 15 | Reversals and adjustments preserve integrity | Locally tested; authority awaiting approval (D-008) | `IntegrityTriggersTest`, `LedgerServiceTest` |
| 16 | Accurate member statements, printable | Locally tested (print in Chromium emulation; paper print outstanding) | MW-6, `RemainingRoutesTest`, browser print check |
| 17 | Treasury reports reconcile | Locally tested | `LedgerServiceTest`, MW-6, `PerformanceTest` |
| 18 | Content and event administration | Locally tested | `ContentAndUploadSecurityTest`, `AdminViewsRenderTest` |
| 19 | Integrity under concurrency and invalid direct SQL | Locally tested | `ConcurrencyTest`, `IntegrityTriggersTest` |
| 20 | Encrypted backup and isolated restore | Locally tested; **off-site custody not established** (D-014) | `BackupRestoreTest`, MW-7 |
| 21 | Seven workflows pass together | Locally tested | `MandatoryWorkflowsTest` |
| 22 | Security and negative tests | Locally tested | `19-test-evidence.md` §25 table; integrity alerting (`IntegrityAlertTest`), optional TOTP (`TwoFactorTest`), one-time-link assessment (`33`) |
| 23 | Responsive and accessibility checks | Locally tested (automated); manual screen-reader/device checks outstanding | browser audit |
| 24 | No known failing release-blocking local tests | **Yes** (153/153) | `evidence/test-run-2026-11-16-staging.txt` |
| 25 | Database, deployment, operations, recovery documented; per-route catalogue | Implemented; route docs sync-tested | `docs/`, `35-route-catalogue.md`, `RouteCatalogueSyncTest` |
| 26 | Reproducible release package | Locally tested | `deploy/build-release.sh`; clean-directory deployment check |
| 27 | Namecheap staging compatibility | **Blocked** — explicitly documented external blocker | `16-namecheap-hosting-compatibility.md` |
| 28 | Outstanding client approvals recorded | Implemented | `23-client-decisions.md`, `29-approval-packet.md` |
| 29 | No unauthorised production/website changes | **Confirmed** — no access was used or available | — |
| 30 | Maintainable by another engineer | Implemented | `CLAUDE.md`, runbooks, guides, generated references |

## Hardening phase — gaps resolved

| Gap (from `30-remaining-work.md`) | Now |
|---|---|
| 18 admin POST routes without HTTP permission tests | All 56 admin mutation routes posted by every role + anonymous (403/302 exactly as declared) and without CSRF (419); behaviour test per route |
| No alert on integrity failure | Non-dismissible banner, history, receipts, acknowledgement, resolution only after a passing check, overdue detection, optional verified email with honest statuses; System Administrator sees counts but no treasury data |
| Per-route documentation | Generated catalogue for 158 routes, sync-tested |
| No manual single-member add / same-name override | Implemented with recorded justification |
| Refused-payment evidence orphans | Removed; daily orphan prune |
| UTC boundaries for reversals in period | Lagos calendar days, tested at the 23:30 UTC edge |
| No print button | Print statement with print header |
| Two-factor | Implemented, tested, **not enforced** (D-024) |
| One-time links | Assessed; clean-URL hand-off, no-referrer, no-store |
| Demo password reuse | Random per run; retired values hash-denylisted; preflight fails on demo accounts outside local |

## Security findings (this phase)

| Finding | Severity | Status |
|---|---|---|
| Retired demo password present in plain text in a local, unpushed test | Medium (credential hygiene) | **Fixed**: test uses a generated value; the string was removed from the unpushed commits before pushing; it is hash-denylisted so it can never be set |
| One-time token visible in the first access-log line and in an encrypted one-request session flash for officers | Low | Accepted, documented (`33`) |
| Alert email reports SMTP acceptance, not inbox delivery | Informational | Documented; the dashboard banner is the guaranteed channel |
| No independent penetration test | — | Outstanding (P2) |
| `composer audit` / `npm audit` | — | 0 advisories / 0 vulnerabilities |

## Readiness verdict

**The application is ready for protected staging acceptance as soon as the external prerequisites are met.** It is not ready for production. Exact staging prerequisites, in order (`36-staging-installation-plan.md`):

1. ~~Written approval D-017~~ — **received 16 Nov 2026** (staging only).
2. Server access: run the deployment from a Claude Code session on the maintainer's computer, using the logged-in Namecheap tab or SSH. The cloud build environment cannot open SSH or cPanel ports.
3. ~~A valid certificate for `v2-staging.bobagunwa.com`~~ — **installed and verified 18 Nov 2026** (Namecheap SSL 36817201, valid until 4 Jun 2027; `37-staging-deployment-evidence.md` §1a).
4. The first administrator named (D-021).
5. Host preflight passing on the server.

Client decisions still open: D-004 … D-016, D-018 … D-022, D-024 … D-026 (`23-client-decisions.md`, `29-approval-packet.md`).

## Blockers, in order

1. ~~**D-017**~~ approved 16 Nov 2026; ~~valid SSL~~ installed 18 Nov 2026; now: **server access** from the maintainer's computer (`37-staging-deployment-evidence.md` §1).
2. ~~**Valid SSL** for `v2-staging.bobagunwa.com`~~ — resolved 18 Nov 2026 via Namecheap support and the Namecheap SSL plugin.
3. Server verification: `php artisan boba:preflight` on the host (PHP 8.3 for web and CLI, trigger privilege, writable paths).
4. Officer acceptance on staging with synthetic data.
5. Production decisions (D-004 … D-022), backup custodians, legal review, then **D-018** cutover approval with a verified backup and rehearsed rollback.

## Remaining work

Prioritised in [30-remaining-work.md](30-remaining-work.md).

## Changes from the previous assessment

First assessment: 103 tests, 18 admin mutation routes without direct HTTP tests, no integrity alerting, no per-route catalogue. This update: see “Hardening phase — gaps resolved”. No deployment, hosting, DNS, SSL, WordPress or email change was made.

## Verification of the pushed revision

Revision **`41945d7`** was pushed to `muheezokunade/bobagunwa_new` (`main`). The remote head was confirmed equal to the local head. It was then cloned fresh from GitHub into an empty directory and checked as follows:

| Check in the fresh clone | Result |
|---|---|
| `composer install` from the lock file, `npm ci`, `npm run build` | pass |
| `php artisan test` (MariaDB 11.4.13) | **151 passed, 0 failed (3,288 assertions)**, 76 s |
| `composer audit` / `npm audit` | 0 advisories / 0 vulnerabilities |
| `pint --test` | pass |
| `boba:docs:reference` regenerated in the clone | byte-identical to the committed documents |
| `.env`, keys, backups, real data, or the retired demo password anywhere in history | none (history search returned 0 commits) |

The commit that records this section changes documentation only. The release package and Git bundle are built from it (`release/`, kept outside Git).
