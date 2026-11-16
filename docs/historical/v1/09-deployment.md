# Deployment

## Current production

The existing public site is a Next static export deployed to Netlify. It remains the production baseline until a Cloudflare staging deployment passes acceptance and cutover is explicitly approved.

## Target environments

| Environment | Data | Purpose |
| --- | --- | --- |
| Local | Synthetic local D1 | Development and automated checks |
| Staging | Synthetic isolated D1 | Auth benchmark, integration/E2E, administrator review |
| Production | Approved real data only | Live association operations |

Each environment must have a separate database, auth secret, origin, and access policy.

## Local checks

```bash
npm run lint
npm run typecheck
npm test
npm run build
npm run build:netlify
```

The Vite configuration exposes a local D1 binding named `DB`. A real staging/production D1 ID is not committed; the deployment environment supplies it.

## Staging procedure

1. Create an association-controlled Cloudflare project and staging D1 database.
2. Apply reviewed migrations in order and record the migration result.
3. Configure secrets using Cloudflare secret storage, never committed environment files.
4. Create staging Turnstile credentials restricted to the staging hostname.
5. Deploy the Worker/static assets under a staging hostname.
6. Seed only clearly fictional test records.
7. Execute auth CPU benchmarks, integration/E2E, access-control, Turnstile, accessibility, and backup/restore tests.
8. Record results and known issues in `docs/12-testing-report.md`.

## Production gate

Production deployment requires explicit approval, verified content, association-controlled credentials, dependency/security review, backup, successful staging acceptance, tested migrations, rollback/recovery instructions, and a named operational owner. Use [`external-configuration.md`](./external-configuration.md) and [`pre-deployment-checklist.md`](./pre-deployment-checklist.md). This repository does not currently meet that gate.
