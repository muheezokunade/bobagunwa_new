# Namecheap Stellar hosting compatibility

## Status: **not yet verified on the server — external blocker**

This build environment had **no access** to the association's hosting: outbound connections to `bobagunwa.com`, `v2-staging.bobagunwa.com` and the Netlify site were refused by the build environment's network policy (16 Nov 2026, `curl` → connection refused at the proxy), and no SSH/cPanel credentials were provided. **Nothing was deployed, changed or tested on Namecheap.** WordPress, DNS, SSL, email, PHP settings and production files were not touched.

The application has instead been verified against the same software versions locally and packaged with a server-side verification command.

## Historical facts (from earlier audits — must be re-verified)

| Fact | Historical value | Impact on this application | Verify with |
|---|---|---|---|
| Plan | Namecheap Stellar shared | No daemons, Redis, Node runtime or workers used | — |
| Database | MariaDB 11.4.13 | **Developed and tested on MariaDB 11.4.13** (identical) | `boba:preflight` |
| PHP | 8.3 available for staging vhost; account default 8.1 | Requires 8.3 for **web and CLI** | cPanel MultiPHP Manager + `boba:preflight` |
| Memory | ~1 GB physical, 20 entry processes | Measured peak ≤ 75 MB per request/import (1,000-row import) | `boba:preflight` |
| Storage | 20 GB | Release 72 MB unpacked; backups a few MB each | cPanel |
| Web server | Apache/LiteSpeed + PHP-FPM | `public/.htaccess` rewrite rules; no other modules required | page load |
| SSH | port 21098 | Needed for migrations, cron set-up, restore drills | `ssh -p 21098` |
| Cron | available with restrictions | One entry every 15 min (or the shortest allowed) runs `schedule:run`; tasks are daily/hourly | cPanel Cron Jobs |
| Staging host | `v2-staging.bobagunwa.com`, docroot `/home/bobakkgn/bobagunwa-v2-webcheck/public`, protected with HTTP 403 rules | Laravel's `public/` maps directly onto this docroot | cPanel Domains |
| SSL | Free certificate **not issued**: re-checked **16 Nov 2026**, the server still presents `CN=*.web-hosting.com` (SSL.com TLS Issuing RSA CA R1, valid 3 Nov 2026 – 20 May 2027) | **Blocks** secure cookies and therefore safe staging login | cPanel SSL/TLS Status; Namecheap support |
| Account 2FA | Enabled | — | — |

## Requirements matrix (verified locally)

| Requirement | Local evidence |
|---|---|
| PHP 8.3 with pdo_mysql, sodium, gd (JPEG/PNG/WebP), intl, mbstring, fileinfo, openssl | `boba:preflight` PASS (PHP 8.3.6) |
| MariaDB 11.4 InnoDB with `TRIGGER` privilege | Full suite on 11.4.13; preflight creates/drops a probe trigger |
| No `exec()`, `mysqldump`, symlinks, Redis, queue worker or Node at runtime | Backups are pure PHP; public path is configurable; release ships compiled assets and `vendor/` |
| Upload limits | Recommend `upload_max_filesize=12M`, `post_max_size=16M` via `deploy/user.ini.example` |
| Release reproducibility | `deploy/build-release.sh` → zip + SHA-256; deployed into a clean directory and database, migrated, preflighted and served successfully |

## Preflight coverage (16 Nov 2026)

`boba:preflight` now also checks:
- that it runs on an empty database before migrations;
- CHECK-constraint enforcement and transaction rollback;
- trigger creation **and** enforcement, and READ COMMITTED isolation;
- `proc_open` (the scheduler starts commands with it) and the web execution limit;
- `.env` outside the webroot.

Verified in a simulated cPanel account (44 PASS, 0 FAIL); not yet on Namecheap.

## Known hosting risks

1. **SSL for the staging hostname** — must be resolved (AutoSSL needs the `.well-known/acme-challenge` path reachable through the 403 protection; a narrow allow rule was previously tested). Do not accept an invalid certificate; do not purchase SSL; do not remove the protection globally. If AutoSSL still fails, escalate to Namecheap support with the evidence (domain, docroot, DCV error from cPanel → SSL/TLS Status).
2. **Account default PHP 8.1** — set PHP 8.3 per domain in MultiPHP Manager and call PHP 8.3 explicitly for CLI/cron (e.g. `/opt/cpanel/ea-php83/root/usr/bin/php` — confirm the path with `ls /opt/cpanel/`).
3. **ModSecurity** — may block some form posts containing words like `SELECT` or `<script>` in free text. Test the application and enquiry forms on staging; request a targeted rule exception from support if needed.
4. **Cron interval restrictions** — scheduled tasks tolerate a 15–30 minute cron.
5. **Binary logging** — if enabled, trigger creation may need `log_bin_trust_function_creators` (support request). Preflight detects this.
