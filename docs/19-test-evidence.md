# Executed test evidence

All results below were **executed** on 16 November 2026 in the build environment: PHP 8.3.6 (CLI), MariaDB **11.4.13** (Docker image `mariadb:11.4`), Laravel 13.42.0, Chromium via Playwright. Synthetic data only. **None of this is staging or production evidence.** Historical V1 results in `historical/v1/` are not evidence for this application.

Raw artefacts in `docs/evidence/`:

| File | Contents |
|---|---|
| `test-run-2026-11-16-hardening.txt` | **Latest** full PHPUnit output (151 tests, hardening phase) |
| `test-run-2026-11-16.txt` | Earlier full PHPUnit output (103 tests, before hardening) — superseded |
| `admin-mutation-matrix-2026-11-16.json` | Status code returned to every role and to anonymous callers for each of the 56 admin mutation routes |
| `acceptance-evidence-2026-11-16.json` | Machine-readable result of the seven mandatory workflows |
| `responsive-a11y-2026-11-16.json` | Every page view of the browser audit with overflow, axe and heading results |
| `preflight-local-2026-11-16.json` | `boba:preflight` output on the build machine |
| `screens/*.png` | Representative screenshots (public, portal, administration; desktop and phone); `statement-print-media.png` is the member statement rendered with print media |

## Summary (latest run, hardening phase, 16 Nov 2026 ≈ 11:20–11:30 UTC)

| Check | Result |
|---|---|
| PHPUnit full suite on MariaDB 11.4.13 | **151 passed, 0 failed (3,288 assertions)**, 74 s — `evidence/test-run-2026-11-16-hardening.txt` |
| Seven mandatory workflows together | **Pass** — all 7 `"result": "pass"` in `acceptance-evidence-2026-11-16.json` |
| Admin mutation HTTP matrix | **56 routes × 7 roles + anonymous**: 302 for anonymous, 403 exactly where the role lacks the permission, no 403/404/405/500 where it holds it; **419** for all 56 without a CSRF token (`AdminMutationMatrixTest`, 507 assertions) |
| Admin mutation behaviour | every one of the 56 routes has a route-specific HTTP test (`docs/34-admin-endpoint-coverage.md`); 158/158 named routes are called by at least one HTTP test (`docs/35-route-catalogue.md`) |
| Integrity alerting | 7/7 (`IntegrityAlertTest`): passing run, missing trigger, reconciliation failure and recurrence, overdue and errored checks, verified email accepted without sensitive content, failed delivery recorded, unconfigured channel refused |
| Two-factor | 7/7 (`TwoFactorTest`) incl. RFC 6238 vectors, replay rejection, lockout guard, admin reset |
| Auth lifecycle | 12/12 (`AccountLifecycleTest`) incl. clean-URL token hand-off and demo-password denylist |
| Concurrency (forked processes) | **6/6 pass** (also re-run on its own: 6 passed, 41 assertions) |
| Direct-SQL integrity | **9/9 pass** |
| Backup/restore | **6/6 pass** |
| Route documentation sync | **2/2 pass** (`RouteCatalogueSyncTest`) |
| Browser audit: 45 pages × 5 widths (320, 375, 768, 1024, 1440), incl. `/admin/alerts` (two roles), `/admin/members/create`, `/admin/account` | **225 page views, 0 failures**: no horizontal overflow, 0 axe WCAG 2.1 A/AA violations, exactly one `h1`, all inputs labelled, 0 console/CSP errors — `responsive-a11y-2026-11-16.json` |
| Keyboard | mobile menu opens with Enter, Escape closes and returns focus, skip link visible on focus, focus ring visible |
| Printed statement (Chromium print media) | Print statement button shown; print header visible; navigation and controls hidden; table visible |
| `composer audit` / `npm audit` | **0 advisories / 0 vulnerabilities** |
| `pint --test` | pass |
| Production asset build | pass (`npm run build`) |
| Local `boba:preflight` | all required checks PASS; backup public key WARN (not configured locally) — `preflight-local-2026-11-16.json` |
| Fresh clone of pushed revision `41945d7` | dependencies installed from lock files, assets built, **151/151 tests pass**, audits clean, generated docs identical (`28-release-readiness-report.md`) |
| Release package | rebuilt from the final pushed revision with `deploy/build-release.sh`; SHA-256 recorded alongside |

