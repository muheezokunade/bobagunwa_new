# Handover plan

Goal: another competent engineer — or the association's chosen maintainer — can run, deploy, back up and recover the platform from these documents alone.

## What is handed over

| Item | Location |
|---|---|
| Source with full Git history | private GitHub repository `muheezokunade/bobagunwa_new`, branch `main`; a Git bundle and release ZIP were also supplied as offline backups |
| Release package + checksum | `deploy/build-release.sh` → `release/` |
| Documentation | `docs/` (index: `docs/00-index.md`) and `CLAUDE.md` |
| Test suite and evidence | `tests/`, `docs/evidence/` |
| Environment templates | `.env.example`, `deploy/env.staging.example`, `deploy/user.ini.example` |

Not handed over in Git (by design): `.env` files, database credentials, `APP_KEY`s, backup private keys, real member or financial data.

## Sessions

1. **Technical maintainer** (2 hours): architecture, local set-up (`README.md`), test suite, release build, preflight, deployment runbook, restore drill.
2. **System Administrator** (1 hour): officer accounts and roles, recovery queue, settings and launch approvals, audit history, backups and custody.
3. **Membership and Secretariat officers** (1.5 hours): applications end to end, message delivery and attestation, register, imports, content publishing, events, enquiries.
4. **Treasurer, Payment Verifier, Executive** (2 hours): categories, assessments, recording and verifying payments, allocation, adjustments, reversals, statements, reports and the monthly reconciliation routine; practise on staging with synthetic data.

Each session uses the guides `20-administrator-guide.md`, `21-treasurer-guide.md`, `22-content-publishing-guide.md`.

## Ongoing responsibilities

| Task | Frequency | Owner |
|---|---|---|
| Check Reports → Reconciliation checks | weekly | Treasurer |
| Download latest encrypted backup to off-site custody | weekly | Backup officer |
| Restore drill into an isolated database | quarterly | Technical maintainer + custodian |
| `composer audit`, `npm audit`, update patch versions, run full tests, rebuild release | monthly | Technical maintainer |
| Review officer accounts and roles | quarterly and at each change of executive | System Administrator |
| Review audit history for unusual activity | monthly | Executive / System Administrator |
| Verify SSL; staging certificate (Namecheap SSL 36817201) does **not** auto-renew — renew/reissue in cPanel → Namecheap SSL before 4 Jun 2027 | monthly glance; reminder May 2027 | Technical maintainer |

## Change of administration (new executive)

Grant roles to incoming officers, revoke outgoing officers' roles (their accounts remain for history, never deleted), rotate the backup custodians if needed (generate a new key pair; keep the old private key for old backups), and export the audit history for the handover file.
