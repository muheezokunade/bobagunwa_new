# Backup and recovery

## Required production policy

- Use D1 Time Travel for short-window operational recovery (Free currently provides 7 days).
- Export a consistent database backup on an approved schedule.
- Encrypt the export before it leaves the controlled environment.
- Store it outside the live Cloudflare account/database with access restricted to named custodians.
- Record backup time, database/environment, schema migration level, checksum, custodian, and restore-test status.
- Never commit backups or real exports to Git.

## Implemented protected-export procedure

The repository supplies two guarded commands. Neither runs automatically and neither defaults to production:

```bash
BACKUP_PASSPHRASE='<from approved secret store>' npm run backup:d1 -- <database-name> <protected-output-directory>
BACKUP_PASSPHRASE='<same secret>' npm run restore:d1 -- <backup.sql.enc> <backup.sql.enc.json> <isolated-database-name> --confirm-isolated
```

The export command uses `wrangler d1 export --remote`, writes the temporary plaintext with owner-only permissions, and creates a version 2 AES-256-GCM envelope. The envelope uses a securely generated 12-byte nonce, a 32-byte salt, scrypt (`N=131072`, `r=8`, `p=1`) and versioned metadata authenticated as additional data. Its manifest binds the environment, source database, export time, plaintext size/hash and encrypted-file hash. The plaintext export is removed in a `finally` block.

The restore command refuses to run without an explicit isolated-target confirmation and a target name different from the source. It authenticates the version 2 envelope and manifest before writing the temporary SQL, removes the temporary directory afterward, and rejects wrong passwords, modified ciphertext, modified metadata/manifests and truncated files. Existing version 1 AES-256-CBC/PBKDF2 archives remain restorable with an explicit warning and checksum check; that legacy path is not described as authenticated encryption.

Run `npm run test:backup-restore` for the credential-free local drill. It creates a migrated synthetic source database, encrypts an export, exercises all rejection cases, restores into a different database, runs integrity and foreign-key checks, confirms all 48 tables and verifies plaintext cleanup. The integrated 12 October 2026 acceptance run additionally preserved membership, import, assessment, payment, allocation, audit and exact ₦30,000 balance records after restoration. The owner-only authentication benchmark did not authorise a remote export or an off-platform custody destination, so it is not counted as the required remote recovery drill.

## Approved operating procedure

Before each protected export:

1. Confirm the target environment and database name.
2. Record the current migration list and application release.
3. Run the protected export command to an owner-only path on an encrypted device.
4. Compute a SHA-256 checksum.
5. Encrypt with an association-approved key-management method.
6. Transfer to the approved off-platform location.
7. Confirm no unencrypted copy remains.
8. Restore into an explicitly isolated staging recovery database and run schema, foreign-key, member-count, assessment/payment/allocation and aggregate reconciliation checks.
9. Record the restore result in `restore_tests` or the approved operations register; never overwrite production during a drill.

No scheduled/off-platform custody is claimed yet because the destination, credentials, retention period, encryption custodian and schedule require association approval. D1 Time Travel is a useful short-window layer, not an independent backup.

## Recovery priorities

Contain writes, preserve evidence, identify the last known-good point, restore to isolation first, validate foreign keys and financial reconciliation, obtain authorised approval, then cut over. Never overwrite production before the backup and restore target are verified.
