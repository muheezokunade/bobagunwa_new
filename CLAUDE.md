# CLAUDE.md — Ẹgbẹ́ Bobagunwa platform

Guidance for anyone (human or AI) changing this repository. Read `docs/00-index.md` first; `docs/28-release-readiness-report.md` is the single authoritative status document.

## What this is

Laravel 13 / PHP 8.3 / MariaDB 11.4 (InnoDB) membership, finance and website platform for an Ijebu cultural association, deployed on existing **Namecheap Stellar shared hosting**. Blade + plain HTML forms (no Livewire, no SPA). No Redis, queues, Node runtime or paid services.

## Non-negotiables

1. **No new recurring cost.** Do not add paid services, CDNs, external fonts/scripts or SaaS APIs.
2. **Money is integer kobo.** Parse naira only with `App\Support\Money::parse`. Never use floats. Never store balances — derive them in `App\Services\Finance\LedgerQuery`.
3. **Posted financial records are immutable.** Corrections are adjustments or reversals. Never edit, delete, or work around the 35 triggers in `App\Support\IntegrityTriggers`; schema changes reinstall them via migration. Run `php artisan boba:ledger:verify` after any finance change.
4. **Permissions are checked server-side** with `permission:` middleware **and** inside services for money/membership decisions (`Ledger::authorise`). The System Administrator role must never gain treasury or approval permissions. Update `PermissionCatalog`, `docs/15-roles-and-permissions.md` and `AuthorisationMatrixTest` together.
5. **Every state-changing financial/import form carries an idempotency key** (`<x-idem />`) and runs inside `Idempotency::run` or a locked transaction.
6. **Strict CSP**: no inline `<script>`, no `style=` attributes, no `{!! !!}` on user data. Markdown via `Content::markdown` only.
7. **Synthetic data only** outside production. Never commit `.env`, keys, tokens, exports, backups or real personal/financial data. `boba:demo:seed` must keep refusing staging/production.
8. **Never invent official facts** (history, leaders, amounts, statistics, contact details). Unapproved content stays draft; decisions go in `docs/23-client-decisions.md`.
9. **Do not touch production, WordPress, DNS, SSL or email** without explicit, recorded authorisation. Staging deployment and production cutover each need separate approval.
10. **Never report a test, browser observation or hosting result you did not actually run.**

## Working locally

```bash
composer install && npm ci && npm run build
cp .env.example .env && php artisan key:generate   # point DB_* at a MariaDB 11.4 database
php artisan migrate && php artisan boba:demo:seed && php artisan serve
php artisan test                                    # needs database bobagunwa_test on MariaDB 11.4
./vendor/bin/pint                                   # formatting (Laravel preset)
```

A MariaDB 11.4 container: `docker run -d --network host -e MARIADB_ROOT_PASSWORD=… mariadb:11.4`.

## Conventions

* Controllers are thin; business rules live in `app/Services`. Throw `App\Exceptions\DomainRuleException` for user-facing refusals (rendered back to the form, not logged as errors).
* Audit every consequential action with `App\Support\Audit::record` (context is redacted automatically); security events with `Audit::security`.
* Views use the components in `resources/views/components` (`x-field`, `x-select`, `x-check`, `x-textarea`, `x-status`, `x-money`, `x-idem`, `x-page-head`, `x-empty`). One `h1` per page. Pass `prefix` when a field name repeats on a page.
* Times stored UTC; display with `App\Support\Format` (Africa/Lagos).
* After changing migrations or routes run `php artisan boba:docs:reference` to regenerate `docs/04-database-schema.md` and `docs/17-route-inventory.md`.
* Keep documentation current in the same commit as the change, and update `docs/28-release-readiness-report.md` honestly.
* Commit messages: imperative summary, explanatory body.
