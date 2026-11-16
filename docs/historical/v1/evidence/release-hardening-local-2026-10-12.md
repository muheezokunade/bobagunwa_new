# Release-hardening local evidence

Date: 12 October 2026
Environment: local Cloudflare Worker runtime and project-local D1 state
Data: synthetic only
Production impact: none

## Integrated operational run

Run `1791799762977` passed the seven required workflows against one isolated local database.

| Workflow | Result | Evidence |
|---|---|---|
| Existing-member import | Pass | Two valid rows committed; replay returned the original receipt; a second preview found two duplicates; zero login accounts created; anonymous request returned 401. |
| Membership application | Pass | Public submission, protected status lookup, review and approval passed; invalid declaration returned 400, duplicate submission 409, anonymous decision 401 and approval replay 409. Exactly one membership record was created. |
| Account activation | Pass | Login before activation returned 401; the one-use invitation activated the account; replay returned 400; member portal returned 200 and `/admin` returned 307. |
| Dues assessment | Pass | One ₦50,000 obligation was posted after preview; idempotent replay preserved the original assessment; duplicate posting returned 409. |
| Partial payment | Pass | A ₦20,000 bank transfer remained pending until manual verification and allocation; ordinary-member recording returned 403; over-allocation returned 400; second posting returned 409. |
| Financial reconciliation | Pass | Member statement, treasurer JSON and CSV all reported ₦50,000 assessed, ₦20,000 paid and ₦30,000 outstanding. |
| Backup and restoration | Pass | AES-256-GCM/scrypt export restored to a separate SQLite database; integrity `ok`, zero foreign-key violations, exact operational records, 6 connected audit events and ₦30,000 outstanding balance preserved. |

Frontend verification returned 200 for the public application and activation pages, import dashboard, member list, approved-application detail, assessment detail, payment detail, treasurer report and member statement. The ordinary member received 307 for `/admin`.

## Backup-security drill

The version 2 backup drill passed:

- successful AES-256-GCM export and restore;
- scrypt with `N=131072`, `r=8`, `p=1`, a random 32-byte salt and random 12-byte nonce;
- authenticated versioned metadata;
- wrong-password rejection;
- modified-ciphertext rejection;
- modified-metadata rejection;
- modified-manifest rejection;
- truncated-file rejection;
- plaintext export and restore-script removal;
- restored 48-table schema, sentinel, SQLite integrity and foreign-key checks.

Integrated encrypted artifact SHA-256: `72b3d51fa3f33fa4b67d5ec8824f08772599459912ec3089e1fc8a320f2064eb`. Temporary artifact paths and the synthetic passphrase were not committed.

## Other executed checks

- TypeScript: pass.
- ESLint: pass with zero errors and zero warnings.
- Unit tests: 28 passed, 0 failed.
- Schema: 48 tables, 19 permissions and 5 roles; foreign keys pass.
- Database invariants: pass.
- Route inventory: 54 routes present and accounted for; all remain Partial.
- Public live suite: pass.
- Member authentication/live suite: pass after one rerun; an overlapping first run produced an expected optimistic-version conflict and was discarded.
- Administrative live suite: pass after applying migration `0011` to the local fixture database and reseeding synthetic fixtures.
- Visible-copy audit: all 54 routes pass.
- Production build: pass.
- Dependency audit: `npm audit --omit=dev` reports zero vulnerabilities. The full audit reports eight high development/build findings that reduce to the unpatched `braces` glob-pattern denial-of-service advisory through ESLint/Vinext tooling; no affected package is present in the Worker bundle.

This evidence does not establish Cloudflare staging, complete assistive-technology acceptance, remote D1 recovery, production credentials or production approval.
