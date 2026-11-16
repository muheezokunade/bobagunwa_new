# Project audit

Date: 2026-10-11  
Scope: repository at `Bobagunwa` and the deployed public site at `https://bobagunwa-ijebu.netlify.app/`

This is the immutable starting-state audit, not the current implementation report. For current status, use `page-inventory.md`, `page-completion-report.md`, `12-testing-report.md`, `14-known-issues.md` and `staging-readiness-report.md`.

## Executive summary

The repository is a small, static public website. Its visual identity is strong enough to preserve, but it is not yet an operational membership platform. There is no authentication, database, API, server-side form processing, test suite, content management, member portal, financial ledger, or administrative interface.

The deployed About page was fetched and compared with the source. Its rendered structure, copy, navigation, logo, contact details, and current footer links match the checked-in source. The deployed HTML also contains a Netlify-generated promotional comment; this is hosting metadata, not an application feature or evidence of an AI-generated design.

The recommended path is an incremental migration to a same-origin Cloudflare Worker with static assets and D1. The current public pages should remain intact while private modules are built behind authenticated routes and tested against a local/staging D1 database.

## Current architecture

| Area | Finding |
| --- | --- |
| Framework | Next.js 16.2.6 pages implemented with the App Router |
| Runtime/build | Vinext 1.0.0-beta.3 + Vite 8 for Cloudflare-compatible builds; Next static export for Netlify |
| UI | React 19.2.6, handwritten global CSS, Lucide icons |
| Package manager | npm with `package-lock.json` |
| Public routes | `/`, `/about/`, `/members/`, `/activities/`, `/events/`, `/contact/` |
| Hosting | Production public site on Netlify; an OpenAI Sites project configuration is present but has no D1 or R2 binding |
| Persistence | None |
| Authentication/authorisation | None |
| Tests | None at audit start |
| Forms | Contact form is visual only (`type="button"` and no submit handler) |

## Reusable assets and design worth preserving

- The supplied crest at `public/bobagunwa-logo.png` and social image at `public/og.png`.
- The restrained wine, dark brown, pale blue, yellow, and red brand palette.
- The editorial public-page direction: large serif headings, strong spacing, structured page heroes, and minimal motion.
- Shared `SiteHeader`, `SiteFooter`, and `InnerHero` components.
- Current public URLs, which should remain stable through migration.
- Consistent professional Lucide iconography.

## Existing functionality

- Responsive public navigation and six static public pages.
- Per-page metadata on Home and About; global Open Graph/Twitter metadata.
- Clickable email and telephone links.
- Static responsive layouts in one global stylesheet.
- Netlify security headers for MIME sniffing and referrer policy.

## Technical problems and architectural weaknesses

1. `next.config.ts` forces `output: 'export'`. Static export cannot host authenticated server routes, D1-backed APIs, or a real contact submission workflow.
2. The Netlify build and the Vinext/Cloudflare build are separate paths, increasing the chance of environment drift.
3. `vite.config.ts` supports a D1 binding in principle, but `.openai/hosting.json` currently has `d1: null` and `r2: null`.
4. All public content is hard-coded in TSX. Events, activities, officers, news, and page copy cannot be maintained by administrators.
5. `app/globals.css` is a 461-line global stylesheet containing older unused inner-page rules alongside the current editorial rules. This makes regression-prone changes likely.
6. There is no error boundary, application logging policy, health check, or operational runbook.
7. Several pages use generic repeated three-card structures rather than page-specific information architecture.
8. The repository had no README, environment template, migrations, or documented decisions at audit start.

## Security and privacy risks

- There is currently no private data, which limits immediate exposure, but there is also no security boundary suitable for adding it.
- No authentication, server-side authorisation, CSRF protection, request validation, rate limiting, payload limits, audit logging, or session revocation exists.
- The contact form gives the appearance of a submission workflow but cannot store or transmit a message.
- Netlify headers omit a Content Security Policy, HSTS, Permissions Policy, and frame protections. These should be introduced carefully and verified against required assets.
- No dependency vulnerability policy or automated dependency scan is configured.
- The initial `npm audit` found 20 advisories, including a critical advisory in Next.js. Compatible framework/toolchain updates removed the critical issue; residual transitive build-tool findings remain recorded in the testing report.
- No privacy notice, retention policy, backup policy, or data-subject correction procedure exists.
- Footer “Terms” and “Privacy Policy” links incorrectly point to `/about`.

