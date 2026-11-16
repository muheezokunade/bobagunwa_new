# Release-readiness report (authoritative)

**Date:** 16 November 2026 · **Revision:** see `git log -1` / `RELEASE.json` in the package

## Overall assessment

| Stage | Status |
|---|---|
| Complete and locally verified | **Yes** — every required capability is implemented and covered by executed tests on PHP 8.3 + MariaDB 11.4 (103 tests, seven workflows, 210-view browser audit) |
| Ready for protected staging deployment | **Yes, technically** — release package and runbook ready; **blocked** by (1) deployment approval D-017 and (2) a valid SSL certificate for `v2-staging.bobagunwa.com` |
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
| 10 | Server-side role permissions | Locally tested; assignments awaiting approval (D-022) | `AuthorisationMatrixTest`, `LedgerServiceTest` |
| 11 | Member portal with real data | Locally tested | MW-3, MW-6, browser audit |
| 12 | Dues and levies assessed | Locally tested; amounts awaiting approval (D-006) | `LedgerServiceTest`, MW-4 |
| 13 | Manual payments recorded and verified | Locally tested; separation policy awaiting approval (D-007) | `LedgerServiceTest`, MW-5 |
| 14 | Allocations and partial payments reconcile | Locally tested; priority awaiting approval (D-009) | `LedgerServiceTest` |
| 15 | Reversals and adjustments preserve integrity | Locally tested; authority awaiting approval (D-008) | `IntegrityTriggersTest`, `LedgerServiceTest` |
| 16 | Accurate member statements | Locally tested | MW-6 |
| 17 | Treasury reports reconcile | Locally tested | `LedgerServiceTest`, MW-6, `PerformanceTest` |
| 18 | Content and event administration | Locally tested | `ContentAndUploadSecurityTest`, `AdminViewsRenderTest` |
| 19 | Integrity under concurrency and invalid direct SQL | Locally tested | `ConcurrencyTest`, `IntegrityTriggersTest` |
| 20 | Encrypted backup and isolated restore | Locally tested; **off-site custody not established** (D-014) | `BackupRestoreTest`, MW-7 |
| 21 | Seven workflows pass together | Locally tested | `MandatoryWorkflowsTest` |
| 22 | Security and negative tests | Locally tested | `19-test-evidence.md` §25 table |
| 23 | Responsive and accessibility checks | Locally tested (automated); manual screen-reader/device checks outstanding | browser audit |
| 24 | No known failing release-blocking local tests | **Yes** (103/103) | `evidence/test-run-2026-11-16.txt` |
| 25 | Database, deployment, operations, recovery documented | Implemented | `docs/` |
| 26 | Reproducible release package | Locally tested | `deploy/build-release.sh`; clean-directory deployment check |
| 27 | Namecheap staging compatibility | **Blocked** — explicitly documented external blocker | `16-namecheap-hosting-compatibility.md` |
| 28 | Outstanding client approvals recorded | Implemented | `23-client-decisions.md`, `29-approval-packet.md` |
| 29 | No unauthorised production/website changes | **Confirmed** — no access was used or available | — |
| 30 | Maintainable by another engineer | Implemented | `CLAUDE.md`, runbooks, guides, generated references |

## Blockers, in order

1. **D-017** written approval to deploy the full application to protected staging.
2. **Valid SSL** for `v2-staging.bobagunwa.com` (AutoSSL/DCV through the 403 protection, or Namecheap support).
3. Server verification: `php artisan boba:preflight` on the host (PHP 8.3 for web and CLI, trigger privilege, writable paths).
4. Officer acceptance on staging with synthetic data.
5. Production decisions (D-004 … D-022), backup custodians, legal review, then **D-018** cutover approval with a verified backup and rehearsed rollback.

## Remaining work

Prioritised in [30-remaining-work.md](30-remaining-work.md).

## Changes from the previous assessment

First assessment for this application (V1's readiness statements in `historical/v1/` do not apply).
