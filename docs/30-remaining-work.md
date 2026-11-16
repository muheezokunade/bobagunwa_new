# Remaining work — prioritised

Review of the project against `03-requirements-traceability.md` and the original brief, 16 November 2026, after the code was preserved in `muheezokunade/bobagunwa_new` (commit `4a75865`). Priority: **P0** blocks staging · **P1** blocks production · **P2** strongly recommended before or soon after launch · **P3** improvement.

## A. Client approvals (owner: association — see `29-approval-packet.md`)

| P | Item | Ref |
|---|---|---|
| P0 | Written approval to deploy the full application to protected staging | D-017 |
| P0 | Authorise a request to Namecheap support for the free SSL certificate on `v2-staging.bobagunwa.com` | — |
| P0 | Name the first administrator | D-021 |
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

## B. Staging (owner: technical maintainer; only after A-P0)

| P | Item |
|---|---|
| P0 | Run `php artisan boba:preflight` on the host: PHP 8.3 for **web and CLI**, extensions, MariaDB version, `CREATE TRIGGER` privilege, writable private paths |
| P0 | Obtain a **valid** certificate (AutoSSL with the `.well-known` allowance through the 403 protection); never test login over an invalid certificate |
| P0 | Deploy with `09-deployment-runbook.md`; keep the staging 403/IP protection; synthetic data only |
| P0 | Verify ModSecurity does not block form posts (application, enquiry, content editor, imports) |
| P0 | Install cron `schedule:run`; confirm the daily ledger check and backup run |
| P1 | Staging restore drill into an isolated database with the custodian key |
| P1 | Officer acceptance on staging using `20/21/22` guides; record results in `19-test-evidence.md` |
| P1 | Test optional SMTP via the existing mailbox only if the association wants email delivery |

## C. Security and quality engineering (owner: developer)

| P | Item | Why |
|---|---|---|
| P1 | **HTTP-level permission tests for the 18 admin POST routes not yet exercised over HTTP** — e.g. `finance.payments.reverse`, `finance.payments.reject`, `finance.obligations.adjust`, `finance.allocations.reverse`, `members.access`, `access.link`, `events.*`, `enquiries.update`, `backups.store` — extend `AuthorisationMatrixTest` to POST every admin route as every role | Brief §16 asks for permission tests directly against HTTP endpoints; these routes rely on declared middleware plus service-level checks that **are** tested, but the routes themselves are not |
| P1 | **Alert on integrity failure**: a failed `boba:ledger:verify` or a missing trigger should raise a dashboard banner for treasury/administrators (and optionally email via the existing mailbox) | Today it is only recorded in the audit history |
| P2 | Two-factor authentication (TOTP, free) for officers holding `finance.*`, `access.manage`, `settings.manage` or `backups.manage` | Financial and personal-data system; not in the brief's required list but prudent |
| P2 | Show one-time links directly in the response instead of carrying them through the (encrypted) session flash | Reduces where raw tokens exist, however briefly |
| P2 | Independent security review / penetration test on staging (OWASP ASVS L1) | No external review has been done |
| P2 | Manual accessibility: NVDA/VoiceOver journeys, low-end Android, Firefox/Safari, 200 % zoom, printed statements | Only automated axe/keyboard checks so far |
| P2 | Lighthouse on the staging origin (performance, SEO, best practice) | Not measurable without hosting access |
| P3 | Clean up unreferenced private files left when a payment with evidence is refused | Harmless but untidy |
| P3 | Use Lagos calendar dates for "reversals in period" boundaries | ≤ 1 h edge difference |

## D. Documentation (owner: developer)

| P | Item |
|---|---|
| P1 | Per-route purpose, validation, states and acceptance criteria (brief §27): `17-route-inventory.md` is generated with method, path, handler and access only; add a curated per-workflow table or extend the generator with route descriptions |
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
| P2 | Add a single member manually (today: one-row import) and an officer override for genuine same-name members | — |
| P3 | Refunds / partial reversals | D-010 |
| P3 | Accounting-period closing | treasury policy |
| P3 | Automatic allocation by approved priority | D-009 |
| P3 | Visible "Print" button on statements (print styles already exist; browser print works) | — |

## Not to be done without separate authorisation

Deploying to Namecheap, changing WordPress, DNS, SSL, email or production files, importing real member or financial data, and production cutover.
