# Deployment runbook (Namecheap cPanel)

**Authorisation required.** The earlier approval covered only an isolated compatibility harness. Deploying this full application to staging needs **explicit approval**; production cutover needs **separate explicit approval, a verified backup and a rehearsed rollback** (decisions D-017, D-018). Use synthetic data only on staging.

## 0. Build and verify the release (developer machine or CI)

```bash
deploy/build-release.sh                              # release/bobagunwa-<rev>.zip, .zip.sha256, .MANIFEST.sha256
deploy/verify-release.sh release/bobagunwa-<rev>.zip # proves the package matches the commit; scans for secrets
```
Requires a clean Git tree. The package contains `MANIFEST.sha256` (every file) and `RELEASE.json` (commit, tree, lock-file hash).

## 1. Prepare cPanel (staging only)

1. **SSL/TLS Status**: the staging host must show a **valid** certificate before anyone signs in through a browser (`36-staging-installation-plan.md`, gate G3). The server-side steps below may run before that.
2. **Domains**: confirm the document root of `v2-staging.bobagunwa.com` is `~/bobagunwa-v2-webcheck/public`. Do not change the main domain or WordPress.
3. **MultiPHP Manager**: PHP 8.3 for the staging domain only. **MultiPHP INI Editor** (on Namecheap: Select PHP Version → Options): apply `deploy/user.ini.example` (upload 12M per file, **post 64M**, 20 files — membership applications carry up to ten documents — memory 256M, execution 60 s).
4. **MySQL Databases**: a new database and user for staging, **ALL PRIVILEGES** on that database only (includes TRIGGER). Generate the password in cPanel; never paste it into chat or documents. For restore drills, create a second empty database for the same user.
5. **Private configuration** (File Manager or SSH), outside every web root:
   ```bash
   mkdir -p ~/bobagunwa-config && chmod 700 ~/bobagunwa-config
   # staging.env from deploy/env.staging.example: APP_ENV=staging, APP_DEBUG=false,
   # APP_URL=https://v2-staging.bobagunwa.com, MAIL_MAILER=log, DB_* for the staging database,
   # BOBA_BACKUP_PATH=/home/<user>/bobagunwa-backups/staging. Leave APP_KEY empty: the script generates it once.
   chmod 600 ~/bobagunwa-config/staging.env
   # staging-protection.htaccess: the protection rules, placed by the script at the top of public/.htaccess.
   ```
   **Chosen for `v2-staging` (18 Nov 2026): password protection** (one shared login for testers; mobile IP
   addresses change too often for an allow-list). Create it with cPanel → **Directory Privacy** on
   `bobagunwa-v2-webcheck/public` ("Password protect this directory", a username such as `boba-staging`, a
   password typed by the account holder: never in chat, documents or the repository). Then copy the lines cPanel
   added to that folder's `.htaccess` into `~/bobagunwa-config/staging-protection.htaccess`, e.g.
   ```apache
   # PHP 8.3 for the website. REQUIRED on Namecheap: CloudLinux "Select PHP Version" has no per-domain
   # setting on this account (site isolation denied) and the account default is PHP 8.1. The deploy
   # script refuses a snippet without it (or BOBA_WEB_PHP_OK=1 where the domain is set to 8.3 elsewhere).
   <FilesMatch "\.(?:html|php)$">
       SetHandler application/x-httpd-alt-php83
   </FilesMatch>

   AuthType Basic
   AuthName "Bobagunwa staging"
   AuthUserFile "/home/<user>/.htpasswds/bobagunwa-v2-webcheck/public/passwd"
   Require valid-user
   ```
   Check after deployment: without the login the site answers **401**; with it, the home page loads. The
   certificate (Namecheap SSL, not AutoSSL) needs no `.well-known` allowance; if a later reissue asks for HTTP
   file validation, add a narrow exception then. IP-based alternative: `Require ip <allowed address>`.

## 2. Upload and deploy (SSH on port 21098, or cPanel → Terminal)

```bash
mkdir -p ~/releases       # upload bobagunwa-<rev>.zip and .zip.sha256 here
cd ~ && unzip -o -j ~/releases/bobagunwa-<rev>.zip deploy/staging-deploy.sh deploy/staging-rollback.sh -d ~ && chmod 700 ~/staging-*.sh
ls /opt/alt/               # the PHP 8.3 CLI on Namecheap (CloudLinux) is /opt/alt/php83/usr/bin/php
                          # (/opt/cpanel/ea-php83/ does not exist there; confirmed on the server 18 Nov 2026)
BOBA_DRY_RUN=1 BOBA_PHP=/opt/alt/php83/usr/bin/php ~/staging-deploy.sh ~/releases/bobagunwa-<rev>.zip
BOBA_PHP=/opt/alt/php83/usr/bin/php ~/staging-deploy.sh ~/releases/bobagunwa-<rev>.zip
```

