# Staging deployment evidence — `v2-staging.bobagunwa.com`

**Authorisation:** Mr Ibrahim approved deploying the complete application to the protected staging site `v2-staging.bobagunwa.com` (recorded 16 Nov 2026, D-017). This does **not** cover the main website, WordPress, DNS, existing certificates, email, production records or cutover.

**Status on 18 Nov 2026 (corrected):**
- **Already deployed on Namecheap on 16 Nov 2026**, from release `9e805f3`, which is `19c6a66` plus one database setting (§0, §0b). The earlier statement "not yet deployed on Namecheap" was wrong.
- **Upgrade ready:** release `bobagunwa-8db1405f54af.zip` from `main` carries that setting; the read-only server check found the upgrade compatible, and it was rehearsed in place on the simulated account (§0c).
- **B1 (SSL) resolved 18 Nov 2026**: the staging host now has a valid certificate (§1a).
- **B2 (server access) resolved for the maintainer's computer**: the maintainer's Claude Code session reads the server through the logged-in cPanel. The cloud build environment still cannot reach the server directly.
- **Open:** staging still runs `9e805f3`, whose commit is not in the repository. Acceptance results count only after the upgrade to `8db1405`.
- **Staging deployment tooling:** built and verified end to end in a simulated cPanel account (§3).
- **Next step:** continue in a Claude Code session on the maintainer's own computer, which can use the logged-in Namecheap tab or SSH. Use the prompt kept with this document (§7).

## 0. Existing Namecheap deployment (reported 18 Nov 2026)

