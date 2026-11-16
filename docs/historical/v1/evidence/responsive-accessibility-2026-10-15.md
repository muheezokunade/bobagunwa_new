# Responsive and semantic route audit — 15 October 2026

Environment: local Vinext/Cloudflare runtime, project-local synthetic D1, isolated member and administrator fixtures. No production endpoint or real association record was used.

## Scope and result

The 52 substantive page views were exercised as the intended role at each of these exact browser viewports:

- 320×800: 13 public, 4 authentication, 8 member and 27 administration views passed.
- 768×1024: the same 52 views passed.
- 1440×900: the same 52 views passed.

`/portal` and `/portal/logout` are redirect/action routes rather than substantive page views. Their access and sign-out behaviour remain covered by the live authentication suite, bringing the route registry coverage to 54/54.

Each browser view was checked for exactly one `main` landmark, at least one `h1`, document-level horizontal overflow, missing image `alt` attributes, unlabelled visible inputs/selects/textareas, unnamed visible buttons, and password fields inside GET forms. All checks passed after the corrections below.

## Corrections made during the audit

1. Client-enhanced forms now declare an explicit native fallback method. Mutation and authentication forms use `method="post"`; the financial report filter retains `method="get"`. This prevents an unhydrated password form from placing credentials in a query string.
2. The 320px homepage overflow was removed by allowing hero grid children to shrink, constraining the mobile headline, and changing the news row to `72px minmax(0, 1fr) 42px` with bounded spacing and wrapping.

## Acceptance boundary

This closes the automated responsive/semantic route-view check at phone, tablet and desktop widths. It does not replace manual keyboard journey testing, screen-reader testing, browser-matrix testing, Lighthouse/performance review or testing on the final canonical HTTPS staging origin.

## Post-fix regression gates

After the form and layout corrections, the following local gates passed on 15 October 2026:

- TypeScript typecheck.
- ESLint with zero warnings and zero errors.
- 28 unit tests, 48-table schema check, foreign-key check and financial database invariants.
- 54/54 route inventory and page-file check; all routes deliberately remain Partial.
- Vinext production build using the bundled Node 24 runtime.
- Live public suite: 19 pages and 18 navigation targets, public writes, Turnstile enforcement, membership validation/status/duplicate detection and privacy-safe recovery.
- Live member-auth suite: anonymous/invalid denial, login, nine member routes, writes, session revocation and sign-out.
- Live administration suite after a local synthetic fixture reset: ordinary-member denial, 28 authorised views representing all 27 administration patterns, 86-member import, finance, publishing, roles, applications, activation/recovery and replay controls.
- 54-route visible-copy audit.
- Authenticated backup drill: AES-256-GCM/scrypt, 48-table isolated restore, wrong-password/tamper/truncation rejection, valid foreign keys and plaintext cleanup.

An initial repeat of the administrative suite encountered a 409 because its synthetic application had already been advanced by the earlier successful run. Resetting only the documented local test fixtures restored the expected initial state; the complete rerun passed. This was fixture state, not a production or application defect.
