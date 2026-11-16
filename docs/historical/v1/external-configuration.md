# External configuration register

Last updated: 11 October 2026

The application is designed to run on one Cloudflare Worker/Sites origin with D1. It has no payment gateway, SMS, AI, paid authentication or transactional-email integration. Membership, approvals, dues, levies, recorded payments, balances and reports are implemented against the internal D1 schema.

`.env.example` contains variable names with empty values only. Local non-production values belong in the ignored `.dev.vars`; staging and production values belong in Cloudflare/Sites variables or secrets. Never commit a real secret, copy one into client code, or reuse credentials between environments.

## Required bindings and values

| Name | Kind | Essential | Source | Purpose and validation |
|---|---|---:|---|---|
| `DB` | D1 binding | Yes | Create a separate D1 database for local/staging/production and bind it as `DB` in the Cloudflare project | Stores all domain, auth, audit and recovery data. The binding name is declared in `.openai/hosting.json`; no production database ID is committed. |
| `APP_ENV` | Plain variable | Yes | Set to `development`, `staging` or `production` in the environment configuration | Controls secure cookies and test-only controls. Production hard-rejects Turnstile test credentials and the benchmark endpoint. |
| `APP_ORIGIN` | Plain variable | Yes | Canonical deployed origin, for example the final HTTPS staging or production URL | Must be a bare origin with no path/query. HTTPS is required outside development and it must match the hostname accepted by Turnstile. |
| `BETTER_AUTH_URL` | Plain variable | Yes | Same canonical origin as `APP_ORIGIN` | Compatibility value for Better Auth tooling. Runtime requests use the validated same-origin configuration. |
| `BETTER_AUTH_SECRET` | Secret | Yes | Generate independently per environment with a cryptographically secure password manager or `openssl rand -base64 48` | Better Auth signing/encryption secret; minimum 32 characters. Rotate through a controlled session-revocation procedure. |
| `PUBLIC_SUBMISSION_PEPPER` | Secret | Yes | Generate independently per environment with a cryptographically secure password manager or `openssl rand -base64 48` | Peppers hashes for source addresses and private identifiers. Minimum 24 characters. Rotation affects rate-limit/linkability history and must be planned. |
| `TURNSTILE_SITE_KEY` | Public variable | Yes | Create a Turnstile widget in the association's Cloudflare dashboard | Rendered in the browser. Configure only the exact staging/production hostnames. Test keys are rejected outside development. |
| `TURNSTILE_SECRET_KEY` | Secret | Yes | Copy once from the same Turnstile widget and store as a Cloudflare secret | Used only by the Worker to call Siteverify. Never expose it to the browser or logs. |
| `TURNSTILE_TEST_MODE` | Plain variable | No; isolated staging only | Set to `true` only in an owner-only synthetic staging environment using Cloudflare's official test credentials | Allows the published test response/action while still verifying Siteverify server-side. It is ignored outside staging and must be absent/false in production. |
| `AUTH_BENCHMARK_TOKEN` | Secret | No; isolated staging only | Generate a high-entropy per-environment secret | Protects the synthetic benchmark fixture endpoint. The endpoint returns 404 outside staging and accepts only `@example.test`/`BENCH-` records. Remove after benchmarking if the environment will serve other staging users. |

The ignored local `.dev.vars` uses Cloudflare's published always-pass development credentials and synthetic data. An isolated owner-only staging benchmark may use the same published credentials only with `APP_ENV=staging` and `TURNSTILE_TEST_MODE=true`; production hard-rejects that combination. Turnstile tokens are still verified server-side and expected to match the action. Production responses must match `APP_ORIGIN`'s hostname. See [Turnstile server-side validation](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/) and [testing keys](https://developers.cloudflare.com/turnstile/troubleshooting/testing/).

## External accounts and services

| Service | Required now | Expected cost | Notes |
|---|---:|---|---|
| Association-controlled Cloudflare account | Yes for staging/production | Approved plan required for password auth | Owns Sites/Worker deployment, D1, Turnstile, DNS and observability. The benchmark rules out Workers Free for the current Better Auth hashing path. |
| Cloudflare D1 | Yes | Free allowance initially; usage-dependent thereafter | Current free allowance includes 5 million rows read/day, 100,000 rows written/day and 5 GB total storage. Verify current pricing before launch. |
| Cloudflare Turnstile | Yes | Free plan | Free plan supports the initial deployment; verify hostname/widget limits before adding more domains. |
| Domain registrar/DNS | Yes for custom production domain | Existing renewal cost | DNS can be managed in Cloudflare after an approved cutover plan. |
| Outbound email/SMS | No | £0 in the current architecture | Activation and recovery use a permissioned, administrator-assisted one-time-link handoff. No message is claimed as sent. |
| Payment gateway | No | £0 | Administrators record verified payments internally; no automated collection is implemented or implied. |
| R2/object storage | No | £0 currently | `.openai/hosting.json` deliberately sets `r2` to `null`; add only after upload/privacy/cost approval. |

Cloudflare Email Service's arbitrary outbound sending currently requires Workers Paid, while Email Routing is not a general transactional-delivery service. The current administrator-assisted process avoids that dependency. If the association later approves email automation, record a new architectural decision, perform privacy/security review, add delivery-failure handling, and obtain explicit approval for any cost.

Primary pricing references: [D1 pricing](https://developers.cloudflare.com/d1/platform/pricing/), [Turnstile plans](https://developers.cloudflare.com/turnstile/plans/), and [Cloudflare Email Service](https://developers.cloudflare.com/email-service/).

## Local development

1. Use the ignored `.dev.vars`; never replace its values with production secrets.
2. Apply all migrations to the local D1 database and load only `tests/fixtures/*` synthetic records.
3. Run `npm test`, the build, and the live public/auth/admin suites.
4. Production values are not required for independent development. Official Turnstile test credentials exercise the full server-verification path locally.

## Secret handling and rotation

- Grant secret-management rights only to named association operators.
- Store recovery codes and owner-account MFA outside the repository.
- Rotate immediately after suspected exposure, staff departure or environment cloning.
- A Better Auth secret change invalidates existing authentication material and requires a planned sign-in reset.
- Never print secret values in deployment logs, screenshots, support messages or documentation.
