# Operational implementation stage report

Date: 14 October 2026
Release conclusion: not approved for production

| Stage | Actual implemented result | Executed evidence | Remaining blocker |
|---|---|---|---|
| Authentication feasibility | Owner-only Cloudflare Worker/D1 benchmark endpoint and runner; secure hashes unchanged | Full activation/login/session/recovery lifecycle passed; Cloudflare CPU telemetry captured | Better Auth password work is 89–174 ms CPU and is unsuitable for Workers Free's 10 ms request limit; an alternative needs approval |
| Activation and recovery | Expiring single-use activation/recovery records, replay denial, account/member linking, session revocation and administrator-assisted delivery | Local suites and isolated Worker lifecycle pass | Association must approve identity verification, delivery channel and hosting decision |
| Member import | CSV template, column mapping, validation, preview, duplicate/conflict detection, cancellation, atomic replay-safe commit, audit trail and no-login-account guarantee | Synthetic 86-row commit passes within D1 Free limits; the checksum-verified client package maps and dry-runs 86 provisional rows with no accounts or official numbers | Every real row remains `PENDING_VERIFICATION`/`NO` for production approval; no real names were committed |
| Finance | Categories, assessments, obligations, adjustments, manual payment verification/allocation/reversal, opening balances, statements and CSV reports | Unit/invariant/live admin suites pass with synthetic data; batches capped for D1 Free | Official rules, historical source data and named permissions remain unapproved; staging packaging cannot install the two canonical SQLite triggers |
| Content | D1 create/edit/publish/archive for events, announcements, leadership-capable content pages, articles and activities | Publishing suite verifies public visibility and archive removal | Official copy, leaders and dates remain unverified and unpublished |
| Backup/restore | Versioned AES-256-GCM/scrypt protected export with authenticated metadata/manifest, per-backup random nonce/salt, legacy-read compatibility and isolated restore guard | Local migrated 48-table success, wrong-password, ciphertext/metadata/manifest tamper, truncation, plaintext-cleanup, integrity, foreign-key and ledger-reconciliation checks pass | Remote D1 custody location, credentials, retention, owners and recovery objectives need approval |
| Testing/dependencies | D1 limit tests, 86-row import, finance batch limits, route/live suites and dependency review expanded | 28 unit tests plus schema, invariant, public, auth, 28-view admin, publishing, authenticated backup, typecheck, zero-warning lint and production-build checks pass | Full 54-route accessibility/security/load/staging acceptance; 8 high development-only advisories need explicit risk acceptance or upstream fixes |
| Production preservation | Only the earlier isolated owner-only authentication benchmark Worker was used for Cloudflare runtime evidence; no release deployment was performed in this hardening stage | Synthetic benchmark data was cleaned; Netlify production, production DNS/databases and real records were untouched | Association-owned staging credentials and explicit production authority remain required |

Working functionality is distinguished from production acceptance throughout the page inventory and page completion report. No route is marked production-complete.

