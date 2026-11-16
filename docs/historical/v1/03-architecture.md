# Architecture

## Recommended target

Use a single Cloudflare Worker origin for public pages, private UI, API routes, and authentication, with D1 for relational data. Serve static assets through the same deployment. This avoids cross-origin cookie and CORS complexity and keeps the security boundary understandable.

```text
Browser
  -> Cloudflare Worker (Vinext / React)
       -> public routes
       -> /portal routes
       -> /admin routes
       -> /api routes
       -> authentication + authorisation boundary
       -> domain services
            -> membership
            -> finance
            -> content/events
            -> enquiries
       -> D1 (structured records)
       -> optional R2 later (approved, validated media only)
```

## Boundaries

- `app/`: route composition and presentation.
- `components/`: reusable UI only; no direct database access.
- `lib/domain/`: deterministic membership and finance rules.
- `lib/server/`: request validation, sessions, permissions, repositories, audit events, and D1 access.
- `migrations/`: ordered SQL applied through Wrangler.
- `tests/`: domain, integration, and end-to-end checks using isolated data.
- `docs/`: operational and decision record.

## Environment model

- Local: local D1 state; synthetic fixtures only.
- Staging: separate Worker, D1 database, secrets, and synthetic accounts.
- Production: association-controlled Cloudflare account, separate D1 database, real secrets, and real data only after approval.

Never share a D1 database or authentication secret across environments.

## Request flow

1. Worker assigns/accepts a correlation ID.
2. Route applies method, content-type, and payload-size rules.
3. State-changing cookie-authenticated requests pass origin/CSRF validation.
4. Authentication resolves the opaque session server-side.
5. Authorisation checks granular permissions and object ownership.
6. Server validates input and calls a domain service.
7. Repository executes parameterised, indexed queries; related financial mutations use one atomic D1 batch.
8. Sensitive actions append a minimal audit event.
9. Response returns a stable result/error shape without secrets or stack traces.

## Authentication feasibility decision

Provisional choice: Better Auth with its first-class D1 database support, opaque secure HttpOnly cookie sessions, disabled public sign-up, invitation/activation flows, session revocation, and server-resolved permissions.

This is not yet approved for production. The exact login, activation, reset and revocation flows were deployed to an isolated owner-only Worker and passed functionally. Cloudflare telemetry measured successful password verification at 89–146ms CPU, activation hashing at 174ms and reset hashing at 146ms. Those results rule out Workers Free's 10ms request allowance for the current secure implementation. The association must approve Workers Paid or a suitable maintained authentication architecture; password parameters must not be weakened merely to fit the free tier.

The repository's local benchmark remains a development signal only. The hosting decision is based on the recorded Worker CPU measurements, not local wall-clock timing.

Account-link delivery uses a controlled administrator-assisted process so development does not depend on a paid email API. Approval creates a one-time activation link, while a Turnstile-protected recovery request enters a permissioned queue. An administrator must verify the member outside the website before issuing and privately delivering the one-time link. Raw activation tokens are stored only as hashes; Better Auth owns reset-token storage and expiry. The website never claims an email was sent. Production still requires approval of the human verification and delivery procedure.

## Data strategy

- Text UUIDs for internal identifiers; configurable non-reused membership numbers for public reference.
- ISO 8601 UTC timestamps in storage; Africa/Lagos formatting at presentation boundaries.
- Integer kobo for NGN; no floating-point money.
- Foreign keys, checks, unique constraints, and targeted indexes.
- Authentication framework records separated from domain member records by an application identity mapping.
- No binary uploads in D1.

## Deployment decision

Netlify remains the public production host during development. The target is a same-origin Cloudflare deployment after staging validation. A split Netlify frontend/Cloudflare API is a fallback only; it would require a reverse proxy/shared parent domain for dependable first-party cookies, stricter CORS, and two coordinated deployments.

## Roadmap

1. Foundation: audit, decisions, schema, local checks, CI-ready scripts.
2. Security slice: D1 staging binding, auth prototype/benchmark, session and RBAC tests.
3. Membership slice: applications, approval, activation, profiles, search, CSV dry-run/import.
4. Finance slice: categories, assessments, obligations, payments, allocations, reversals, statements, reconciliation.
5. Operations slice: enquiries, announcements, events, CMS, exports, audit viewer.
6. Quality: E2E, accessibility, responsive/device, performance, dependency and abuse-case tests.
7. Staging: synthetic-data review, backup/restore drill, administrator acceptance.
8. Production: approved migration/cutover, smoke tests, monitoring, handover.
