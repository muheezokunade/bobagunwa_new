# Authentication feasibility report

Date: 14 October 2026
Environment: isolated owner-only Cloudflare Sites Worker with a separate D1 database  
Production impact: none; the Netlify production website was not changed

## Verdict

Better Auth and the implemented account lifecycle work correctly on the Cloudflare runtime, but the current secure password configuration is **not feasible on Workers Free**. Cloudflare's official [Workers limits](https://developers.cloudflare.com/workers/platform/limits/) still report a 10 ms CPU limit per Free-plan HTTP request as rechecked on 14 October 2026. Successful password verification consumed 89–146 ms CPU, activation password hashing consumed 174 ms, and recovery password hashing consumed 146 ms. Password strength was not reduced and no paid plan was enabled.

The isolated Sites runtime allowed the requests to complete and exposed Cloudflare CPU telemetry; it did not enforce the Free-plan 10 ms cutoff. The feasibility decision therefore compares measured Worker CPU directly with the published Free limit. The margin is too large to treat as measurement noise.

## Controls used

- The benchmark endpoint exists only when `APP_ENV=staging` and a separate high-entropy bearer secret matches using a timing-safe digest comparison.
- Only synthetic `@example.test` identities and `BENCH-` membership numbers are accepted.
- Preparation and cleanup are explicit; the final run removed the synthetic users, member, invitation and recovery records.
- Official Cloudflare Turnstile testing credentials were enabled only through the explicit staging-only `TURNSTILE_TEST_MODE` flag. Production rejects test credentials.
- The Site remained owner-only. No real member, financial or application record was used.

## Functional result

Run `auth-mv4dujj5-dc8f563e` completed successfully. The following checks passed:

- approved-member activation and email-verification state;
- invitation expiry/use state and replay denial;
- valid and invalid password login;
- simultaneous session persistence, session listing and targeted revocation;
- recovery request, administrator issuance and one-use reset;
- reset replay denial and revocation of pre-reset sessions;
- login with the new password and denial of the old password;
- authentication rate limiting. Repeated harness runs from one address were correctly returned as HTTP 429 until the 60-second window expired.

## Measured results

Client duration includes the owner-only access proxy and network. Worker wall/CPU values are Cloudflare log telemetry for the successful run.

| Operation | HTTP | Client ms | Worker wall ms | Worker CPU ms |
|---|---:|---:|---:|---:|
| Administrator login | 200 | 1,085 | 367 | 146 |
| Account activation/hash | 200 | 752 | 465 | 174 |
| Activation replay denial | 400 | 239 | 34 | 6 |
| Member login 1 | 200 | 741 | 277 | 99 |
| Member login 2 | 200 | 658 | 333 | 127 |
| Session list | 200 | 408 | 160 | 15 |
| Session revoke | 200 | 630 | 230 | 22 |
| Revoked-session denial | 401 | 395 | 92 | 10 |
| Recovery request | 202 | 368 | 97 | 12 |
| Recovery link issuance | 200 | 695 | 400 | 33 |
| Password reset/hash | 200 | 819 | 581 | 146 |
| Reset replay denial | 400 | 257 | 28 | 6 |
| Old-password denial | 401 | 500 | 292 | 130 |
| Recovered login | 200 | 438 | 264 | 89 |

All logged Worker outcomes were `ok`; the expected 400/401 responses are security assertions, not runtime errors. Successful password verification CPU ranged from 89 to 146 ms (median 113 ms), 8.9–14.6 times the Free request allowance.

## Secure alternatives requiring a decision

1. Approve Workers Paid for the application Worker, retain Better Auth and the current password hashing, then repeat load, cost and staging acceptance. This is the smallest architectural change but must not be enabled automatically.
2. Keep the Worker/D1 application but move password verification to an association-controlled conventional Node runtime that can safely afford the current hash cost. This adds a service, operational ownership and a protected service-to-service boundary.
3. Evaluate a mature managed authentication service with an acceptable free/low-cost tier, data-processing terms, account recovery and Cloudflare compatibility. This introduces an external processor and possible future cost.
4. Evaluate a passwordless WebAuthn/passkey design with secure assisted recovery. This avoids password hashing but is a material authentication redesign and requires device/recovery usability testing.

Reducing scrypt work factors merely to fit 10 ms is rejected. Until one alternative is approved and accepted, authentication remains a production blocker even though the lifecycle implementation is functionally complete.

