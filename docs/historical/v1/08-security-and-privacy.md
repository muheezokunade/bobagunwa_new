# Security and privacy baseline

## Authentication gate

Candidate: Better Auth + D1 + opaque HttpOnly session cookies. Public self-registration is disabled unless approved. The framework schema, pinned package, dependency review and isolated Worker lifecycle benchmark are complete; production use still requires an approved hosting architecture and full staging acceptance.

The pinned candidate is Better Auth 1.7.7. `lib/server/auth.ts` validates the runtime origin/secret, disables public sign-up and automatic sign-in on the mounted authentication API, requires 12–128 character passwords, enables session revocation after reset, uses database-backed rate limiting, restricts trusted origins, and leaves CSRF/origin protections enabled. A server-only lifecycle factory temporarily enables sign-up only while consuming a valid approved invitation and captures reset links only for the permissioned administrator handoff.

The 2026-10-11 isolated owner-only Worker lifecycle passed activation, login, recovery/reset, replay denial and session revocation. Cloudflare telemetry measured password verification at 89–146ms CPU, activation hashing at 174ms and reset hashing at 146ms. This rules out Workers Free's current 10ms HTTP-request CPU allowance for the secure configuration. Hashing strength was not reduced; Workers Paid or another maintained secure authentication architecture now requires approval.

Required settings include Secure cookies in production, strict trusted origins, appropriate SameSite policy, session expiry and revocation, password reset token expiry, email enumeration protection, rate limiting, and no secrets or role claims in browser storage.

## Activation, recovery and anti-automation

- Public sign-up remains disabled. Application approval creates a random seven-day activation token; only its SHA-256 hash is stored.
- Recovery requests use a generic response, a peppered identifier/source fingerprint, rate limiting and Cloudflare Turnstile verified server-side.
- Only an account with `roles.manage` can issue a recovery link, and the interface requires identity verification through an approved channel first.
- Better Auth reset tokens expire after one hour, are one-use, and revoke existing sessions after password reset.
- Local development uses Cloudflare's official test site/secret keys. Runtime validation refuses those keys outside `APP_ENV=development`.
- Production CSP must allow Turnstile's documented script/frame/connect origins before CSP is enabled.

## Authorisation

- Default deny.
- Resolve identity/session on the server for every protected request.
- Check granular permission and object ownership in the service layer.
- Keep finance, role management, exports, and audit access separately grantable.
- Test horizontal and vertical privilege escalation.

## API controls

- Schema-validate all inputs on the server.
- Parameterise SQL; never interpolate user input.
- Validate `Origin`/CSRF token for cookie-authenticated mutations.
- Restrict CORS to the deployed origin; same-origin architecture is preferred.
- Apply endpoint-specific request-size, pagination, and rate limits.
- Use idempotency keys on financial and import mutations.
- Return stable public errors; log correlation IDs, not secrets or raw sensitive payloads.
- Set CSP, HSTS, frame, MIME, referrer, and permissions headers after staging verification.

## Privacy

- Collect only association-operational data approved by policy.
- Do not collect bank credentials, NIN, biometrics, or unrelated sensitive data.
- Keep private routes out of search indexing and shared caches.
- Encrypt transport; rely on platform encryption at rest; protect exports and backups separately.
- Define access, correction, retention, suspension, archival, and deletion procedures with Nigerian legal review, including review under the Nigeria Data Protection Act.
- Record administrative exports and sensitive changes, but do not copy full private records into audit logs.

## Secrets

Secrets belong in environment-specific Cloudflare secret storage, never Git, client bundles, documentation, or logs. Rotate authentication, backup, and integration secrets through documented procedures.

## Incident minimum

Contain access, revoke sessions/credentials, preserve audit evidence, assess affected records, restore safely if required, notify the authorised association contact, and follow the approved legal response process. Detailed incident and recovery procedures will be completed before production.
