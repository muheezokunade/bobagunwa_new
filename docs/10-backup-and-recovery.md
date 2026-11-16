# Backup and recovery

Namecheap's own account backups are useful but not sufficient: they are not encrypted to the association, not verified by restore, and live with the same provider. This platform adds encrypted, verifiable, off-site backups at no extra cost.

## Design (`app/Services/BackupService.php`)

1. **Consistent snapshot**: `START TRANSACTION WITH CONSISTENT SNAPSHOT` (REPEATABLE READ, read-only); every table's schema (`SHOW CREATE TABLE`), data (all strings hex-encoded; generated columns skipped), then all triggers (definer stripped). Sessions/cache are schema-only. Pure PHP — no `mysqldump` or `exec()`.
2. **Encryption** (libsodium, established constructions only): random 32-byte file key → `crypto_secretstream_xchacha20poly1305` (authenticated, chunked; detects modification, reordering and truncation via the FINAL tag). File key sealed to the custodian's **X25519 public key** with `crypto_box_seal`. The plaintext header (format, time, environment, database) is bound to every chunk as additional data. The server holds only the public key: it can create backups but cannot read them.
3. **Manifest** (encrypted, final chunk): per-table row counts, trigger list, last migration, organisation financial totals, member and audit counts, plaintext SHA-256.
4. Files are written with mode 0600 to `BOBA_BACKUP_PATH` (default `storage/app/private/backups`, outside the webroot); no plaintext is ever written during backup.

## Restore (`php artisan boba:backup:restore`)

* Requires the custodian key pair file, an **empty** target database different from the live one, and `--confirm-isolated`.
* The whole file is authenticated and decrypted into a 0600 temporary file **before** any SQL runs; wrong key, tampering, header changes, truncation or a missing final block abort with nothing restored; the temporary file is always deleted.
* After loading: row counts vs manifest, trigger set vs manifest and vs `IntegrityTriggers`, a foreign-key orphan scan of every constraint, recomputed financial totals vs manifest, and the nine ledger exception checks. The result is recorded in `backup_runs`.

Tested in `BackupRestoreTest` and workflow 7 of `MandatoryWorkflowsTest`.

## Procedure

**Once (key ceremony)** — on a trusted computer with the application checked out, not the server:
```bash
php artisan boba:backup:keygen
```
Put `BOBA_BACKUP_PUBLIC_KEY=…` in the server `.env`. Store the private key pair with **two** named custodians (e.g. password manager plus sealed printed copy). Losing it makes every backup unreadable; leaking it exposes backups to whoever also holds a file.

**Daily (automatic)**: cron runs `schedule:run`; at 02:00 `boba:backup:create --keep=14` (only if a public key is configured) keeps the 14 newest encrypted files on the server.

**Weekly (manual, off-site)**: an officer with `backups.manage` downloads the latest file from Administration → Backups (only ciphertext can be downloaded) and stores it in the association's approved off-site location, recording the SHA-256 shown on the page.

**Quarterly (restore drill)**: in cPanel create an empty database (e.g. `<user>_restoredrill`) granted to the application user, then:
```bash
php artisan boba:backup:restore /path/to/file.bobabk --key-file=/secure/path/custodian.key --target=<user>_restoredrill --confirm-isolated
```
Check `"ok": true`, compare totals with the Reports page, then delete the drill database and the key file from the server.

## Disaster recovery

Contain (maintenance mode `php artisan down`), preserve evidence, choose the last good backup, restore into an isolated database, verify, obtain the association's explicit approval, then repoint `.env` to the restored database (or restore again into a fresh production database) and `php artisan up`. Never overwrite production during a drill.

## Not yet claimed

Off-site custody and the custodians themselves are association decisions (D-014). Until a custodian holds the key and an off-site copy exists, **reliable off-site disaster recovery is not established.**
