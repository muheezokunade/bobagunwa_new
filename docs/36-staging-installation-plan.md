# Protected staging installation plan — `v2-staging.bobagunwa.com`

**Nothing in this plan has been carried out.** Running any step on Namecheap needs the association's written approval to deploy the full application to protected staging (D-017) **and** hosting access given to the person doing it. The earlier authorisation covered only the isolated compatibility harness. Production is out of scope (D-018). WordPress, the main domain, DNS, existing SSL certificates and mailboxes must not be touched.

Detailed commands: [09-deployment-runbook.md](09-deployment-runbook.md). Hosting facts and risks: [16-namecheap-hosting-compatibility.md](16-namecheap-hosting-compatibility.md).

## 0. Go / no-go gates (all must be “yes” before step 1)

| # | Gate | Evidence to record |
|---|---|---|
| G1 | Written approval D-017 for full-application staging deployment | message/date in `23-client-decisions.md` |
| G2 | Hosting access for the deployer (cPanel + SSH on port 21098) granted by the account owner | who, when, scope |
| G3 | **Valid TLS certificate** for `v2-staging.bobagunwa.com`: cPanel → SSL/TLS Status shows it as valid, and `openssl s_client -connect v2-staging.bobagunwa.com:443 -servername v2-staging.bobagunwa.com </dev/null 2>/dev/null \| openssl x509 -noout -subject -issuer -dates` names the staging host, not `*.web-hosting.com` | command output in `19-test-evidence.md` |
| G4 | Release package built from the latest `main` and its SHA-256 verified | file name + hash |
| G5 | Staging 403/IP or password protection remains in place, with only the `.well-known/acme-challenge` allowance | `.htaccess` excerpt (no secrets) |

**SSL evidence status (16 Nov 2026):** no certificate evidence for the staging host was available to this build environment, and the hosts could not be reached (network policy). The last recorded check (8 Nov) found **no** valid certificate for the staging host: the server presented `*.web-hosting.com`. G3 is therefore **open**. Do not accept an invalid certificate, do not buy one, and do not remove the protection globally to obtain one. Escalate to Namecheap support if AutoSSL domain validation fails.

## 1. Pre-deployment host preflight (no switch-over yet)

Unpack the release into `~/releases/bobagunwa-<rev>`, outside the served directory. Create the empty staging database and user (ALL PRIVILEGES on that database only). Write `.env` from `deploy/env.staging.example` (`APP_ENV=staging`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, backup path outside `public_html`), then:

```bash
php83 artisan key:generate --force
php83 artisan boba:preflight
```

Every required check must PASS:

* PHP ≥ 8.3 on the CLI;
* the extensions it lists, with sodium secretstream and GD JPEG/PNG;
* memory ≥ 128M;
* `APP_KEY` set and debug off;
* MariaDB ≥ 10.6 with InnoDB;
* the CREATE TRIGGER probe;
* no `@example.test` accounts, and the demo seeder refused;
* writable private paths outside the webroot, and built assets present.

Record the full output. **Stop on any FAIL.** Typical remedies are PHP 8.3 per domain in MultiPHP Manager, and a support request for `log_bin_trust_function_creators` if trigger creation is refused under binary logging.

Also confirm that **web** requests use PHP 8.3, not only the CLI. After the switch in step 3, Administration → Settings shows the PHP version and SAPI that served the page (visible to `settings.manage` only); it must read 8.3.x and not `cli`.

## 2. Migrate and verify integrity

```bash
php83 artisan migrate --force
php83 artisan boba:ledger:verify        # expects 35 triggers, zero exceptions
php83 artisan config:cache && php83 artisan route:cache && php83 artisan view:cache
```

## 3. Switch the staging document root

Move the release into `~/bobagunwa-v2-webcheck` (keep the previous directory for rollback; see the runbook). Re-apply the 403 protection.

## 4. Smoke and security checks (record each result)

| Check | Expected |
|---|---|
| `https://v2-staging.bobagunwa.com/` from an allowed IP | 200, valid certificate, CSP + `X-Frame-Options: DENY` + `nosniff` headers |
| Same URL from a non-allowed IP | 403 (protection still active) |
| `/.env`, `/storage/logs/laravel.log`, `/composer.json` | 403/404 |
| Session cookie after visiting `/login` | `Secure; HttpOnly; SameSite=Lax` |
| Application form, enquiry form, content editor, CSV import preview (synthetic) | no ModSecurity block; if blocked, note the rule ID for a targeted support request |
| `/account/activate/<token>` | redirects to `/account/activate`; response has `Referrer-Policy: no-referrer`, `Cache-Control: no-store` |

## 5. First administrator, cron, backups

1. `php83 artisan boba:admin:create <named officer email> "<name>" --role=system_admin`. Use the person named in D-021, and send the printed link privately. **Never** use the local demo password or demo accounts on staging.
2. Add the cron entry `*/15 * * * * cd ~/bobagunwa-v2-webcheck && /path/to/php83 artisan schedule:run`.
3. The next day, confirm a passing integrity check under Integrity alerts → recent checks, and an encrypted backup in the backup register (once the public key is configured).
4. Backup key ceremony on a trusted computer (`boba:backup:keygen`). Put only the **public** key on the server. Then run a restore drill into an empty `<user>_restoredrill` database (`10-backup-and-recovery.md`).

## 6. Officer acceptance (synthetic data only)

Run the seven workflows with officers, following the guides in `20-administrator-guide.md`, `21-treasurer-guide.md` and `22-content-publishing-guide.md`. Also:

* trigger an integrity alert deliberately (the maintainer drops and reinstalls one trigger on staging only), then acknowledge, re-check and resolve it;
* optionally enrol two-factor and rehearse a lost-device reset (enforcement stays off until D-024);
* print a statement.

Record pass/fail per step in `19-test-evidence.md`, and move capability rows to “Staging verified” in `28-release-readiness-report.md` only for steps actually observed.

## 7. Rollback

Restore the previous directory name (runbook “Rollback”). Staging uses its own database, so nothing else is affected. Drop the staging database only on the association's instruction.

## Information still needed from the association

D-017 approval. Who will hold hosting access. The first administrator's name and email (D-021). Whether to request SSL help from Namecheap support. Two backup custodians (D-014). Whether to test email alerts through the existing mailbox (D-025).
