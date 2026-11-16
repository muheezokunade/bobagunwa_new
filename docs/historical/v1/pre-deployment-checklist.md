# Pre-deployment checklist

Last updated: 14 October 2026
Current verdict: **not approved for production deployment or cutover**

Complete this checklist separately for staging and production. Production deployment requires explicit user/association approval; the existing Netlify site remains untouched until then.

## Ownership, accounts and permissions

- [ ] Association-controlled Cloudflare account exists; it is not owned only by a contractor or personal address.
- [ ] At least two named owners use phishing-resistant MFA and recovery codes are stored securely.
- [ ] Developer, deployer, database, secret, DNS and billing permissions follow least privilege.
- [ ] A named operational owner, privacy contact and incident decision-maker are recorded.
- [ ] Current Netlify owner/access and rollback contact are recorded for the transition period.

## Cloudflare resources

- [ ] Separate staging and production Sites/Workers projects exist.
- [ ] Separate staging and production D1 databases exist and are bound as `DB`.
- [x] Reviewed migrations `0001` through `0011` apply successfully in isolated local SQLite; foreign-key and invariant checks pass.
- [ ] Chosen production migration runner installs both canonical financial invariant triggers; the Sites staging migration parser rejected their multi-statement bodies and the auth-only benchmark package omits them.
- [ ] Separate Turnstile widgets restrict hostnames to the exact staging/production domains.
- [ ] Worker compatibility settings and `nodejs_compat` match the verified build.
- [ ] Log access, retention, alert recipients and redaction rules are approved.
- [ ] R2 remains unbound unless an upload/storage design is separately approved.

## Variables and secrets

- [ ] Every essential value in `external-configuration.md` exists in the correct environment.
- [ ] `APP_ENV` is `staging` or `production`, never `development`.
- [ ] `APP_ORIGIN` and `BETTER_AUTH_URL` are the exact canonical HTTPS origin.
- [ ] Unique `BETTER_AUTH_SECRET` and `PUBLIC_SUBMISSION_PEPPER` values are stored in Cloudflare secret storage.
- [ ] Production `TURNSTILE_SITE_KEY` and `TURNSTILE_SECRET_KEY` replace development test keys.
- [ ] No real secret exists in Git history, build output, browser bundles, documentation or logs.
- [ ] A documented rotation/revocation drill has been performed.

## Domain and transport

- [ ] Final domain and `www`/apex canonical choice are approved.
- [ ] DNS records, nameserver authority and TTL/cutover timing are documented.
- [ ] TLS is active and HTTP redirects to HTTPS.
- [ ] HSTS, CSP (including Turnstile origins), frame, MIME, referrer and permissions policies pass staging tests.
- [ ] Search-engine indexing rules distinguish public routes from portal/admin routes.
- [ ] The old Netlify deployment and DNS rollback procedure are retained through the acceptance window.

## Authentication and operations

- [x] Better Auth login, activation, reset, replay denial and session revocation pass an isolated deployed Worker lifecycle without weakening password hashing.
- [ ] An approved authentication host replaces Workers Free: measured password CPU is 89–174ms against the Free 10ms/request limit.
- [ ] Public sign-up is confirmed disabled.
- [ ] Approval/activation and recovery/reset one-time-token expiry and replay tests pass in staging.
- [ ] Public application, inclusive 1980–1982 screening, out-of-range review flagging, duplicate detection and reference/token status tracking pass in staging.
- [ ] Named accounts granted `members.approve` are approved by the association; no other account can review, request information, approve or reject.
- [ ] The association approves how administrators verify member identity before delivering a one-time link.
- [ ] The association approves the private applicant-notification channel; a live Request Information, Approve and Reject message is delivered and explicitly recorded.
- [ ] Only authorised `roles.manage` users can view/issue recovery links; role grants are reviewed.
- [ ] Turnstile challenge, action and hostname failures are tested with production-configured staging keys.
- [ ] Incident response, account compromise, administrator departure and member support procedures are rehearsed.

## Data, privacy and recovery

- [ ] Real member/application/finance data has an approved source, minimisation plan and lawful handling review.
- [ ] Synthetic fixtures are absent from production.
- [ ] Import dry-run, duplicate/conflict handling and reconciliation have been accepted.
- [ ] Backup/export encryption, custody, restore target and retention are approved.
- [x] Authenticated AES-256-GCM export/tamper rejection/isolated restore mechanism passes locally with synthetic data.
- [ ] The same protected-export procedure succeeds against staging D1 within approved recovery objectives and custody controls.
- [ ] Privacy notice, terms, retention, correction and access procedures receive formal approval.
- [ ] Audit events exclude passwords, raw tokens and unnecessary personal data.

## Quality and acceptance

- [ ] `npm run lint`, `npm run typecheck`, `npm test` and `npm run build` pass from a clean checkout.
- [ ] Public, member and administrator live suites pass against staging D1.
- [ ] Tests cover loading, empty, error and success states for every route in `page-inventory.md`.
- [ ] Keyboard, screen-reader, WCAG contrast, 320 px mobile, tablet and desktop testing pass.
- [ ] Browser compatibility, Lighthouse and representative load tests pass.
- [ ] Production dependency audit is clear; development/build advisories are fixed or formally risk-accepted.
- [ ] Page-by-page completion report has no unaccounted workflow and is signed off by the product owner.

## Costs and billing approval

- [ ] Full Workers and D1 usage forecasts are measured from representative staging, not guessed. Authentication CPU has been measured; broader load/row usage has not.
- [ ] The owner understands the D1 free allowance and what happens when daily/storage limits are reached.
- [x] Better Auth CPU results determine Workers Free is not viable for the current secure hashing configuration.
- [ ] The association explicitly approves Workers Paid or another secure authentication architecture; no plan change occurs automatically.
- [ ] Turnstile remains within its free-plan widget/hostname limits.
- [ ] Domain registration/renewal cost and billing contact are recorded.
- [ ] Cloudflare Email Service, SMS, payment gateways, AI APIs and other paid integrations remain disabled unless separately approved.
- [ ] Budget alerts and a monthly cost/usage review owner are configured.

## Release, cutover and rollback

- [ ] Staging acceptance is signed by the association's authorised representative.
- [ ] Production migration is rehearsed with timing, checksums/reconciliation and rollback criteria.
- [ ] Backups are taken and verified immediately before cutover.
- [ ] DNS switch, smoke-test order, monitoring window and decision authority are documented.
- [ ] Public pages, login, application, recovery, member ledger and critical admin views pass post-deploy smoke tests.
- [ ] Rollback preserves new production writes or explicitly accounts for reconciliation.
- [ ] Final deployment/cutover approval is recorded before any production mutation.
