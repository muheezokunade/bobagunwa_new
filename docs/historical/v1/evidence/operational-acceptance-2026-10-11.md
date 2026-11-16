# Integrated operational acceptance evidence

Date: 11 October 2026  
Run ID: `1791758864406`  
Environment: isolated local Cloudflare Worker and D1-compatible SQLite state  
Data classification: synthetic test data only  
Production impact: none; the Netlify site and production records were not modified

## Result

All seven mandatory workflows passed against one isolated database. The run exercised server-rendered public, member and administrative pages, the real application APIs, Better Auth sessions, D1 transactions, permission denials, audit writes, reconciliation queries and a separately restored encrypted database.

| # | Workflow | Successful path | Failure/security path | Database/integrity evidence | Result |
|---:|---|---|---|---|---|
| 1 | Existing member import | Dashboard CSV upload and preview showed 2 total/2 valid/0 invalid/0 duplicates; API commit created 2 members | Same rows revalidated as 2 duplicates; anonymous validation returned 401; commit replay returned the original receipt | Import job `65f166b0-fb73-47d8-b0ad-1a23d8b54f4d` is completed with 2 linked rows and 0 login accounts | Pass |
| 2 | New application | Public application `EBI-APP-20261011-RZ9NJW` was submitted, reviewed and approved | Missing declaration returned 400; duplicate contact details returned 409; anonymous decision returned 401; duplicate approval returned 409 | Application `21d244f8-f302-4cf8-b640-224e3dea203b` links to exactly one member, `BOB-000002` | Pass |
| 3 | Account activation | One-use invitation activated the approved member; member login and portal access returned 200 | Login before activation returned 401; invitation replay returned 400; member access to `/admin` redirected with 307 | One accepted, non-revoked invitation and one member-linked application were present after restore | Pass |
| 4 | Dues assessment | Selected-member preview targeted 1 member; ₦50,000 assessment posted | Same idempotency key replayed the original assessment; a second key for the same assessment returned 409 | One posted assessment and one 5,000,000-kobo obligation for the accepted member | Pass |
| 5 | Partial payment | ₦20,000 bank transfer recorded as pending, then manually verified and allocated | Ordinary member recording returned 403; ₦20,000.01 over-allocation returned 400; a second posting returned 409 | One posted 2,000,000-kobo payment and one active 2,000,000-kobo allocation; balance is 3,000,000 kobo | Pass |
| 6 | Financial reconciliation | Member statement and treasurer JSON/CSV all showed ₦50,000 assessed, ₦20,000 paid and ₦30,000 outstanding | Export and administrative pages remained permission-protected | Statement, report and underlying obligation/allocation records agreed exactly | Pass |
| 7 | Backup and restore | Online snapshot encrypted and restored into a separate SQLite database | Plaintext backup was removed after encryption; restore required the ephemeral passphrase | AES-256-CBC/PBKDF2 (200,000 iterations), integrity `ok`, 0 FK violations, 11 table counts matched, 6 connected audit events preserved | Pass |

## Frontend verification

The following real pages returned 200 with their expected persisted records and controls:

- `/membership/apply/`
- `/portal/activate/`
- `/admin/members/import/`
- `/admin/members/`
- `/admin/applications/21d244f8-f302-4cf8-b640-224e3dea203b/`
- `/admin/finance/assessments/54d9e32e-da28-46f3-9435-5d808b712c38/`
- `/admin/finance/payments/d7f62710-6b5b-4572-92be-e8f21a4740a4/`
- `/admin/reports/`
- `/portal/finances/statement/`

The statement page was corrected during the acceptance cycle after the first run exposed that it listed debit and credit entries without displaying their ₦30,000 result. The rerun verified the new Total assessed, Payments received and Outstanding balance summary. An ordinary member request to `/admin/` returned 307 to the member portal.

## Backup artifact

- Encrypted file: `/tmp/bobagunwa-operational-acceptance.Fapjy3/backup-restore/bobagunwa-acceptance-1791758864406-1791759122854.sqlite.aes256`
- Size: 737,312 bytes
- SHA-256: `b1c92dcda20d28a3c36010727f72365da96e12287ef3c4ccf9ae2fde7b6003f0`
- Restored database: `/tmp/bobagunwa-operational-acceptance.Fapjy3/backup-restore/bobagunwa-acceptance-1791758864406-1791759122854-restored.sqlite`
- Restored integrity: `ok`
- Foreign-key violations: 0
- The encryption passphrase was generated only for the drill and was not written to the repository or evidence.

Temporary `/tmp` artifact paths are local evidence, not durable backup custody. Production backup custody and a remote D1 export drill remain separate pre-production requirements.

## Reproduction commands

The repeatable harnesses are:

- `npm run test:acceptance:operations`
- `npm run test:acceptance:frontend`
- `npm run test:acceptance:backup`

They require the environment variables documented by each script and an isolated migrated database. No harness defaults to a production URL or production database.
