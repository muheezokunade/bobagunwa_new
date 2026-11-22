# Ẹgbẹ́ Bobagunwa Okunrin Akile Ijebu — website and association platform

Public heritage website, membership applications, member register, member portal, dues and levies ledger with manual payment verification, content and events administration, and encrypted backups — built to run on the association's existing Namecheap Stellar hosting with no new subscriptions.

**Status:** engineering complete and locally verified on PHP 8.3 + MariaDB 11.4; **not deployed**. Staging deployment needs approval and a valid SSL certificate; production needs separate approval. See [`docs/28-release-readiness-report.md`](docs/28-release-readiness-report.md).

## Stack

Laravel 13 · PHP 8.3 · MariaDB 11.4 (InnoDB, 35 integrity triggers) · Blade · Vite (CSS/JS compiled at build time) · self-hosted Noto Serif · PHPUnit · Playwright + axe-core.

## Quick start (local, synthetic data)

```bash
composer install
npm ci && npm run build
cp .env.example .env && php artisan key:generate
# set DB_* for a MariaDB 11.4 database, then:
php artisan migrate
php artisan boba:demo:seed        # synthetic officers and members; prints a generated password once
php artisan serve                 # http://127.0.0.1:8000
```

Demo accounts (local only, refused in staging/production): `admin@`, `membership@`, `secretariat@`, `treasurer@`, `verifier@`, `executive@`, `applicant@example.test`. The seeder generates a random password on each run and prints it; set `BOBA_DEMO_PASSWORD` to choose one (needed for the browser audit). No fixed password exists in the code.

## Tests

```bash
php artisan test        # 162 tests on MariaDB 11.4 (database bobagunwa_test)
```

Includes direct-SQL ledger invariants, forked-process concurrency, the seven mandatory end-to-end workflows, encrypted backup/restore with tamper and wrong-key rejection, an authorisation matrix, an HTTP matrix of every admin mutation route × role (+ CSRF), integrity alerting, two-factor and volume tests. `php artisan boba:docs:reference` regenerates the schema, route inventory, endpoint coverage matrix and route catalogue. Browser audit: `node tests/browser/responsive-a11y.cjs http://127.0.0.1:8000`.

## Deploy

`deploy/build-release.sh` → upload → follow [`docs/09-deployment-runbook.md`](docs/09-deployment-runbook.md); run `php artisan boba:preflight` on the server first.

## Documentation

Start at [`docs/00-index.md`](docs/00-index.md). Contributor rules: [`CLAUDE.md`](CLAUDE.md).