Reported by the maintainer from the server (deploy log and `~/bobagunwa-config/` listing, read through cPanel by the Claude Code session on the maintainer's computer). **Not independently verified from the build environment**: the site answers 403 to it.

| Item | Reported |
|---|---|
| Deployed | 16 Nov 2026 with `deploy/staging-deploy.sh`, stamp `20261116T123304Z` |
| Release | `bobagunwa-9e805f3da77e.zip`, commit `9e805f3da77e497744aef075118434416b83759f`, built 16 Nov 2026 12:28 UTC |
| PHP CLI | 8.3.36 |
| Preflight | passed; one advisory (no backup key) |
| Migrations | "Nothing to migrate" (so the staging database had already been migrated before this run) |
| Ledger verification | 35 triggers verified |
| Protection | placed; `staging-protection.htaccess` is currently `Require all denied` (consistent with the 403 "Compatibility endpoint is closed." seen from outside) |
| Smoke check | **13/13 passed** (corrected 18 Nov 2026: the first report read a truncated log and listed only 5 pages; the other 8 were `/login`, `/account/recover`, `/privacy`, `/sitemap.xml`, `/portal` 302, `/admin` 302, `/admin/finance` 302 and the 404 probe) |
| Previous version | kept at `~/bobagunwa-v2-webcheck.previous-20261116T123304Z` |
| Private configuration | `~/bobagunwa-config/` holds `staging.env`, `staging-protection.htaccess` and `user.ini`, all mode 600; a staging database and `APP_KEY` exist |

**Provenance gap.** Commit `9e805f3` is not in `muheezokunade/bobagunwa_new` (checked 18 Nov 2026: not on any branch of the remote, not in the build environment's history or reflog, not among the simulation packages). It was built 27 minutes after `19c6a66`. *(An earlier version of this paragraph called it a different code version because its smoke check seemed to cover 5 pages; that came from a truncated log and is withdrawn.)* The manifest comparison in §0b shows exactly what it contains: `19c6a66` plus one change to `config/database.php`. Who built it is still unrecorded. Nothing observed on it counts as acceptance evidence; staging is upgraded in place to `8db1405` from `main`, which includes the same change.

### 0a. Upgrade check before replacing `9e805f3` (read-only, on the server)

The deployed package carries its own `MANIFEST.sha256` and `RELEASE.json`, so the difference can be measured on the server without the missing commit:

```bash
cat ~/bobagunwa-v2-webcheck/RELEASE.json
mkdir -p ~/bobagunwa-compare && chmod 700 ~/bobagunwa-compare && cd ~/bobagunwa-compare
sort -k2 ~/bobagunwa-v2-webcheck/MANIFEST.sha256 > old.txt
unzip -p ~/releases/bobagunwa-19c6a66c0ef0.zip MANIFEST.sha256 | sort -k2 > new.txt
join -1 2 -2 2 -a1 -a2 -e MISSING -o 0,1.1,2.1 old.txt new.txt | awk '$2!=$3' > changed.txt
wc -l < changed.txt; grep -E 'database/migrations|IntegrityTriggers' changed.txt
cd ~/bobagunwa-v2-webcheck && /opt/alt/php83/usr/bin/php artisan migrate:status --no-ansi
```

Expected migrations in `19c6a66` (SHA-256 of each file):

| File | SHA-256 |
|---|---|
| `2026_11_16_000001_create_identity_and_access_tables.php` | `0ebb60dd1cfbee8ecbc1e7756abdb2decd25586d07fdb1d50e81f7b802c99a9e` |
| `2026_11_16_000002_create_membership_tables.php` | `e61c50ef9b2fc386993268121857ba9a12511d761d9b46ebfe101f4ea7b6e867` |
| `2026_11_16_000003_create_finance_tables.php` | `d8049ee8e1d8801bbafe04222b23d6b49937709af7c60fcd2c808fc679f7a776` |
| `2026_11_16_000004_create_content_and_communication_tables.php` | `e7e603404b1605aef28aaa34062c7476f5c3be984fb472fe43789b450daef084` |
| `2026_11_16_000005_create_integrity_triggers.php` | `dc60ae85f87d9b993a9cb5c953558dd8bfdf0528a0cf557e873a0af40f9a8daf` |
| `2026_11_16_000006_seed_reference_data.php` | `ed28fb1b1fcd246c2d0eadc26dc896879ee602e1fc0b085ccd8d85abfbe017c0` |
| `2026_11_17_000007_add_alerts_two_factor_and_name_confirmation.php` | `ec9a258af3824b89dfcb4848b582b5e9f531eb5722376e92f6f2cdb6837bb7a3` |
| `app/Support/IntegrityTriggers.php` | `024bf622b43117698046952ec014e0ff0c84a0a0d9c1a64a6d7175ce817cfac5` |

Decision rule:
- **No migration or trigger file differs, and `migrate:status` lists exactly these seven as Ran:** compatible. Upgrade in place, keeping the database and `staging.env`.
- **`9e805f3` has a migration `19c6a66` lacks, or one with different contents:** stop. Its schema may be ahead of `19c6a66`; deploying would run older code against a newer schema. Send the file names and hashes (no secrets) to the developer first.
- **Only `19c6a66` has extra migrations:** compatible; the deploy script applies them.

Before the upgrade, export the staging database (phpMyAdmin → Export, or `mysqldump`) into `~/bobagunwa-deploy-logs/` (mode 700). The deploy script's snapshot covers files only, not the database.

### 0b. Results of the check (server, 18 Nov 2026 ~19:30 UTC, read-only)

Run by the Claude Code session on the maintainer's computer in cPanel Terminal; reported to this document.

| Item | Result |
|---|---|
| Deployed `RELEASE.json` | revision `9e805f3da77e`, commit `9e805f3da77e497744aef075118434416b83759f`, tree `517f1b4a72fb5b04f535b4400910d95d576bd4ab`, built 2026-11-16T12:28:13Z, `composer_lock_sha256` identical to `19c6a66` |
| Uploaded `19c6a66` package | `sha256sum -c`: OK (the upload page showed "HTTP error 500" at 100 %, but the file arrived complete) |
| Files that differ, of 8,464 | **2**: `config/database.php` and `RELEASE.json`; none added or removed |
| Migration and trigger files | all 7 migrations and `IntegrityTriggers.php` identical to the hashes above |
| `migrate:status` | the seven migrations, all `[1] Ran`, nothing else |
| The one real difference | `config/database.php` adds to the `mariadb` options `(PHP_VERSION_ID >= 80400 ? Mysql::ATTR_INIT_COMMAND : PDO::MYSQL_ATTR_INIT_COMMAND) => 'SET SESSION default_storage_engine=InnoDB'` (Namecheap's MariaDB may default to MyISAM) |
| PHP 8.3 CLI | **`/opt/alt/php83/usr/bin/php`**; `/opt/cpanel/ea-php83/` does not exist (runbook corrected) |
| Server changes made during the check | earlier `19c6a66` files in `~/releases` renamed `old-20261116-…` (nothing deleted); `~/bobagunwa-compare/` (mode 700) created; nothing deployed; `staging.env` not opened |

**Decision:** the database is compatible, but deploying `19c6a66` would remove the InnoDB setting. That setting was added to `main` (commit `8db1405`) and a new release was built from it.

### 0c. New release and upgrade rehearsal (build environment, 18 Nov 2026)

| Check | Result |
|---|---|
| Release | `bobagunwa-8db1405f54af.zip`, SHA-256 `33baf5f3e8ce3a2d642ec3c86246f976bd1dee6cbfc6578fa2edc5e0b0b4b501`, commit `8db1405f54af64c707f0e611e60c4d35aad65640` |
| `deploy/verify-release.sh` | all checks pass (8,471 files; identical to the commit; no secrets) |
| Application code versus `19c6a66` | only `config/database.php` differs; the rest are deploy scripts, docs and evidence |
| `config/database.php` SHA-256 | `77e87831451a2f1705f145ec4fd5271a9c822670e0c5ac3978afa0f2154ed7cd` (same setting as `9e805f3`; the comment wording may differ) |
| InnoDB setting, locally with the server default set to **MyISAM** | application session reports InnoDB, `boba:preflight` passes; a plain client still gets MyISAM. Regression test added (154 tests pass) |
| **In-place upgrade rehearsal** on the simulated cPanel account (existing database with 15 synthetic members, server default MyISAM) | `staging-deploy.sh` with the file-name-only checksum: preflight no required failure (advisories: upload limit, backup key); "Nothing to migrate"; 35 triggers and ledger verified; switch; **smoke 13/13**; data kept (15 members); 0 non-InnoDB tables (`evidence/staging-upgrade-rehearsal-2026-11-18/`) |

## 1. Blockers (exact)

| # | Blocker | Evidence | Who resolves |
|---|---|---|---|
| B1 | **Resolved 18 Nov 2026 — see §1a.** *Original finding:* **no valid certificate for the staging host.** The server presents Namecheap's shared certificate `CN=*.web-hosting.com` (SAN `*.web-hosting.com`, `web-hosting.com`; issuer SSL.com TLS Issuing RSA CA R1; valid 3 Nov 2026 – 20 May 2027). `curl` reports "no alternative certificate subject name matches target host name 'v2-staging.bobagunwa.com'". Same finding as 8 Nov. | `openssl s_client -connect v2-staging.bobagunwa.com:443 -servername v2-staging.bobagunwa.com`, run 16 Nov 2026 from the build environment | Maintainer: cPanel → SSL/TLS Status → AutoSSL for the staging host only, keeping the `/.well-known/acme-challenge/` allowance; if validation still fails, ask Namecheap support for the free certificate. Never buy one, never disable the protection globally, never accept an invalid certificate. |
| B2 | **Resolved for the maintainer's computer 18 Nov 2026** (cPanel through the logged-in browser); still true for the cloud build environment. *Original finding:* **no server access from the cloud build environment.** Its proxy carries HTTPS on port 443 only. SSH (port 21098) and cPanel (port 2083) are not possible, and no computer was linked to the session. The maintainer's Namecheap login is in a browser on their own computer, which a cloud session cannot see. | network policy response (CONNECT 403 before the host was allowed; non-443 ports unsupported) | Run the deployment from a Claude Code session on the maintainer's computer (Desktop app with Claude in Chrome, or SSH). |

Browser sign-in on staging is now allowed (B1 resolved), but only once the application is deployed and only from an address the protection admits.

### 1a. B1 resolution — certificate installed (18 Nov 2026)

The account has no AutoSSL, so the account holder asked Namecheap support, who issued Namecheap SSL certificate ID 36817201 ("Standard SSL", `CN=v2-staging.bobagunwa.com`, issuer SSL.com TLS Issuing RSA CA R1, valid 18 Nov 2026 – 4 Jun 2027). It was installed through cPanel → "Exclusive for Namecheap customers" → **Namecheap SSL** → certificate 36817201 → Install, by a Claude Code session on the maintainer's computer using the logged-in browser.

| Check | Result |
|---|---|
| Certificate's domain before install | `v2-staging.bobagunwa.com` only; the install screen offered no other domain |
| Validation, DNS records, payment | none requested; nothing bought, enabled or disabled |
| Status in the plugin | "Active", expires 4 Jun 2027 |
| HTTPS redirect for the staging host | turned on automatically by the plugin's existing "HTTPS by default" setting; not changed by us |
| Main site certificate (`bobagunwa.com`, ID 34208607, expires 23 Jan 2027) | still Active, untouched (observed in the plugin; this build environment cannot reach the main site) |
| Independent TLS check from the build environment, verification **on** (`evidence/staging-ssl-2026-11-18/tls-check.txt`) | `subject CN=v2-staging.bobagunwa.com`; SAN matches the host; issuer SSL.com TLS Issuing RSA CA R1; 18 Nov 2026 18:41 – 4 Jun 2027 18:51 UTC; **"SSL certificate verify ok"** |
| Response | HTTP/2 403 from LiteSpeed, body "Compatibility endpoint is closed." — the existing protection is still in place |
| `/.well-known/acme-challenge/` | 403 (not needed: this certificate was not issued through AutoSSL) |

**Tester access (decided 18 Nov 2026):** password protection (cPanel Directory Privacy, one shared login) rather than an IP allow-list, because testers' mobile and home addresses change. The password is set by the account holder and shared privately; it is never recorded in chat, documents or the repository. The acceptance script takes it from `STAGING_HTTP_USER` / `STAGING_HTTP_PASS`.

**Renewal:** this is not an automatically renewing AutoSSL certificate. Reissue or renew it from the same Namecheap SSL plugin before **4 Jun 2027**, and confirm before then whether the free certificate continues after its first year. Do not buy a certificate without a recorded decision.

## 2. Release integrity

| Check | Result |
|---|---|
| Revision | `19c6a66c0ef08abd21c0d1dc2362b83acb62d890` (`main`, pushed) |
| Package | `bobagunwa-19c6a66c0ef0.zip`, SHA-256 `e0c28026621febe3864b9518b3fd1cb2fa123fae254dee994476289f2228c102` |
| `deploy/verify-release.sh` | package hash OK; per-file `MANIFEST.sha256` OK; every tracked file identical to the commit; only documented exclusions absent; no `.env`, keys, dumps, logs, tests or `node_modules`; no private-key or token patterns anywhere, `vendor/` included |
| Finding fixed on the way | the package carried a vendor library's own test fixtures, including dummy SSH keys (`league/flysystem/test_files`); `build-release.sh` now strips them |
| Rollback package | the deploy script tars the existing staging directory (without `.env`) and keeps the previous directory for an instant switch back |

## 3. Simulated cPanel deployment (executed, synthetic)

Environment: an unprivileged Linux user `bobasim` with its own home directory and a database user limited to its own database (MariaDB 11.4.13), PHP 8.3.6. The existing staging site was represented by a "compatibility harness" folder with a 403 protection `.htaccess`. Commands were run exactly as on Namecheap:

```bash
unzip -p bobagunwa-19c6a66c0ef0.zip deploy/staging-deploy.sh > ~/staging-deploy.sh
BOBA_DRY_RUN=1 BOBA_PHP=<php83> ./staging-deploy.sh ~/releases/bobagunwa-19c6a66c0ef0.zip
BOBA_PHP=<php83> ./staging-deploy.sh ~/releases/bobagunwa-19c6a66c0ef0.zip
./staging-rollback.sh <STAMP>
```

| Check | Result |
|---|---|
| Guards: run as root / target in `public_html` / `MAIL_MAILER=smtp` / wrong `APP_URL` / no protection snippet / tampered checksum / `staging.env` not mode 600 | **all refused**; existing site untouched |
| Preflight (`evidence/staging-simulation-2026-11-16/preflight.txt`) | **44 PASS, 0 FAIL**, 2 advisory: upload limit 2M (set 12M via MultiPHP INI), backup key not set |
| Migrations, 35 triggers, ledger verification | pass |
| Protection rules placed at the top of `public/.htaccess` | yes (with the ACME allowance) |
| Internal smoke check `boba:smoke` (13 pages: status codes and security headers) | **13/13 PASS** |
| Forced smoke failure | **automatic rollback** to the previous site; failed release kept for diagnosis |
| Manual rollback, then redeploy | pass |

### Defects found by the simulation and fixed before any real deployment

| Defect | Impact if deployed | Fix |
|---|---|---|
| `config:cache`, `route:cache` and `view:cache` were built in `~/releases/…` and the folder was then renamed; Laravel caches absolute paths | **every page HTTP 500** on staging after the first deployment (also present in the original runbook) | caches cleared before the switch and built in the final location; `boba:smoke` and auto-rollback added |
| `boba:preflight` queried `users` on an empty database | preflight-before-migrate (the documented order) failed | unmigrated database reported as informational; new probes: CHECK constraints, transaction rollback, trigger enforcement, READ COMMITTED, `proc_open` for the scheduler, web execution time, `.env` outside the webroot |
| The `.zip.sha256` file recorded the path `release/bobagunwa-…zip` (found 18 Nov 2026 while preparing the upload; the simulation had used a regenerated file) | the deploy script's checksum check would refuse the release after upload to `~/releases` | `build-release.sh` writes the file name only; `verify-release.sh` checks from the zip's own folder; the `19c6a66` checksum file was rewritten (hash unchanged, `e0c28026…c102`) and both checks re-run: pass |
| Deploy target normalisation mishandled a missing parent folder | wrong path in an error message (still refused) | `realpath -m` |

## 4. Acceptance over HTTP (simulation)

`tests/browser/staging-acceptance.cjs` drives the real forms in Chromium. On staging it refuses to run unless HTTPS has a valid certificate. The local simulation used `http://127.0.0.1` with `STAGING_ALLOW_HTTP=1`, so Secure-cookie and HSTS checks were not applicable there. Synthetic officers came from `boba:staging:prepare` (single-use links, `.invalid` addresses, random in-memory passwords). Result: **19/19 steps passed** (`evidence/staging-simulation-2026-11-16/acceptance.json`).

| Step | Result |
|---|---|
| Compiled CSS/JS served, JavaScript runs, CSP, X-Frame-Options, nosniff, session cookie HttpOnly + SameSite=Lax | pass (12 assets) |
| 6 synthetic officers activated or reset through single-use links; token removed from the address bar; `Referrer-Policy: no-referrer` | pass |
| Role restrictions: System Administrator → finance 403; Treasurer → member import 403; Secretariat → payments 403; anonymous → sign-in | pass |
| **W1** CSV import of 2 members, no login accounts, re-upload flags 2 duplicates | pass |
| **W2** application → receipt → status "Received" → System Administrator 403 → under review → approved → one-time link | pass |
| **W3** activation → portal shows number → admin 403 → link refused on reuse | pass |
| **W4** category, ₦50,000 assessment previewed and posted; cannot post twice | pass |
| **W5** ₦20,000 recorded (pending; recorder not offered verification), verified by another officer, over-allocation refused, allocated, allocation reversed, re-allocated | pass |
| **W6** member statement ₦50,000 / ₦20,000 / ₦30,000; Print button; balances CSV reconciles | pass |
| Integrity check from the alerts page | passed run recorded |
| **W7** encrypted backup from the web | pass |
| Event draft → published → on `/events`; contact enquiry (with SQL/HTML-like words) → secretariat queue | pass |
| Responsive 375 / 768 / 1440 px, 30 page views without horizontal overflow | pass |

Two script defects were found and fixed while validating it:
- it measured page width before the stylesheet had loaded;
- it read "this link cannot be used" on reuse as a failure.

A simulation artefact (a router that did not serve static files) was also found and replaced with Laravel's server router. The script now checks that compiled assets are served, which will catch a real `.htaccess` problem too.

### Isolated restore drill (W7, server side, simulation)

```bash
php artisan boba:backup:restore <file>.bobabk --key-file=<test key pair> --target=<empty restore db> --confirm-isolated
```

Result: **"Restore verified."**
- 15/15 members;
- 35 triggers restored;
- financial totals identical (allocated ₦60,000 and outstanding ₦90,000 across the three acceptance runs);
- 148 audit events.

The key pair was generated for the test only and is not the custodian key.

## 5. Financial acceptance figures (what staging must reproduce)

| Figure | Expected |
|---|---|
| Assessment for the W2 member | ₦50,000.00 (one obligation) |
| Payment | ₦20,000.00 bank transfer, pending until verified by a **different** officer |
| Allocation after reversal and re-allocation | ₦20,000.00 active; one reversed allocation in history |
| Member outstanding | ₦30,000.00 (statement, portal, balances CSV and raw ledger agree) |
| Ledger exceptions / integrity check | none / passed |

## 6. Client acceptance checklist — for Mr Ibrahim (after deployment; B1 resolved 18 Nov, B2 still open)

Use only made-up names and amounts. Tick each item, or note what went wrong.

- [ ] The address bar shows a padlock for `https://v2-staging.bobagunwa.com` (no certificate warning).
- [ ] The site is not visible from an address outside the allowed list (protection still on).
- [ ] Home, About, Membership, Events, News, Gallery and Contact pages look right on a phone and on a computer.
- [ ] A made-up application can be submitted, and its private tracking code shows "Received".
- [ ] The membership officer approves it and receives a one-time link; the link works once only.
- [ ] The new member signs in and sees their membership number and statement.
- [ ] The treasurer records ₦20,000; it shows "awaiting verification" until the verifier (a different person) verifies it.
- [ ] After allocation, the member's statement shows ₦50,000 charged, ₦20,000 paid and ₦30,000 outstanding, and prints neatly.
- [ ] The System Administrator cannot open any finance page.
- [ ] "Integrity alerts → Run the check now" shows a passed check.
- [ ] A backup can be created, and the technical maintainer confirms the restore drill passed.
- [ ] An event can be drafted and published, and it appears on the Events page.
- [ ] Decisions still needed are listed in `29-approval-packet.md` (items 4–24).

## 6a. Namecheap results — upgrade to `8db1405` (18 Nov 2026)

Run by the Claude Code session on the maintainer's computer in cPanel; reported here. Nothing secret recorded.

| Step | Result |
|---|---|
| Release transfer | the browser download of the 19.9 MB zip arrived truncated (6.6 MB; checksum mismatch, so the deploy was correctly not started). Re-sent as four ≤ 5 MB parts with `parts.sha256`; joined on the server: 19,879,088 bytes, `sha256sum -c` OK |
| Before the upgrade | staging database exported through phpMyAdmin (kept on the maintainer's computer); restore-drill database `…_v2restore` created, only the staging database user has access; password protection created with Directory Privacy (login `boba-staging`; password typed by the account holder, never seen by Claude); old protection file kept as `staging-protection.htaccess.denied-20261118`; cron `*/15 … /opt/alt/php83/usr/bin/php artisan schedule:run` already present |
| Dry run | stamp `20261118T214953Z`, exit 0; preflight 44 PASS, 0 FAIL, 1 WARN (backup public key not set) |
| Deploy | stamp `20261118T215046Z`, exit 0; PHP CLI 8.3.36; snapshot 16 MB (`.env` excluded); manifest verified; "Nothing to migrate"; 35 triggers, no invariant exceptions; protection placed; previous kept at `~/bobagunwa-v2-webcheck.previous-20261118T215046Z`; rollback `staging-rollback.sh 20261118T215046Z` |
| Smoke (`boba:smoke`) | **13/13 PASS** (9 × 200, `/portal` `/admin` `/admin/finance` 302, probe 404) |
| Deployed `config/database.php` | `77e87831…ed7cd`, as expected |
| **Issue 1 — web PHP version** | the new protection snippet (login lines only) had dropped the old snippet's `SetHandler application/x-httpd-alt-php83` block, which is what gives the website PHP 8.3 on this account (no per-domain PHP selector; account default 8.1). Restored by the session in the snippet and the live `public/.htaccess` (backup `public.htaccess.before-php83-handler` in the deploy log folder). Prevention: the runbook snippet now includes the block and `staging-deploy.sh` refuses a snippet without it (from the next release). **Web PHP 8.3 still to be confirmed** on Administration → Settings once the login works |
| **Issue 2 — 403 instead of a login prompt** | open. Confirmed independently from the build environment: `HTTP/2 403`, `www-authenticate: Basic realm="Bobagunwa staging"`, 33-byte body "Compatibility endpoint is closed." — so the new login rules are read, but a deny rule and an `ErrorDocument` defined **outside** `public/` (a parent `.htaccess` or host-level setting) still apply. Browsers show no login box for a 403, so testers cannot get in yet |

**Not yet verified on Namecheap:** browser login, web PHP 8.3, acceptance (§4 workflows), restore drill, rollback rehearsal.

## 7. Continuing on Namecheap

**Correction (18 Nov 2026):** staging is an **upgrade to `bobagunwa-8db1405f54af.zip`**, not a first install, using `BOBA_PHP=/opt/alt/php83/usr/bin/php`. Keep the existing database, `staging.env` (with its `APP_KEY`) and `user.ini`. Add only the empty restore-drill database and the password protection (replace `Require all denied` in `staging-protection.htaccess` with the Directory Privacy lines, keeping a copy of the old file). Run the §0a check first.


Run the steps in [36-staging-installation-plan.md](36-staging-installation-plan.md) from a session on the maintainer's computer, using the commands in [09-deployment-runbook.md](09-deployment-runbook.md). Then record real results in §3–§4 style tables under a new heading **"Namecheap results"** here. Mark capabilities as *Staging verified* in `28-release-readiness-report.md` only for steps actually observed on Namecheap.
