# Release-hardening verification

Date: 14 October 2026
Environment: local Cloudflare/Vinext runtime with project-local synthetic D1
Production impact: none

## Passing gates

- TypeScript typecheck: pass.
- ESLint: pass with zero errors and zero warnings.
- Unit suite: 28 passed, 0 failed.
- Schema: 48 tables, 19 permissions and 5 roles; foreign-key check passed.
- Database invariants: valid allocation/reversal accepted; over-allocation, cross-member allocation and excessive reversal rejected.
- Route registry: 54 routes accounted for and 54 page files present; all remain Partial.
- Production Vinext build: pass using Node 24, which satisfies the repository's Node `>=22.13.0` requirement.
- Public live suite: pass, including navigation, Turnstile enforcement, application validation/status, duplicate detection, eligibility flagging and enumeration-safe recovery.
- Visible-copy audit: pass across 54 routes.
- Authentication/member live suite: pass across all nine member views, profile/RSVP/read/preference writes, remote-session revocation and sign-out.
- Administrative live suite: pass across 28 authorised views plus ordinary-member denial, imports, finance, publishing, role changes, application decisions, recovery and replay controls.
- Authenticated backup drill: pass with AES-256-GCM/scrypt, 48-table isolated restore, wrong-password/tamper/truncation rejection, foreign keys and plaintext cleanup.

The first administrative rerun on the hot development server encountered a transient Miniflare internal `fetch failed` response after deliberate origin-denial/concurrency cases. The database fixtures were reset, the local runtime was restarted cleanly, and the complete administrative suite then passed. This does not establish a staging-runtime result; representative staging concurrency remains an acceptance gate.

The official Cloudflare Workers limit was rechecked on 14 October 2026. Workers Free still allows 10ms CPU per HTTP request, so the earlier measured 89–174ms secure password work remains incompatible with the free plan.

Netlify production, production DNS/databases and client personal/financial records were not modified.
