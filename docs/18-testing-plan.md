# Testing plan

All database tests run against **MariaDB 11.4.13 / InnoDB** (`bobagunwa_test`), the same engine as the Namecheap server, because triggers, generated columns, CHECK constraints and row locking cannot be proven on SQLite. All data is synthetic.

## How to run

```bash
# one-time: a MariaDB 11.4 server with database bobagunwa_test and a user with ALL on bobagunwa% (restore tests create/drop databases)
php artisan test                                  # full suite (~45 s)
php artisan test tests/Feature/Acceptance         # the seven mandatory workflows
php artisan test tests/Feature/Concurrency        # forked-process races (needs pcntl)
./vendor/bin/pint --test                          # formatting
composer audit && npm audit                       # dependency advisories
npm run build                                     # production assets
# browser audit (needs the demo data and a running server)
export BOBA_DEMO_PASSWORD=$(openssl rand -hex 12)
php artisan boba:demo:seed && php artisan serve
node tests/browser/responsive-a11y.cjs http://127.0.0.1:8000 docs/evidence/responsive-a11y-<date>.json
```

## Suites

| Category (brief §23) | Where |
|---|---|
| Unit (money, normalisation, CSV, dates, CSV injection) | `tests/Unit` |
| Migrations, schema, triggers, direct-SQL invariants, rollback | `tests/Feature/Database/IntegrityTriggersTest` (every test also migrates the full schema) |
| Finance services, idempotency, reversals, adjustments, permissions in services, reconciliation vs raw SQL | `tests/Feature/Finance/LedgerServiceTest` |
| Concurrency (forked processes, separate connections) | `tests/Feature/Concurrency/ConcurrencyTest` |
| Membership application, review, approval, privacy | `tests/Feature/Membership/ApplicationWorkflowTest` |
| Import (2/86/100/500/1,000 rows), invalid rows, duplicates, replay, production gate | `tests/Feature/Membership/MemberImportTest` |
| Authentication lifecycle, tokens, suspension, reset, rate limiting | `tests/Feature/Auth/AccountLifecycleTest` |
| Authorisation matrix (every role × every admin page), exports, escalation, last admin | `tests/Feature/Http/AuthorisationMatrixTest` |
| Every admin view renders with data; unique IDs; escaping | `tests/Feature/Http/AdminViewsRenderTest` |
| Public pages, headers/CSP, SEO metadata, no fabricated content, enquiries, CSRF | `tests/Feature/Http/PublicSiteTest` |
| Content publication, XSS, uploads with prohibited content | `tests/Feature/Http/ContentAndUploadSecurityTest` |
| Backup/restore, wrong key, tampering, truncation, isolation | `tests/Feature/Backup/BackupRestoreTest` |
| Seven mandatory workflows together | `tests/Feature/Acceptance/MandatoryWorkflowsTest` (writes `storage/logs/acceptance-evidence.json`) |
| Volume (100/500/1,000 members), query counts, N+1 guard | `tests/Feature/Http/PerformanceTest` |
| Responsive (320/375/768/1024/1440), axe WCAG 2.1 A/AA, keyboard, console/CSP | `tests/browser/responsive-a11y.cjs` |

## Test credentials

`tests/TestCase.php` creates fixture accounts with the password `synthetic-password-123`. They exist only inside the rolled-back transactions of the `bobagunwa_test` database (or are dropped by `migrate:fresh` in the committed-data suites), so the value cannot sign in to any real environment. It is deliberately kept for readable tests.

## Not automated (manual acceptance required)

Screen-reader journeys (NVDA/VoiceOver), real-device testing on low-end Android phones, cross-browser (Firefox, Safari), 200 % zoom review, print layout of statements on paper, and everything on the Namecheap server (`16-namecheap-hosting-compatibility.md`).
