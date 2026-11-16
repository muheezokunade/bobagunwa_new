# Deployment runbook (Namecheap cPanel)

**Authorisation required.** The earlier approval covered only an isolated compatibility harness. Deploying this full application to staging needs **explicit approval**; production cutover needs **separate explicit approval, a verified backup and a rehearsed rollback** (decisions D-017, D-018). Use synthetic data only on staging.

## 0. Build the release (developer machine or CI)

```bash
deploy/build-release.sh
```
Produces `release/bobagunwa-<revision>.zip` and its `.sha256`. Requires a clean Git tree.

## 1. Prepare cPanel (staging)

1. **Domains**: confirm `v2-staging.bobagunwa.com` document root is `/home/<user>/bobagunwa-v2-webcheck/public` (do not change the main domain or WordPress).
2. **MultiPHP Manager**: PHP 8.3 for the staging domain only. **MultiPHP INI Editor**: apply `deploy/user.ini.example` values for that domain.
3. **MySQL Databases**: create database `<user>_bobastg` and user `<user>_bobastg`, strong password, add user to that database only with **ALL PRIVILEGES** (includes TRIGGER).
4. **SSL/TLS Status**: run AutoSSL for the staging host; continue only when a valid certificate is installed (see `16-namecheap-hosting-compatibility.md`).

## 2. Upload and unpack (SSH, port 21098)

```bash
ssh -p 21098 <user>@<server>
mkdir -p ~/releases && cd ~/releases
# upload bobagunwa-<rev>.zip and .sha256 with scp -P 21098 or File Manager, then:
sha256sum -c bobagunwa-<rev>.zip.sha256
mkdir bobagunwa-<rev> && cd bobagunwa-<rev> && unzip -q ../bobagunwa-<rev>.zip
```

## 3. Configure

```bash
cp deploy/env.staging.example .env
chmod 600 .env
nano .env        # database credentials, APP_URL, BOBA_BACKUP_PATH (outside public_html), BOBA_BACKUP_PUBLIC_KEY
php83 artisan key:generate --force      # use the PHP 8.3 CLI path for this account
mkdir -p ~/bobagunwa-backups/staging && chmod 700 ~/bobagunwa-backups ~/bobagunwa-backups/staging
```

## 4. Verify the host

```bash
php83 artisan boba:preflight
```
All required checks must PASS. Record the output in `docs/19-test-evidence.md`.

## 5. Migrate and cache

```bash
php83 artisan migrate --force          # schema, 35 integrity triggers, reference data
php83 artisan boba:ledger:verify
php83 artisan config:cache && php83 artisan route:cache && php83 artisan view:cache
```

## 6. Switch the document root to this release (no symlink needed)

The staging docroot is `~/bobagunwa-v2-webcheck/public`. Keep the previous version for rollback:

```bash
cd ~
[ -d bobagunwa-v2-webcheck ] && mv bobagunwa-v2-webcheck bobagunwa-v2-webcheck.previous-$(date +%Y%m%d%H%M)
mv ~/releases/bobagunwa-<rev> ~/bobagunwa-v2-webcheck
```
(`mv` within one filesystem is atomic.) Re-apply the existing staging 403 protection to `public/.htaccess` (IP allow-list or password protection via cPanel **Directory Privacy**), keeping the `.well-known/acme-challenge` allowance for certificate renewal.

## 7. Provision the first administrator

```bash
php83 artisan boba:admin:create admin.officer@example.org "Officer Name" --role=system_admin
```
Send the printed one-time link privately. That administrator then creates the other officers in Administration → Officers and access.

## 8. Cron (cPanel → Cron Jobs)

```
*/15 * * * * cd /home/<user>/bobagunwa-v2-webcheck && /path/to/php83 artisan schedule:run >> /dev/null 2>&1
```

## 9. Smoke test (record results)

Home, Membership, Apply (synthetic), Track application, Contact (synthetic), Login, activation of a synthetic officer, Administration overview, Reports, Backups → Create encrypted backup, then a restore drill into an empty `<user>_restoredrill` database (`10-backup-and-recovery.md`). Confirm HTTPS shows a valid certificate and the `Secure` session cookie is set. Then enable the HTTPS redirect in `public/.htaccess`.

## Rollback

```bash
cd ~ && mv bobagunwa-v2-webcheck bobagunwa-v2-webcheck.failed-$(date +%Y%m%d%H%M) && mv bobagunwa-v2-webcheck.previous-<stamp> bobagunwa-v2-webcheck
```
Database migrations in this release are additive from an empty database; for later releases take an encrypted backup before `migrate` and restore it into a fresh database if a rollback of schema is required.

## Production (only after separate approval)

Use a **separate** database, user, `APP_KEY`, backup key and directory (e.g. `~/bobagunwa-app` with a new subdomain or the main domain document root as decided). The main domain currently serves WordPress from `public_html`; cutover options and their DNS/SSL implications must be approved and rehearsed first (`27-production-release-checklist.md`). Production starts with **no synthetic data**: run only migrations (reference data) and `boba:admin:create`; record launch approvals in Settings before importing members or issuing numbers.