Per-class counts: Unit 22 (Money 16, Normalize/CSV 6) · MandatoryWorkflows 1 · AccountLifecycle 12 · TwoFactor 7 · BackupRestore 6 · Concurrency 6 · IntegrityTriggers 9 · RouteCatalogueSync 2 · HistoricalImport 3 · LedgerService 14 · AdminMutationBehaviour 14 · AdminMutationMatrix 2 · AdminViewsRender 1 · AuthorisationMatrix 7 · ContentAndUploadSecurity 4 · Performance 3 · PublicSite 5 · RemainingRoutes 8 · ApplicationWorkflow 5 · ManualMember 5 · MemberImport 8 · IntegrityAlert 7.

## The seven mandatory workflows (`MandatoryWorkflowsTest`)

| # | Workflow | Verified |
|---|---|---|
| 1 | Existing-member import | 2 rows previewed valid; commit created 2 members and **0 login accounts**; repeat commit returned the original receipt; re-upload flagged 2/2 duplicates; anonymous → sign-in; treasurer → 403; imported member cannot sign in |
| 2 | Application and approval | missing declaration rejected; duplicate application rejected; private tracking works, wrong code "not found"; no member before approval; system administrator → 403; membership officer approves; **exactly one member and one number**; repeat approval rejected |
| 3 | Account activation | login refused before activation; activation succeeds; portal 200; member → admin 403; **replayed token refused; expired token refused** |
| 4 | Assessment of ₦50,000 (synthetic fixture) | membership officer → 403; one obligation of 5,000,000 kobo; same-key replay returns original; second posting refused |
| 5 | Manual ₦20,000 bank transfer | member recording → 403; pending until verified; treasurer cannot verify (→ 403); verifier verifies; ₦20,000.01 over-allocation refused; allocation replay returns original; further allocation refused; exactly one payment and one allocation |
| 6 | Statement reconciliation | **₦50,000 assessed, ₦20,000 allocated, ₦30,000 outstanding** identical in member summary, statement (closing and position), portal pages, treasury report, balances CSV (`50000.00,0.00,20000.00,30000.00`) and raw MariaDB sums; 0 ledger exceptions |
| 7 | Encrypted backup and isolated restore | libsodium sealed box + XChaCha20-Poly1305 secretstream; **wrong key rejected; tampered file rejected**; restore into a separate database: 46 tables, 30 triggers restored, 81 foreign keys checked, 0 orphans, finance totals equal, ₦30,000 outstanding, 3 members and all audit events present |

## Additional acceptance scenarios (brief §25)

| Scenario | Result | Test |
|---|---|---|
| Member reads another member's record | Denied (403; portal scoped to session member) | `AuthorisationMatrixTest::test_member_cannot_reach_another_members_records` |
| Membership officer attempts treasury posting | Denied | `LedgerServiceTest::test_permissions_are_enforced…`, MW-4 |
| General administrator attempts payment reversal | Denied | same; `AuthorisationMatrixTest::test_system_administrator_has_no_treasury…` |
| Duplicate payment submitted concurrently | One payment; all replays return it | `ConcurrencyTest::test_duplicate_payment_submitted_concurrently…` |
| Two requests exhaust the same payment | Exactly one succeeds (8 rounds) | `ConcurrencyTest::test_two_requests_cannot_exhaust…` |
| Cross-member allocation by direct SQL | Rejected by trigger/composite keys | `IntegrityTriggersTest::test_cross_member_allocation…` |
| Reversal attempted twice | Refused (service and trigger) | `IntegrityTriggersTest`, `LedgerServiceTest::test_payment_reversal…` |
| Failed multi-record transaction | Fully rolled back | `IntegrityTriggersTest::test_failed_multi_record_transaction…` |
| Suspended member login | Denied; live session ended | `AccountLifecycleTest::test_suspended_member…` |
| Expired reset token | Denied | `AccountLifecycleTest::test_reset_tokens_expire…` |
| Unapproved applicant activation | Denied (no account exists) | `AccountLifecycleTest::test_unapproved_applicant…` |
| Imported member login without activation | Denied | `AccountLifecycleTest::test_imported_member_cannot_log_in…`, MW-1 |
| Unauthorised data export | Denied (members, balances, statements, audit) | `AuthorisationMatrixTest::test_unauthorised_exports_are_denied`, `RemainingRoutesTest::test_officer_exports_templates_and_forms` |
| Payment rejected, then verification attempted | Refused; never counts | `AdminMutationBehaviourTest::test_payment_rejection` |
| Payment reversal replayed with the same key / repeated with a new key | Replay returns the first result; second reversal refused; ledger exceptions empty | `AdminMutationBehaviourTest::test_payment_reversal_with_dependent_allocation_and_replay` |
| Stale category edit | Refused (version conflict) | `AdminMutationBehaviourTest::test_assessment_discard_and_category_update_with_version_conflict` |
| Financial batch cancelled through the member-import route (and vice versa) | 404 | `AdminMutationBehaviourTest::test_finance_import_cancel_is_kind_scoped` |
| Content id edited through another type / revision of another page published | 404 | `AdminMutationBehaviourTest::test_content_update_unpublish_and_gallery_item` |
| Member changes protected fields via the portal | Ignored; only permitted fields change | `RemainingRoutesTest::test_member_profile_update_is_limited_and_versioned` |
| RSVP over capacity / to a draft event | Refused / 404 | `RemainingRoutesTest::test_member_rsvp_capacity_and_unpublished_events` |
| Refused payment with evidence | Uploaded file removed | `AdminMutationBehaviourTest::test_refused_payment_does_not_leave_its_evidence_file` |
| Integrity failure seen by a System Administrator | Alert visible (counts only); finance still 403 | `IntegrityAlertTest::test_missing_trigger_raises_one_critical_alert…` |
| Tampered encrypted backup | Rejected before any restore (flipped byte, truncation, missing final block, header change, non-backup) | `BackupRestoreTest` |
| Wrong decryption key | Rejected | `BackupRestoreTest::test_wrong_key_is_rejected` |
| Upload with prohibited content | PDF with JavaScript, PHP disguised as JPEG, HTML refused; JPEG with trailing PHP re-encoded and payload removed | `ContentAndUploadSecurityTest` |
| Invalid CSV rows | Reported per row; commit blocked; existing records unchanged | `MemberImportTest::test_invalid_and_duplicate_rows…`, `HistoricalImportTest` |