## Accessibility issues to verify and fix

- Interface text appears as small as 8–11px in several places. This is likely difficult to read, especially on mobile.
- Focus styling is incomplete. Form inputs change border colour but there is no consistent `:focus-visible` treatment across links, buttons, summary controls, and form controls.
- The mobile menu uses `<details>` and is usable without JavaScript, but focus order, expanded-state announcement, and escape behaviour require testing.
- The contact form lacks `required`, autocomplete guidance, validation messages, and a live status region.
- Contrast, touch-target sizes, reduced-motion behaviour, heading hierarchy, and zoom at 200% have not been tested.
- Page landmarks and semantic structure are generally reasonable, but must be checked with automated and keyboard tests.

## Content discrepancies and unverified material

- Contact email differs: `secretariat@bobagunwa.com` appears on Home while `eweso@bobagunwa.com` appears in the header, footer, and Contact page.
- `1980–82` / `EST. 1980–82` appears in the design and on the supplied crest but its intended historical meaning has not been confirmed.
- Public event dates, activity descriptions, leadership offices, telephone number, street address, motto orthography, and association narrative are provisional until approved.
- The leadership section shows offices and crest placeholders, not verified officer profiles.
- Public event/news cards are hard-coded examples, not event records.
- The root metadata uses `https://bobagunwa.com` while the currently inspected deployment is on Netlify; canonical-domain ownership and cutover are unverified.

See `docs/content-verification-checklist.md` for the approval list.

## Performance and SEO observations

- The current site is mostly static and dependency-light, which is a good baseline.
- Images use raw `<img>` tags without explicit dimensions, responsive sources, or an image delivery policy; layout shift and over-download should be measured.
- Only Home and About have descriptive page metadata. Members, Activities, Events, and Contact require unique descriptions.
- No sitemap, robots configuration, canonical policy, or structured data is present.
- Private routes do not yet exist; when added they must be excluded from indexing and caching.

## Proposed improvements

1. Retain the public React components and CSS while separating public, portal, admin, domain, and infrastructure modules.
2. Converge on a same-origin Cloudflare Worker deployment using Vinext/Vite and D1 after staging proves compatibility.
3. Add version-controlled D1 migrations and a repository-level schema validation check.
4. Keep authentication framework tables separate from association domain tables. Adopt Better Auth only after its D1 integration and password hashing are benchmarked on the selected Workers plan.
5. Model finance as append-only posted records plus explicit allocations and reversals; calculate balances from source records.
6. Add real server-side contact storage, then membership, RBAC, and finance in small vertical slices.
7. Move editable public records to a focused CMS layer only after authorisation and sanitisation are in place.
8. Add unit, integration, end-to-end, accessibility, and reconciliation tests before staging sign-off.

## Migration risks

- Vinext is beta software. Critical Next.js behaviours must be verified on the target Cloudflare runtime.
- Workers Free provides only 10ms CPU per HTTP request. Password hashing and server rendering may exceed that budget; authentication needs an actual benchmark.
- D1 is single-threaded per database. Financial posting must use short, indexed, atomic batches and idempotency keys.
- Static-export assumptions in components/configuration may break when server routes are introduced.
- Business rules for membership approval, fee eligibility, payment verification, reversal authority, and statement wording remain undecided.
- Production cutover, DNS, email delivery, and real-member data import require association-controlled credentials and explicit approval.

## Scope assumptions

- Initial scale is about 100 members; design target is at least 1,000.
- Nigerian naira is the default currency and all amounts are stored as integer kobo.
- Initial payments are recorded manually; there is no online payment gateway.
- No public member directory is assumed.
- Real member and financial data will not be used in development or public staging.
- Existing public site remains production until a staging release passes the launch checklist and cutover is approved.

## Audit evidence and limitations

- Repository files, package versions, routes, CSS, assets, build configuration, and Git history were inspected locally.
- The deployed About page was fetched on 2026-10-11 and compared with `app/about/page.tsx` plus shared header/footer components; it matches the current source at the structural/content level.
- A full visual browser and assistive-technology audit has not yet been completed. Lighthouse, axe, keyboard, screen-reader, and device tests remain required.
- No Cloudflare database or production secrets were created during this audit.
