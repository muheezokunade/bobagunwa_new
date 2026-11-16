# Cloudflare staging setup checklist

Date: 14 October 2026
Status: configuration-ready; resource creation and deployment require association-controlled access

## Required isolated resources

- [ ] Association-controlled Cloudflare account with two MFA-protected owners.
- [ ] A staging Worker/Sites project named separately from production, for example `bobagunwa-staging`.
- [ ] A staging D1 database named separately from production, bound only as `DB`.
- [ ] A staging Turnstile widget restricted to the exact staging hostname.
- [ ] A canonical HTTPS staging origin; `APP_ORIGIN` and `BETTER_AUTH_URL` must exactly match it.
- [ ] Staging-only log access and retention settings with secret/token redaction.
- [ ] An approved Worker plan or alternate authentication architecture. Workers Free is blocked by measured secure-password CPU.

The build now accepts these non-secret deployment selectors without changing the local defaults:

- `CLOUDFLARE_WORKER_NAME`
- `CLOUDFLARE_D1_DATABASE_NAME`
- `CLOUDFLARE_D1_DATABASE_ID`

## Staging variables and secrets

Plain variables: `APP_ENV=staging`, `APP_ORIGIN`, `BETTER_AUTH_URL`, `TURNSTILE_SITE_KEY`, and optionally `TURNSTILE_TEST_MODE=true` only for an owner-only synthetic benchmark.

Secrets: independent `BETTER_AUTH_SECRET`, `PUBLIC_SUBMISSION_PEPPER`, `TURNSTILE_SECRET_KEY`, and optional short-lived `AUTH_BENCHMARK_TOKEN`. Values must be entered through Cloudflare secret storage and must not be written to `.env.example`, Git or support logs.

## Setup and verification order

1. Create the staging Worker and D1 database. Record IDs in the deployment environment, not source code.
2. Configure the exact HTTPS origin and a hostname-restricted Turnstile widget.
3. Apply migrations `0001` through `0011` to staging. Confirm both finance invariant triggers exist.
4. Load synthetic fixtures only.
5. Build and deploy the release candidate to staging without changing Netlify, DNS or production resources.
6. Run public, member and administrator route suites; the seven operational workflows; Turnstile hostname/action failure cases; backup export/restore; security headers; accessibility; responsive and load checks.
7. Remove benchmark fixtures/token and confirm no synthetic account can authenticate before client acceptance.
8. Record staging evidence, costs and rollback steps. Do not promote automatically.

## Missing access

No association-controlled Cloudflare credentials, staging D1 ID, staging hostname, Turnstile keys, approved paid Worker plan or deployment authorisation are present in the workspace. Therefore no staging resource was created or changed in this stage.
