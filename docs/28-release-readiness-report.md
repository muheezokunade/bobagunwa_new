# Release-readiness report (authoritative)

**Date:** 16 November 2026 (updated after the hardening phase) · **Revision:** see `git log -1` / `RELEASE.json` in the package; the verified revision is recorded under “Verification of the pushed revision” below

## Overall assessment

| Stage | Status |
|---|---|
| Complete and locally verified | **Yes** — every required capability is implemented and covered by executed tests on PHP 8.3 + MariaDB 11.4 (151 tests / 3,288 assertions, seven workflows, all 56 admin mutation routes × 7 roles over HTTP, 225-view browser audit) |
| Ready for protected staging deployment | **Yes, technically** — release package, runbook and installation plan (`36-staging-installation-plan.md`) ready; **blocked** by (1) deployment approval D-017, (2) hosting access for the deployer and (3) a valid SSL certificate for `v2-staging.bobagunwa.com` |
| Verified on protected staging | **No** — no access to the hosting account from the build environment |
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
| 24 | No known failing release-blocking local tests | **Yes** (151/151) | `evidence/test-run-2026-11-16-hardening.txt` |
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

1. Written approval D-017 to deploy the full application to protected staging (the earlier approval covered only the compatibility harness).
2. Hosting access (cPanel + SSH) for the named deployer.
3. A valid certificate for `v2-staging.bobagunwa.com`. No current evidence was available to this environment, and the last recorded check (8 Nov) showed none.
4. The first administrator named (D-021).
5. Host preflight passing on the server.

Client decisions still open: D-004 … D-016, D-018 … D-022, D-024 … D-026 (`23-client-decisions.md`, `29-approval-packet.md`).

## Blockers, in order

1. **D-017** written approval to deploy the full application to protected staging.
2. **Valid SSL** for `v2-staging.bobagunwa.com` (AutoSSL/DCV through the 403 protection, or Namecheap support).
3. Server verification: `php artisan boba:preflight` on the host (PHP 8.3 for web and CLI, trigger privilege, writable paths).
4. Officer acceptance on staging with synthetic data.
5. Production decisions (D-004 … D-022), backup custodians, legal review, then **D-018** cutover approval with a verified backup and rehearsed rollback.

## Remaining work

Prioritised in [30-remaining-work.md](30-remaining-work.md).

## Changes from the previous assessment

First assessment: 103 tests, 18 admin mutation routes without direct HTTP tests, no integrity alerting, no per-route catalogue. This update: see “Hardening phase — gaps resolved”. No deployment, hosting, DNS, SSL, WordPress or email change was made.

## Verification of the pushed revision

Recorded after pushing to `muheezokunade/bobagunwa_new`; see the follow-up commit.
