# Cost and usage baseline

Checked against Cloudflare documentation on 2026-10-11; the Workers CPU limit was rechecked on 2026-10-14.

## Workers Free

- 100,000 Worker requests/day.
- 10ms CPU per HTTP request.
- 128MB memory.
- 50 external subrequests per invocation and 1,000 subrequests to internal Cloudflare services.
- Static assets have separate platform behaviour; deployment limits must still be monitored.

The isolated Worker benchmark confirmed the risk: successful password verification used 89–146ms CPU, activation hashing used 174ms, and password reset used 146ms. Therefore the current Better Auth/password design is not viable on Workers Free. The system must not weaken password hashing to avoid the limit, and no paid plan is enabled without explicit approval.

## D1 Free

- 5 million rows read/day.
- 100,000 rows written/day.
- 500MB maximum per database; 5GB total account storage.
- 10 databases per Free account.
- 7-day Time Travel window.
- 50 D1 queries per Worker invocation.
- 100 bound parameters per query.

When daily read/write limits are exceeded, D1 queries fail until the reset. When storage is exhausted, writes/schema changes require cleanup or an approved upgrade. This is an availability constraint, not an automatic bill.

## Operating controls

- Index frequent filters and avoid full-table scans.
- Paginate member, payment, event, and audit lists.
- Track query `rows_read`, `rows_written`, duration, and storage.
- Review Worker request/error/CPU metrics and D1 row metrics monthly at first.
- Alert at agreed thresholds before hard limits.
- Keep images/documents out of D1; introduce R2 only after upload controls and cost review.
- Document domain renewal and any approved email/backup service separately.

## Current integration choices

- Turnstile uses the free plan: up to 20 widgets, 10 configured hostnames per widget and unlimited challenges as documented on 11 October 2026. The public forms do not require a paid anti-bot API.
- There is no payment gateway, paid SMS, AI API or paid authentication provider.
- Activation/recovery links are issued internally and delivered through an approved administrator-assisted channel, so no transactional-email subscription is required today.
- Cloudflare Email Service arbitrary outbound sending currently requires Workers Paid; it is not integrated.
- See `external-configuration.md` and `pre-deployment-checklist.md` for the full configuration and approval register.

D1 query shapes now keep the 86-row member import, 100-row historical import, 500-member assessment and 50-allocation posting within the per-invocation query/parameter limits. The expected data scale can fit D1 Free if usage is modest, but the application Worker cannot use Workers Free with the current secure password implementation. No production cost guarantee is made before representative load testing. A Paid-plan upgrade or alternate authentication host requires explicit approval.

## Primary references

- Cloudflare D1 pricing: https://developers.cloudflare.com/d1/platform/pricing/
- Cloudflare D1 limits: https://developers.cloudflare.com/d1/platform/limits/
- Cloudflare Workers limits: https://developers.cloudflare.com/workers/platform/limits/
- Cloudflare Turnstile plans: https://developers.cloudflare.com/turnstile/plans/
- Cloudflare Email Service: https://developers.cloudflare.com/email-service/