The script (`deploy/staging-deploy.sh`) does, in order, and stops before the switch on any failure:

1. **Guards.** It refuses to run when:
   - it is run as root;
   - the target is in `public_html` or is the home directory;
   - `staging.env` is not mode 600 or has the wrong settings: `APP_ENV`, `APP_DEBUG`, `APP_URL`, or a mail transport other than `log`/`array`;
   - the protection snippet is missing;
   - the checksum does not match;
   - PHP is older than 8.3.
2. **Snapshot** of the existing staging directory (`.env` excluded) and its `.htaccess` into `~/bobagunwa-deploy-logs/<STAMP>/`.
3. **Unpack** to `~/releases/…-<STAMP>`, verify `MANIFEST.sha256`, copy `.env`, and generate `APP_KEY` once (stored only in `staging.env`).
4. **`boba:preflight`**. Any failed required check aborts the run; output goes to `preflight.txt` and `preflight.json`.
5. **`migrate --force`** and **`boba:ledger:verify`** (35 triggers, zero exceptions).
6. **Protection**: the protection rules are placed at the top of `public/.htaccess`.
7. **Switch** by renaming within the home filesystem. The previous directory is kept as `~/bobagunwa-v2-webcheck.previous-<STAMP>`.
   Before the switch the script also **carries uploads over** (`public/media`, `storage/app`) from the live site, restores the release's own files there from the zip, and sets **web permissions** (app folder 711, `public/` 755/644) because LiteSpeed reads files as `nobody`.
8. **Caches are built in the final location** (they store absolute paths; building them before the rename makes every page fail). Then **`boba:smoke`** sends internal requests to 13 pages and checks status codes and security headers. **If it fails, the previous site is restored automatically.**

Record the STAMP the script prints.

## 3. Cron (cPanel → Cron Jobs, staging only)

```
*/15 * * * * cd /home/<user>/bobagunwa-v2-webcheck && /opt/alt/php83/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```
If preflight reports `proc_open` disabled, replace it with direct entries: `boba:ledger:verify` daily, `boba:backup:create --keep=14` daily, and the hourly overdue and prune tasks.

## 4. Administration

* The **real first administrator** is provisioned only after the association names the person (D-021):
  `php artisan boba:admin:create <email> "<name>" --role=system_admin` prints a single-use link to the SSH terminal; send it privately.
* **Synthetic acceptance officers**:
  `php artisan boba:staging:prepare --confirm-synthetic --links-file=~/bobagunwa-config/acceptance-links.json`
  creates one officer per role at `@staging-synthetic.invalid` with single-use links (no passwords exist). Copy the file privately to the computer running the acceptance script, then delete both copies. Refused in production; preflight fails in production if such accounts exist.
* Two-factor enforcement stays **off** until D-024.

## 5. Acceptance (only with a valid certificate)

On the computer that runs it (a clone of the repository, not the server):
```bash
npm ci && npx playwright install chromium   # or skip and set STAGING_BROWSER_CHANNEL=chrome to use installed Chrome
node tests/browser/staging-acceptance.cjs https://v2-staging.bobagunwa.com acceptance-links.json staging-acceptance.json
# STAGING_HTTP_USER / STAGING_HTTP_PASS if the protection is cPanel Directory Privacy (type the password, never store it)
# STAGING_BROWSER_CHANNEL=chrome where Playwright cannot install Chromium (e.g. macOS 13)
```
Run it from an allowed address. It refuses invalid HTTPS and reproduces workflows W1–W7 (the web part of W7), role restrictions, the integrity check, an event, an enquiry (ModSecurity check) and responsive rendering.

Then run the **restore drill** on the server, into the empty second database:
```bash
php artisan boba:backup:restore ~/bobagunwa-backups/staging/<file>.bobabk --key-file=<custodian or test key pair file> --target=<empty restore db> --confirm-isolated
```
Record results in `37-staging-deployment-evidence.md`.

## 6. Smoke test by eye

Confirm a valid certificate and the padlock, the `Secure` session cookie, and that a non-allowed address still gets 403. Only then enable the HTTPS redirect in `public/.htaccess`.

## Rollback

```bash
~/staging-rollback.sh <STAMP>   # restores the kept previous directory (or the snapshot); the failed release is kept as .failed-<time>
```
The staging database is not changed by a rollback. Migrations in this release are additive from an empty database. For later releases, take an encrypted backup before deploying, and restore it into a fresh database if a schema rollback is needed.

## Production (only after separate approval)

Use a **separate** database, user, `APP_KEY`, backup key and directory (e.g. `~/bobagunwa-app` with a new subdomain or the main domain document root as decided). The main domain currently serves WordPress from `public_html`; cutover options and their DNS/SSL implications must be approved and rehearsed first (`27-production-release-checklist.md`). Production starts with **no synthetic data**: run only migrations (reference data) and `boba:admin:create`; record launch approvals in Settings before importing members or issuing numbers.
