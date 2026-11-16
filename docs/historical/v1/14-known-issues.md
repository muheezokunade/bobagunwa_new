# Known issues and risks

## Blocking production readiness

- Better Auth passed the functional lifecycle on an isolated owner-only Cloudflare Worker, but secure password operations consumed 89–174 ms CPU. This is incompatible with the Workers Free 10 ms/request limit. Password security was not weakened; hosting/auth architecture approval is now the primary production blocker. See `auth-feasibility-report.md`.
- No production D1 database, environment bindings, secrets, or association-controlled Cloudflare account is configured.
- Several business decisions in `docs/decisions.md` remain unresolved, including named approvers and final membership-number presentation.
- Client-supplied history, welcome, About, leadership and sitemap material is present in a private package but remains unverified and unpublished; five chief titles are unnamed and one chief entry is duplicated in the source.
- Contact, membership-application and recovery forms use server-verified Turnstile. The isolated benchmark used Cloudflare's explicit staging test mode; production Turnstile keys and final-domain hostname enforcement remain pending.
- Membership decision notifications currently use an explicit administrator-delivered, low-cost private channel. The association must approve the production channel and operating procedure; no automatic email/SMS delivery is claimed.
- Privacy/terms routes exist, but their content has not received legal review or versioned CMS publication.
- The client package checksum and 86-row private dry-run pass, and the importer creates no accounts. Every source row is still marked `PENDING_VERIFICATION` and `NO` for production approval; no real row has been imported.
- Local member/finance import and authenticated AES-256-GCM isolated restore drills pass with synthetic data. Automated responsive/semantic checks pass all 52 substantive route views at 320px, tablet and desktop sizes. Remote D1 export custody/restore, formal security test, manual assistive-technology audit and full staging acceptance remain outstanding.
- The full dependency audit reports 8 high advisories in development/build-only paths, all converging on the unpatched `braces` glob-pattern denial-of-service advisory. The production-only audit is clean and affected packages are absent from the Worker bundle. Monitor upstream and record risk acceptance before staging sign-off.

## Engineering risks

- Vinext is beta software and requires runtime regression testing.
- The Sites deployment migration service could not parse two valid multi-statement SQLite trigger bodies (`incomplete input`). Canonical `migrations/` retain both financial invariant triggers and pass Wrangler locally; the isolated Sites staging package omits them and must not be used for live finance. Production must use a migration path that installs and verifies the canonical triggers.
- Static Netlify production and Cloudflare target builds can drift.
- The existing global stylesheet includes dead/duplicated rules and very small interface text.
- Several public pages still contain unverified editorial content; D1-backed lists use only published records and public member opt-in.
- The native-image lint/performance warnings are resolved, and protected/public route-view checks pass at 320px, tablet and desktop sizes. Manual keyboard journeys, screen-reader testing, browser-matrix testing and final-origin Lighthouse/performance acceptance remain outstanding.

## Deliberately not claimed

No route is marked production-complete. Core public writes, authentication, membership administration, imports, ledger operations, manual payment handling, historical finance, reports, role assignment and publishing are functional with synthetic data. The account lifecycle also passes in isolated staging, but Workers Free is ruled out for the current secure password implementation. Hosting approval, identity-verification/delivery policy, remote backup custody, accessibility/security acceptance, real-data migration and production deployment remain incomplete.
