# Authentication and security

## Accounts

* No public registration route exists (`AccountLifecycleTest::test_no_public_registration_route_exists`).
* Accounts are created only by (a) approving an application, (b) an officer issuing access to a register member who has an email address, (c) an administrator creating an officer, or (d) `php artisan boba:admin:create` for the first administrator. All start `pending_activation` with **no password**.
* Activation and reset use 32-byte random tokens; only SHA-256 hashes are stored (`account_tokens`). Activation expires in 7 days, reset in 60 minutes. Tokens are single-use; issuing a new token revokes earlier unused ones. Raw tokens are shown once to the issuing officer (or emailed if SMTP delivery is enabled) and never written to messages, audit or logs (`ApplicationWorkflowTest` asserts this).
* Passwords: bcrypt (cost 12), 12–128 characters, rejecting passwords that contain the email, membership number or common words, or with fewer than five distinct characters. Any password ever used for local demonstrations is refused by SHA-256 denylist (`boba.accounts.denied_password_sha256`), so it can never be reused on staging or production. The demo seeder now generates a random password per run (or takes `BOBA_DEMO_PASSWORD`) and refuses staging/production, and `boba:preflight` fails outside local if any `@example.test` account exists.
* Login: generic failure message (no account enumeration), timing equalised with a dummy hash check, rate limited to 5/minute per email+IP and 20/minute per IP, session ID regenerated on login.
* Suspension/inactivation of a member, or suspension/disabling of an officer, ends all their sessions; `EnsureActiveAccount` re-reads status from the database on every request so a live session stops immediately.
* Password reset and password change end other sessions and rotate the remember token. Members can sign out of other devices.
* One-time links: the `GET /account/activate/{token}` and `/account/reset/{token}` entry points move the token into the encrypted session and redirect to a clean URL; `account/*` responses send `Referrer-Policy: no-referrer` and `no-store`. Exposure assessment: [33-one-time-link-assessment.md](33-one-time-link-assessment.md).
* Optional TOTP two-factor sign-in for officers, with replay prevention, hashed single-use recovery codes, a lockout-guarded enforcement setting (off) and administrator reset for lost devices: [32-two-factor-authentication.md](32-two-factor-authentication.md).
* Recovery requests are enumeration-safe (identical response for known and unknown emails) and go to an officer queue; issuing a link requires the officer to confirm identity verification. Optional automatic email of reset links is off by default.

## Request protection

| Control | Implementation |
|---|---|
| CSRF and cross-origin posts | Laravel 13 `PreventRequestForgery` (token **and** Origin/Sec-Fetch checks) on every POST; tested by `PublicSiteTest::test_csrf_and_origin_protection…` |
| Server-side authorisation | `permission:` middleware on routes; services re-check money/membership permissions. Every one of the 56 admin mutation routes is posted by every role and anonymously in `AdminMutationMatrixTest` (403 exactly where expected) and without a CSRF token (419) — see [34-admin-endpoint-coverage.md](34-admin-endpoint-coverage.md) |
| Input validation | Laravel validation on every form; domain rules in services; DB constraints last |
| SQL injection | Query builder/Eloquent bindings throughout; the few raw statements use bound parameters or internal identifiers only |
| Output escaping / XSS | Blade `{{ }}` everywhere; Markdown rendered with `html_input=strip` and unsafe links removed; tested |
| Content-Security-Policy | `default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; frame-ancestors 'none'; form-action 'self'; object-src 'none'` — no inline script or style exists (tested on every public page) |
| Other headers | `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`; HSTS when `BOBA_FORCE_HTTPS=true` |
| Private pages | `Cache-Control: no-store, private` and `X-Robots-Tag: noindex` |
| Sessions | database driver, encrypted payload, HttpOnly, `SameSite=Lax`, `Secure` in staging/production, 60-minute lifetime |
| Rate limits | logins, applications (5/h/IP), status look-ups, enquiries, recovery, token submissions |
| Anti-automation | honeypot field and encrypted render-time stamp on public forms |
| Uploads | MIME sniffing (not extension); images re-encoded by GD (strips metadata and appended payloads); PDFs with JavaScript/launch/embedded content refused; size limits; random names; private files outside the webroot served only via authorised downloads; `public/media/.htaccess` disables execution |
| CSV exports | formula-injection neutralised (`Csv::safeCell`); exports are permissioned and audited |
| Error handling | `APP_DEBUG=false` outside local; business refusals shown as messages, not stack traces; domain refusals are not logged as errors |
| Webroot | only `public/` is served; `.htaccess` denies dotfiles and `.env/.sql/.log/.bobabk/.key` patterns |

## Integrity alerting

Failed reconciliation, missing triggers, check errors and overdue checks raise non-dismissible dashboard alerts with honest delivery records, and optional verified email without details: [31-integrity-alerting.md](31-integrity-alerting.md).

## Audit

`audit_events` (administrative actions, with redacted context and a request ID) and `security_events` (sign-in, denial, token and session events) are append-only by trigger. IP addresses are stored only as keyed HMAC fingerprints. Viewable and exportable with `audit.read`.

## Secrets and configuration

* `.env` lives outside the webroot and is never committed. Each environment has its own `APP_KEY`, database user and backup public key.
* The backup **private** key is never on the server.
* No credentials, production records, tokens or unencrypted backups are in Git (`.gitignore` covers `.env`, `/release`, `public/media/*`, storage).
* The database user should have privileges on its own database only.

## Initial administrator provisioning

```bash
php artisan boba:admin:create officer@example.org "Full Name" --role=system_admin
```

The command prints a one-time activation link to the operator's SSH terminal; the officer chooses their own password. There is no default or known password in any environment except the clearly synthetic local demo seeder, which refuses to run in staging or production.

## Nigerian data protection

See `14-privacy.md`. This document is not legal certification; legal review is a pending decision (D-016).