## Volume and performance (`PerformanceTest`, `MemberImportTest`)

Local MariaDB, single request each; shared hosting will be slower and was **not** load-tested (the brief forbids disruptive load tests on shared hosting).

| Page | 100 members | 500 members | 1,000 members | Queries (all volumes) |
|---|---|---|---|---|
| Member register | 27 ms | 10 ms | 11 ms | 5 |
| Finance overview | 9 ms | 13 ms | 23 ms | 15 |
| Reports (all member balances) | 38 ms | 127 ms | 258 ms | 23 |
| Payments list | 11 ms | 11 ms | 18 ms | 5 |
| Member ledger | 9 ms | 10 ms | 20 ms | 11 |
| Admin overview | 10 ms | 14 ms | 28 ms | 17 |
| Portal dashboard / statement | 8 / 5 ms | 8 / 4 ms | 15 / 10 ms | 9 / 8 |
| Balances CSV | 5 ms | 7 ms | 18 ms (1,001 rows) | — |

Query counts are constant across volumes (no N+1). Peak memory ≤ 79 MB including the test harness. Member import (preview + commit): 2 rows 0.02 s · 86 rows 0.28 s · 100 rows 0.22 s · 500 rows 1.1 s · 1,000 rows 2.7 s.

## Failures found and fixed during testing

| Found by | Defect | Fix |
|---|---|---|
| Trigger smoke test | MariaDB error 1442: locking read of the trigger's own table | Parent-row locks + statement reads; documented limitation |
| `LedgerServiceTest` | Replayed payment submission refused by the duplicate-reference check | Duplicate checks moved inside the idempotent transaction |
| `AccountLifecycleTest` | Suspension relied on cached user state | Middleware re-reads status every request |
| Admin views review | Import cancel/commit accepted the wrong batch kind; enquiry assignable to non-officers | Kind checks; officer-only assignment |
| Guides review | No "Record delivery" link after approval; rejected payment blocked its bank reference forever; officers without member record could not change password; leadership group order; gallery uploads lacked rights confirmation | All fixed and tested where applicable |
| Browser audit | Tables overflowing narrow screens (grid `1fr` minimum, escaping `.sr-only` text), unfocusable scroll regions, header wrapping | CSS fixes; 210/210 views clean |
| Full-suite run | Concurrency tests leaking committed data | Per-test clean-up |
| Hardening review | Retired demo password present in plain text in an unpushed test | Test rewritten to use a generated value and the hash-only denylist; string removed from unpushed history before the push |
| Staging plan review | Settings page did not show which PHP served web requests (needed for staging verification) | PHP version, SAPI and database version shown to `settings.manage` |

## Not executed

Anything on Namecheap (no access); manual screen-reader and real-device testing; Firefox/Safari; Lighthouse on the final origin; printed statements on paper; SMTP delivery through the association mailbox (alert email was tested only against Laravel's mail fakes and a failing transport); independent penetration test.
