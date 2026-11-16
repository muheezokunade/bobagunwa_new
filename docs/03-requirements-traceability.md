# Requirements traceability matrix

Status vocabulary: **Implemented** · **Locally tested** (automated test executed and passing on MariaDB 11.4) · **Staging verified** · **Awaiting client approval** · **Blocked** · **Production accepted**. Nothing here is staging-verified or production-accepted.

Test abbreviations: `IT` IntegrityTriggersTest · `LS` LedgerServiceTest · `CC` ConcurrencyTest · `AW` ApplicationWorkflowTest · `MI` MemberImportTest · `AL` AccountLifecycleTest · `AM` AuthorisationMatrixTest · `AV` AdminViewsRenderTest · `PS` PublicSiteTest · `CU` ContentAndUploadSecurityTest · `BR` BackupRestoreTest · `HI` HistoricalImportTest · `MW` MandatoryWorkflowsTest · `PF` PerformanceTest · `BA` browser audit (`tests/browser/responsive-a11y.cjs`) · `U` unit tests.

| Brief § | Requirement | Implementation | Evidence | Status |
|---|---|---|---|---|
| 2 | No new recurring services | Laravel on existing hosting; no paid APIs | `25-cost-and-service-register.md` | Implemented |
| 4 | Independent repository, synthetic data only | `Bobagunwa-Claude` Git repo; demo seeder refuses staging/production | release check run | Locally tested |
| 9 | Public pages with metadata, headings, responsive, accessible, no fake content | `Public\SiteController`, `resources/views/public` | PS, BA (205 views, 0 failures) | Locally tested; content awaiting approval |
| 9 | Leadership directory maintained by officers, approved only | `leaders` + content admin | AV, PS (`no_fabricated_content`) | Locally tested; names awaiting approval |
| 9 | News, events, activities database-backed | `posts`, `events`, `activities` | CU, PS | Locally tested |
| 9 | Protected contact enquiry | `ContactController`, `EnquiryService`, honeypot/timing/rate limit | PS | Locally tested |
| 9 | Privacy/terms pages | managed pages + labelled working drafts | PS | Awaiting legal review |
| 10 | Application without account; validation; declarations; reference + private token | `ApplicationService::submit` | AW, MW-2 | Locally tested |
| 10 | 1980–1982 inclusive; outside flagged for human review | `ApplicationService::screen` | AW (four boundary dates) | Locally tested |
| 10 | Rate limiting, duplicate detection, privacy | throttles, generated-column unique keys, generic look-up failures | AW, CC | Locally tested |
| 10 | Submission creates no member/account | service design | AW, MW-2 | Locally tested |
| 10 | Officer queue, search/filter, inspect, flags, request info, approve/reject with reasons, history, no duplicate approval, one member | `Admin\ApplicationController`, `ApplicationService` | AW, AM, CC (concurrent approval), MW-2 | Locally tested |
| 11 | Unique, permanent, immutable, never-reused numbers; concurrency-safe; distinct from DB id; configurable; production issuance gated | `MembershipNumberService`, `membership_numbers` + triggers, Settings gate | IT, CC, MI | Locally tested; format awaiting approval |
| 11 | Register search/filter/profile/correct/suspend/reactivate/export/history/duplicates/incomplete | `Admin\MemberController`, `MemberService` | AV, AM, AL | Locally tested |
| 12 | Bulk import with template, mapping, validation, preview, conflicts, atomic, idempotent, receipt, no accounts; 2/86/100/500/1,000 rows | `MemberImportService` | MI (all volumes), MW-1 | Locally tested; real roster awaiting approval |
| 13 | Login/logout, activation, expiring single-use tokens, password change, recovery, reset, replay prevention, session invalidation, throttling, suspension, admin management, security logs, no self-registration | `AccountService`, `LoginController`, `AccountController`, `EnsureActiveAccount` | AL, MW-3 | Locally tested |
| 13 | No paid email; admin-assisted delivery; attestation separate from issuance; never claim delivery | `MessageService`, `outbound_messages` | AW, AV | Locally tested; channel awaiting approval |
| 14 | Member portal with own data only; no invented balances | `PortalController` (member from session) | AM (`member_cannot_reach_another…`), MW-6 | Locally tested |
| 15 | Admin areas operate end to end | `Admin\*` controllers | AV (37 pages), AM | Locally tested |
| 16 | Explicit RBAC; sysadmin without treasury; membership officers without finance; no escalation; last admin protected; tested via HTTP and services | `PermissionCatalog`, `RequirePermission`, services, `AccessController` | AM, LS (`permissions_are_enforced_inside_finance_services`) | Locally tested; assignments awaiting approval |
| 17.1 | Integer kobo; transactions; separated concepts; derived balances | migrations 000003, `LedgerQuery` | U (Money), LS | Locally tested |
| 17.2 | Authorised, atomic, unique id, auditable, duplicate rejection, concurrency, rollback | services + `Idempotency` + triggers | LS, CC, IT | Locally tested |
| 17.3 | Recorded ≠ verified; approval-gated configurable policy, safe default | `PaymentService::verify`, trigger L6, setting | LS, IT, MW-5 | Locally tested; policy awaiting approval |
| 17.4 | Allocation rules (8 bullets) tested by direct DB operations | triggers L8–L12 | IT, CC (incl. direct SQL under RC and RR) | Locally tested |
| 17.5 | Reversals/adjustments preserve history and consistency; no double reversal | `PaymentService::reverse*`, triggers L13–L16 | IT, LS | Locally tested |
| 17.6 | Statements reconciling to ledger; print and export | `LedgerQuery::statement`, `partials/statement` | LS, MW-6 | Locally tested |
| 17.7 | Treasury reports with documented definitions | `ReportController`, `06-financial-rules.md` | LS, MW-6, PF | Locally tested |
| 17.8 | Guarded historical import with approved source, second-officer confirmation and receipt; no invented history | `FinanceImportService` | HI, AV | Locally tested; records awaiting approval |
| 18 | Content management with drafts/publish and history | `ContentController`, `page_revisions` | CU | Locally tested |
| 19 | Events, RSVP, member visibility, officer reporting; internal announcements | `EventController`, portal | PS, CU, AV | Locally tested |
| 20 | Security controls list | `13-authentication-and-security.md` | PS, CU, AL, AM | Locally tested |
| 21 | Encrypted backup, key management, isolated restore with triggers and reconciliation, wrong key/tamper rejection, cleanup | `BackupService`, commands | BR, MW-7 | Locally tested; custody awaiting approval |
| 22 | Low memory, indexed queries, pagination, no N+1, bounded imports, 100/500/1,000 volumes | indexes, paginators, constant-query pages | PF, MI | Locally tested |
| 23 | All test categories on PHP 8.3 + MariaDB 11.4 | `tests/` | `19-test-evidence.md` | Locally tested |
| 24 | Seven workflows pass together | `MandatoryWorkflowsTest` | MW (90 assertions) | Locally tested |
| 25 | 17 additional acceptance scenarios | see `19-test-evidence.md` table | various | Locally tested |
| 26 | Responsive 320–1440 and accessibility checks | CSS, components | BA; manual checks listed as outstanding | Locally tested (automated) |
| 27 | Documentation set | `docs/` | this matrix | Implemented |
| 30 | Namecheap staging deployment | runbook, preflight, release | release check locally | **Blocked** (no access; approval and SSL pending) |
