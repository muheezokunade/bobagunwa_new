# Architecture

## Decision

**Laravel 13 (PHP 8.3) + Blade + MariaDB 11.4 (InnoDB)**, server-rendered HTML forms with progressive-enhancement JavaScript, deployed as one application on the association's existing **Namecheap Stellar** shared hosting. No new recurring service is required.

Why (evaluated against the brief's preference and the hosting facts):

| Option | Outcome |
|---|---|
| V1 React/Vinext on Cloudflare Workers + D1 | Rejected by the client's no-new-subscription constraint; V1's own measurements showed password hashing (89–174 ms CPU) cannot run on Workers Free (10 ms). |
| Laravel + **Livewire** (V2 approach) | Not adopted. Livewire's JSON update endpoint adds a second request path that shared-hosting ModSecurity rules sometimes block, and it was not needed: every workflow is a form post. Removing it removes that risk entirely. |
| **Laravel + Blade forms** (chosen) | Runs on PHP 8.3 / Apache-LiteSpeed / MariaDB with no persistent processes, no Redis, no queue worker, no Node at runtime. Every action works without JavaScript; CSRF and origin checks are built in. |

The V2 Laravel repository was not available in this environment, so nothing was copied from it.

## Shape

```text
Browser ──HTTPS──> Apache/LiteSpeed (cPanel) ──> public/index.php (Laravel)
                                                  │
  Middleware: SecurityHeaders (CSP, no-store) → session → PreventRequestForgery (CSRF + Origin)
              → EnsureActiveAccount (re-reads status every request) → auth → staff/member → permission:<key>
                                                  │
  Controllers (thin: validate, call service, redirect)
     Public/  Auth/  Portal/  Admin/
                                                  │
  Services (all business rules; re-check permissions for money and membership decisions)
     ApplicationService · MemberService · MemberImportService · AccountService · MessageService
     Finance/AssessmentService · Finance/PaymentService · Finance/LedgerQuery · Finance/FinanceImportService
     UploadService · EnquiryService · BackupService
                                                  │
  MariaDB 11.4 InnoDB: FKs, CHECKs, generated-column unique keys, 30 integrity triggers
  storage/app/private (outside webroot): evidence, documents, encrypted backups
  public/media: re-encoded images only (execution disabled)
```

## Key decisions

| Topic | Decision | Reason |
|---|---|---|
| Money | integer kobo, decimal-string parsing | no floating-point error |
| Balances | always derived | brief: no editable balance field as source of truth |
| Isolation | `READ COMMITTED` + parent-row locks in triggers | correct concurrent allocation; tested with forked processes |
| Idempotency | `idempotency_keys` row inserted inside the same transaction as the work; a form carries one key | double-clicks, retries and concurrent duplicates return the original result |
| Sessions, cache, rate limits | database driver | no Redis on shared hosting |
| Queue | `sync` | no background workers on shared hosting; nothing long-running |
| Scheduled work | `php artisan schedule:run` from cPanel cron | daily ledger check and backup, hourly pruning |
| Email | optional SMTP via the existing hosting mailbox; default is officer-assisted delivery with recorded attestation | no paid email service; never claim delivery that did not happen |
| Anti-automation | honeypot + encrypted timing stamp + per-IP rate limits | no third-party CAPTCHA; works under a strict CSP |
| Fonts/assets | self-hosted Noto Serif (Yoruba diacritics), Vite build committed to release | no external CDN; CSP `default-src 'self'` |
| Content | Markdown with raw HTML stripped and unsafe links removed | officers cannot inject script |
| Uploads | images re-encoded with GD; PDFs scanned for active content; private files outside webroot | prohibited content rejected or neutralised |
| Backups | pure-PHP consistent snapshot dump + libsodium sealed box + XChaCha20-Poly1305 secretstream | server never holds the decryption key; no `mysqldump`/`exec` dependency |
| Public webroot | `public/`; optionally relocated with `BOBA_PUBLIC_PATH` | works with cPanel subdomain document roots; no symlinks required |

## Directories

| Path | Contents |
|---|---|
| `app/Http/Controllers/{Public,Auth,Portal,Admin}` | Thin controllers |
| `app/Http/Middleware` | Security headers, active-account, staff, member, permission gates |
| `app/Services`, `app/Services/Finance` | Business rules |
| `app/Support` | Money, normalisation, CSV, audit, tokens, settings, permission catalogue, integrity triggers |
| `app/Console/Commands` | `boba:*` operational commands |
| `database/migrations` | 6 migrations; triggers and reference data included |
| `resources/views` | Blade: `layouts`, `components`, `public`, `auth`, `portal`, `admin`, `partials`, `errors` |
| `resources/css/app.css`, `resources/js/app.js` | Design system; progressive enhancement only |
| `tests/` | PHPUnit (MariaDB) and `tests/browser` (Playwright + axe) |
| `deploy/` | Release builder, environment and PHP ini examples |
| `docs/` | This documentation |

## Console commands

| Command | Purpose |
|---|---|
| `boba:admin:create {email} {name} --role=` | First administrator / officer provisioning with a one-time activation link (no default password) |
| `boba:preflight` | Hosting compatibility verification |
| `boba:ledger:verify` | Trigger presence + reconciliation exceptions (scheduled daily) |
| `boba:backup:keygen` | Custodian key pair (run on a trusted computer) |
| `boba:backup:create --keep=14` | Encrypted backup (scheduled daily when a public key is configured) |
| `boba:backup:restore file --key-file= --target= --confirm-isolated` | Verified restore into an empty isolated database |
| `boba:content:baseline` | Load existing public-site copy as **unpublished** drafts |
| `boba:demo:seed` | Synthetic demonstration data (refused in staging/production) |
| `boba:docs:reference` | Regenerate schema and route reference documents |
